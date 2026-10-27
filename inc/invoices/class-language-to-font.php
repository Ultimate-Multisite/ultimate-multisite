<?php
/**
 * Maps languages to the packaged invoice fonts.
 *
 * @package WP_Ultimo
 * @subpackage Invoices
 * @since 2.18.0
 */

namespace WP_Ultimo\Invoices;

// Exit if accessed directly
defined('ABSPATH') || exit;

use Mpdf\Language\LanguageToFont;

/**
 * Chooses the font mPDF uses for each language of an invoice.
 *
 * By default mPDF maps eight Indic scripts to the single font name "freeserif", which
 * is not packaged. This class gives every script its own packaged Noto Sans font
 * (assets/fonts/noto/) and defers to mPDF for all other languages.
 *
 * It is passed to mPDF through the "languageToFont" configuration key, so the same
 * instance is used for language spans, tables and the document default.
 *
 * @since 2.18.0
 */
class Language_To_Font extends LanguageToFont {

	/**
	 * Version of the packaged fonts.
	 *
	 * Part of every font name. mPDF caches parsed fonts by name in the temporary
	 * directory, so changing a font file without changing its name would keep
	 * serving stale cache data. Bump this whenever a file in assets/fonts/noto/ changes.
	 */
	const FONT_VERSION = '1';

	/**
	 * Fonts shipped with mPDF that are kept for scripts without a Noto font.
	 *
	 * Myanmar (Tharlon) and Sindhi (Lateef). Noto Sans Myanmar has a glyph defect
	 * under mPDF, and Lateef covers Sindhi letters that DejaVu lacks.
	 */
	const MPDF_FONTS = ['tharlon', 'lateef'];

	/**
	 * Script slug => language codes (ISO 639-1 and 639-2) that use it.
	 *
	 * The first language of each entry is the one mPDF assigns when it detects the script
	 * (see Mpdf\Language\ScriptToLanguage).
	 *
	 * @var array<string, string[]>
	 */
	const LANGUAGES = [
		'devanagari' => ['hi', 'mr', 'ne', 'sa', 'hin', 'mar', 'nep', 'san'],
		'bengali'    => ['bn', 'as', 'ben', 'asm'],
		'gujarati'   => ['gu', 'guj'],
		'gurmukhi'   => ['pa', 'pan'],
		'tamil'      => ['ta', 'tam'],
		'malayalam'  => ['ml', 'mal'],
		'oriya'      => ['or', 'ori'],
		'thaana'     => ['dv', 'div'],
		'telugu'     => ['te', 'tel'],
		'kannada'    => ['kn', 'kan'],
		'sinhala'    => ['si', 'sin'],
		'thai'       => ['th', 'tha'],
		'lao'        => ['lo', 'lao'],
		'khmer'      => ['km', 'khm'],
		'hebrew'     => ['he', 'heb', 'yi', 'yid'],
		'ethiopic'   => ['am', 'amh', 'ti', 'tir'],
	];

	/**
	 * Returns the mPDF font name of a packaged script font.
	 *
	 * @since 2.18.0
	 *
	 * @param string $script Script slug, e.g. "devanagari".
	 * @return string
	 */
	public static function get_font_name(string $script): string {

		return 'noto' . $script . '_v' . self::FONT_VERSION;
	}

	/**
	 * Returns the directory with the packaged fonts.
	 *
	 * @since 2.18.0
	 * @return string
	 */
	public static function get_font_dir(): string {

		return WP_ULTIMO_PLUGIN_DIR . 'assets/fonts/noto';
	}

	/**
	 * Returns the mPDF fontdata entries of the packaged fonts.
	 *
	 * Only the regular and bold files exist, so the italic styles use the upright files.
	 * useOTL enables OpenType Layout, which Indic, Thai, Lao and Khmer shaping needs.
	 *
	 * @since 2.18.0
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fontdata(): array {

		$fontdata = [];

		foreach (array_keys(self::LANGUAGES) as $script) {
			$file = 'NotoSans' . ucfirst($script);

			$fontdata[ self::get_font_name($script) ] = [
				'R'      => $file . '-Regular.ttf',
				'B'      => $file . '-Bold.ttf',
				'I'      => $file . '-Regular.ttf',
				'BI'     => $file . '-Bold.ttf',
				'useOTL' => 0xFF,
			];
		}

		return $fontdata;
	}

	/**
	 * Returns the font for a language, as mPDF expects it.
	 *
	 * @since 2.18.0
	 *
	 * @param string $llcc       Language tag, e.g. "hi", "hi-IN" or "und-Ethi".
	 * @param bool   $adobe_cjk   Whether mPDF uses its built-in Adobe CJK fonts.
	 * @return array{0: bool, 1: string} Whether core fonts suffice, and the font name ('' for none).
	 */
	public function getLanguageOptions($llcc, $adobe_cjk) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

		$script = self::get_script_by_language((string) $llcc);

		if ($script) {
			return [false, self::get_font_name($script)];
		}

		return parent::getLanguageOptions($llcc, $adobe_cjk);
	}

	/**
	 * Finds the packaged script of a language tag.
	 *
	 * @since 2.18.0
	 *
	 * @param string $llcc Language tag.
	 * @return string Script slug, or an empty string when no packaged font applies.
	 */
	public static function get_script_by_language(string $llcc): string {

		$tags = explode('-', strtolower(str_replace('_', '-', $llcc)));

		// Undetermined language written in Ethiopic, as mPDF names it.
		if ('und' === $tags[0] && 'ethi' === ($tags[1] ?? '')) {
			return 'ethiopic';
		}

		foreach (self::LANGUAGES as $script => $languages) {
			if (in_array($tags[0], $languages, true)) {
				return $script;
			}
		}

		return '';
	}
}
