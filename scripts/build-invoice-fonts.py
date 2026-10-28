#!/usr/bin/env python3
"""Builds the Noto Sans fonts used for invoice PDFs (assets/fonts/noto/).

mPDF cannot use Noto Sans as published. This script adapts each per-script font so
that mPDF can load and shape it, without changing how the glyphs look:

 1. Strip GDEF MarkGlyphSets and the UseMarkFilteringSet lookup flag (mPDF throws on them).
 2. Rewrite GSUB contextual lookups of format 3 (type 5/3) as chaining context format 3
    (type 6/3) with empty backtrack and lookahead. mPDF does not support 5/3 but supports 6/3.
 3. Remove GSUB 'locl' features. mPDF's Indic setup reads a 'replace' key that chaining
    lookups do not have, which raises a PHP warning. With autoScriptToLang the language is
    hi/bn/ta/..., so the locale-specific variants are never selected.
 4. Remove references to GPOS chaining context lookups of format 1 and 2 (unsupported by mPDF)
    and any feature left empty by that (mPDF reads LookupListIndex[0] of every feature).
 5. Remove overlapping contours. The static Noto instances keep overlaps, which show as
    double outlines when mPDF draws the translucent "Paid" watermark.
 6. Normalise units-per-em to 1000.
 7. Add the ASCII digits, punctuation and a few invoice symbols from Noto Sans. mPDF attaches
    Common-script characters (digits, punctuation, "$") to the adjacent script run, so a script
    font without them would render "123" or "$" as tofu inside a Hebrew or Thai run.

Each script keeps its own font file: merging several scripts into one font makes mPDF break
words in the middle of a cluster when wrapping.

Inputs (not committed, see assets/fonts/noto/README.md for where to get them):
  --current  directory with the current static unhinted TTFs (NotoSans<Script>-Regular/Bold.ttf)
  --legacy   directory with the 2017 Noto builds, which mPDF loads natively for some scripts
  --latin    directory with NotoSans-Regular.ttf and NotoSans-Bold.ttf

Requirements: pip install -r scripts/build-invoice-fonts-requirements.txt

Usage: python3 scripts/build-invoice-fonts.py --current DIR --legacy DIR --latin DIR --out DIR
"""

import argparse
import os
import sys
import tempfile

from fontTools import merge, subset
from fontTools.ttLib import TTFont
from fontTools.ttLib import scaleUpem
from fontTools.ttLib.removeOverlaps import removeOverlaps
from fontTools.ttLib.tables import otTables as ot

# Script => which Noto build is used. 'legacy' builds load in mPDF natively; the current builds
# of these scripts display misplaced marks or raise warnings under mPDF's shaper.
SCRIPTS = {
    'Devanagari': 'current',
    'Bengali': 'current',
    'Gujarati': 'current',
    'Gurmukhi': 'legacy',
    'Tamil': 'current',
    'Malayalam': 'legacy',
    'Oriya': 'current',
    'Thaana': 'current',
    'Telugu': 'current',
    'Kannada': 'current',
    'Sinhala': 'legacy',
    'Thai': 'legacy',
    'Lao': 'legacy',
    'Khmer': 'legacy',
    'Hebrew': 'legacy',
    'Ethiopic': 'legacy',
}

WEIGHTS = ['Regular', 'Bold']

# Code points taken from Noto Sans: ASCII, no-break space, dashes, quotes and the euro sign.
FILL_UNICODES = list(range(0x20, 0x7F)) + [0xA0, 0x2013, 0x2014, 0x2018, 0x2019, 0x201C, 0x201D, 0x20AC]

UNITS_PER_EM = 1000


def iter_lookups(font):
    for tag in ('GSUB', 'GPOS'):
        if tag in font and font[tag].table.LookupList:
            for lookup in font[tag].table.LookupList.Lookup:
                yield lookup


def strip_mark_glyph_sets(font):
    if 'GDEF' in font:
        gdef = font['GDEF'].table
        if getattr(gdef, 'MarkGlyphSetsDef', None):
            gdef.MarkGlyphSetsDef = None
            gdef.Version = 0x00010000
            if hasattr(gdef, 'VarStore'):
                gdef.VarStore = None
    for lookup in iter_lookups(font):
        if lookup.LookupFlag & 0x0010:
            lookup.LookupFlag &= ~0x0010
            if hasattr(lookup, 'MarkFilteringSet'):
                lookup.MarkFilteringSet = None


def context3_to_chain3(subtable):
    chain = ot.ChainContextSubst()
    chain.Format = 3
    chain.BacktrackGlyphCount = 0
    chain.BacktrackCoverage = []
    chain.InputGlyphCount = subtable.GlyphCount
    chain.InputCoverage = subtable.Coverage
    chain.LookAheadGlyphCount = 0
    chain.LookAheadCoverage = []
    chain.SubstCount = subtable.SubstCount
    chain.SubstLookupRecord = subtable.SubstLookupRecord
    return chain


def convert_gsub_context3(font):
    if 'GSUB' not in font or not font['GSUB'].table.LookupList:
        return
    for lookup in font['GSUB'].table.LookupList.Lookup:
        is_extension = lookup.LookupType == 7
        real_type = lookup.SubTable[0].ExtensionLookupType if is_extension else lookup.LookupType
        if real_type != 5:
            continue
        inner = [s.ExtSubTable if is_extension else s for s in lookup.SubTable]
        if not all(getattr(s, 'Format', 0) == 3 for s in inner):
            continue
        for i, subtable in enumerate(inner):
            converted = context3_to_chain3(subtable)
            if is_extension:
                lookup.SubTable[i].ExtensionLookupType = 6
                lookup.SubTable[i].ExtSubTable = converted
            else:
                lookup.SubTable[i] = converted
        if not is_extension:
            lookup.LookupType = 6


