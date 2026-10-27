<?php
/**
 * Removes mPDF font files that the plugin does not need, to reduce package size.
 *
 * Fonts retained for invoice PDF language support (GH#2039), by script:
 *
 * - Hebrew:  TaameyDavidCLM-Medium.ttf, TaameyDavidCLM-LICENSE.txt
 * - Thai:    Garuda.ttf, Garuda-Bold.ttf (oblique files are stripped)
 * - Lao:     Dhyana-Regular.ttf, Dhyana-Bold.ttf, DhyanaOFL.txt
 * - Khmer:   KhmerOS.ttf, KhmerOFL.txt
 * - Myanmar: Tharlon-Regular.ttf, TharlonOFL.txt
 * - Kannada: Lohit-Kannada.ttf, LohitKannadaOFL.txt
 * - Telugu:  Pothana2000.ttf
 * - Sindhi:  LateefRegOT.ttf, Lateef font OFL.txt
 *
 * Sinhala (kaputaunicode.ttf) stays stripped: its only licence text is
 * "All rights reserved", so redistribution is not allowed.
 *
 * Every retained font family must keep all four fontdata styles (R, B, I, BI)
 * resolvable. mPDF defines only some styles for these fonts, and the oblique
 * Garuda files are stripped, so Invoice::get_missing_fontdata() remaps the
 * missing styles to a retained file. Update that method when changing this list.
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
	'Garuda-BoldOblique.ttf',
	'Garuda-Oblique.ttf',
	'GNUFreeFontinfo.txt',
	'Jomolhari.ttf',
	'Jomolhari-OFL.txt',
	'kaputaunicode.ttf',
	'lannaalif-v1-03.ttf',
	'ocrb10.ttf',
	'ocrbinfo.txt',
	'Padauk-book.ttf',
	'Quivira.otf',
	'Sun-ExtA.ttf',
	'Sun-ExtB.ttf',
	'SundaneseUnicode-1.0.5.ttf',
	'SyrCOMEdessa.otf',
	'SyrCOMEdessa_license.txt',
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
