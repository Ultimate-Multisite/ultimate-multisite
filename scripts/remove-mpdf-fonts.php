<?php
/**
 * Removes mPDF font files that the plugin does not need, to reduce package size.
 *
 * Invoice PDF language support (GH#2047):
 *
 * Most non-Latin scripts use the Noto Sans fonts packaged in assets/fonts/noto/ (see the
 * README there). The mPDF fonts they replace are stripped here: Hebrew (TaameyDavidCLM),
 * Thai (Garuda), Lao (Dhyana), Khmer (KhmerOS), Kannada (Lohit-Kannada) and Telugu
 * (Pothana2000). Sinhala (kaputaunicode.ttf) stays stripped as well: its only licence
 * text is "All rights reserved", so it cannot be redistributed. Noto covers it.
 *
 * Two mPDF fonts are kept, by script:
 *
 * - Myanmar: Tharlon-Regular.ttf, TharlonOFL.txt (Noto Sans Myanmar has a glyph defect under mPDF)
 * - Sindhi:  LateefRegOT.ttf, Lateef font OFL.txt (covers Sindhi letters that DejaVu lacks)
 *
 * mPDF defines only some styles for these two fonts; it simulates the missing bold and
 * italic from the regular file, so no remap is needed. Any other retained font family
 * must keep all four fontdata styles (R, B, I, BI) resolvable: remap the missing styles in
 * Invoice::get_missing_fontdata() when changing this list.
 *
 * @package WP_Ultimo
 */

// This is a composer CLI script that runs without WordPress loaded.
// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.Security.EscapeOutput.OutputNotEscaped

$fonts = [
	'AboriginalSansREGULAR.ttf',
	'Aegean.otf',
	'Aegyptus.otf',
	'Akkadian.otf',
	'ayar.ttf',
	'damase_v.2.ttf',
	'DBSILBR.ttf',
	'DejaVuSerif.ttf',
	'Dhyana-Regular.ttf',
	'Dhyana-Bold.ttf',
	'DhyanaOFL.txt',
	'DejaVuSansMono-Oblique.ttf',
	'DejaVuSerif-BoldItalic.ttf',
	'DejaVuSerifCondensed-BoldItalic.ttf',
	'DejaVuSansMono-Bold.ttf',
	'DejaVuSerif-Italic.ttf',
	'DejaVuSansMono.ttf',
	'DejaVuSansMono-BoldOblique.ttf',
	'DejaVuSerif-Bold.ttf',
	'DejaVuSerifCondensed-Italic.ttf',
	'DejaVuSansCondensed-BoldOblique.ttf',
	'DejaVuSansCondensed-Oblique.ttf',
	'DejaVuSans-Oblique.ttf',
	'DejaVuSans-BoldOblique.ttf',
	'DejaVuSans-Bold.ttf',
	'DejaVuSans.ttf',
	'FreeMonoBoldOblique.ttf',
	'FreeMonoOblique.ttf',
	'FreeSans.ttf',
	'FreeSansBold.ttf',
	'FreeSansBoldOblique.ttf',
	'FreeSansOblique.ttf',
	'FreeSerif.ttf',
	'FreeSerifBold.ttf',
	'FreeSerifBoldItalic.ttf',
	'FreeSerifItalic.ttf',
	'Garuda.ttf',
	'Garuda-Bold.ttf',
	'Garuda-BoldOblique.ttf',
	'Garuda-Oblique.ttf',
	'GNUFreeFontinfo.txt',
	'Jomolhari.ttf',
	'Jomolhari-OFL.txt',
	'kaputaunicode.ttf',
	'KhmerOS.ttf',
	'KhmerOFL.txt',
	'lannaalif-v1-03.ttf',
	'Lohit-Kannada.ttf',
	'LohitKannadaOFL.txt',
	'ocrb10.ttf',
	'ocrbinfo.txt',
	'Padauk-book.ttf',
	'Pothana2000.ttf',
	'Quivira.otf',
	'Sun-ExtA.ttf',
	'Sun-ExtB.ttf',
	'SundaneseUnicode-1.0.5.ttf',
	'SyrCOMEdessa.otf',
	'SyrCOMEdessa_license.txt',
	'TaameyDavidCLM-Medium.ttf',
	'TaameyDavidCLM-LICENSE.txt',
	'TaiHeritagePro.ttf',
	'UnBatang_0613.ttf',
	'Uthman.otf',
	'XB Riyaz.ttf',
	'XB RiyazBd.ttf',
	'XB RiyazBdIt.ttf',
	'XB RiyazIt.ttf',
	'XW Zar Font Info.txt',
	'ZawgyiOne.ttf',
	'Abyssinica_SIL.ttf',
];

$dir = __DIR__ . '/../vendor/mpdf/mpdf/ttfonts/';

echo "\nRemoving fonts...\n\n";

foreach ($fonts as $font) {
	$file_path = $dir . $font;
	if (file_exists($file_path)) {
		unlink($file_path);
		echo "Deleted: $font\n";
	} else {
		echo "Not found: $font\n";
	}
}