def remove_features(table, gone):
    """Removes feature records and renumbers every reference to the remaining ones."""
    records = table.FeatureList.FeatureRecord
    remap = {}
    for index in range(len(records)):
        if index not in gone:
            remap[index] = len(remap)
    table.FeatureList.FeatureRecord = [r for i, r in enumerate(records) if i not in gone]
    table.FeatureList.FeatureCount = len(table.FeatureList.FeatureRecord)

    def fix(langsys):
        langsys.FeatureIndex = [remap[i] for i in langsys.FeatureIndex if i in remap]
        langsys.FeatureCount = len(langsys.FeatureIndex)
        if langsys.ReqFeatureIndex != 0xFFFF:
            langsys.ReqFeatureIndex = remap.get(langsys.ReqFeatureIndex, 0xFFFF)

    for script_record in table.ScriptList.ScriptRecord:
        if script_record.Script.DefaultLangSys:
            fix(script_record.Script.DefaultLangSys)
        for lang_record in script_record.Script.LangSysRecord:
            fix(lang_record.LangSys)


def clean_layout(font):
    if 'GSUB' in font and font['GSUB'].table.FeatureList:
        table = font['GSUB'].table
        gone = {i for i, r in enumerate(table.FeatureList.FeatureRecord) if r.FeatureTag == 'locl'}
        if gone:
            remove_features(table, gone)

    if 'GPOS' in font and font['GPOS'].table.LookupList:
        table = font['GPOS'].table
        unsupported = set()
        for index, lookup in enumerate(table.LookupList.Lookup):
            is_extension = lookup.LookupType == 9
            formats = []
            for subtable in lookup.SubTable:
                real_type = subtable.ExtensionLookupType if is_extension else lookup.LookupType
                inner = subtable.ExtSubTable if is_extension else subtable
                if real_type == 8:
                    formats.append(getattr(inner, 'Format', 0))
            if formats and any(f in (1, 2) for f in formats):
                if all(f in (1, 2) for f in formats):
                    unsupported.add(index)
                else:
                    keep = [s for s in lookup.SubTable
                            if getattr(s.ExtSubTable if is_extension else s, 'Format', 0) == 3]
                    lookup.SubTable = keep
                    lookup.SubTableCount = len(keep)
        for record in table.FeatureList.FeatureRecord:
            record.Feature.LookupListIndex = [i for i in record.Feature.LookupListIndex if i not in unsupported]
            record.Feature.LookupCount = len(record.Feature.LookupListIndex)
        empty = {i for i, r in enumerate(table.FeatureList.FeatureRecord) if not r.Feature.LookupListIndex}
        if empty:
            remove_features(table, empty)


def drop_unmergeable_tables(font):
    for tag in ('vhea', 'vmtx', 'STAT', 'avar', 'fvar', 'gvar', 'MVAR', 'HVAR', 'DSIG'):
        if tag in font:
            del font[tag]


def adapt_font(source, destination):
    font = TTFont(source)
    strip_mark_glyph_sets(font)
    convert_gsub_context3(font)
    clean_layout(font)
    try:
        removeOverlaps(font)
    except Exception as error:  # noqa: BLE001 - report and continue, the font is still usable.
        print(f'warning: could not remove overlaps in {os.path.basename(source)}: {error}', file=sys.stderr)
    if font['head'].unitsPerEm != UNITS_PER_EM:
        scaleUpem.scale_upem(font, UNITS_PER_EM)
    drop_unmergeable_tables(font)
    font.save(destination)


def ascii_subset(latin_font, destination):
    options = subset.Options()
    options.layout_features = []  # digits and punctuation need no shaping
    options.notdef_outline = False
    options.name_IDs = [1, 2]
    subsetter = subset.Subsetter(options)
    font = TTFont(latin_font)
    subsetter.populate(unicodes=FILL_UNICODES)
    subsetter.subset(font)
    drop_unmergeable_tables(font)
    font.save(destination)


def build(args):
    os.makedirs(args.out, exist_ok=True)
    work = tempfile.mkdtemp(prefix='invoice-fonts-')
    sources = {'current': args.current, 'legacy': args.legacy}

    for weight in WEIGHTS:
        latin = os.path.join(work, f'latin-{weight}.ttf')
        ascii_subset(os.path.join(args.latin, f'NotoSans-{weight}.ttf'), latin)

        for script, source_kind in SCRIPTS.items():
            name = f'NotoSans{script}-{weight}.ttf'
            adapted = os.path.join(work, f'adapted-{name}')
            adapt_font(os.path.join(sources[source_kind], name), adapted)
            # The script font goes first so its own glyphs win for shared code points.
            merged = merge.Merger().merge([adapted, latin])
            merged.save(os.path.join(args.out, name))
            print(f'{name}: {os.path.getsize(os.path.join(args.out, name))} bytes ({source_kind} build)')


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--current', required=True)
    parser.add_argument('--legacy', required=True)
    parser.add_argument('--latin', required=True)
    parser.add_argument('--out', required=True)
    build(parser.parse_args())


if __name__ == '__main__':
    main()
