<?php
/**
 * Handles the generation of PDF Invoices.
 *
 * @package WP_Ultimo
 * @subpackage Invoices
 * @since 2.0.0
 */

namespace WP_Ultimo\Invoices;

// Exit if accessed directly
defined('ABSPATH') || exit;

use Mpdf\Config\ConfigVariables;
use Mpdf\Language\LanguageToFont;
use Mpdf\Language\ScriptToLanguage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\Ucdn;
use WP_Ultimo\Exception\Runtime_Exception;

/**
 * Handles the generation of PDF Invoices.
 *
 * @since 2.0.0
 */
class Invoice {

	/**
	 * Keeps the settings key for the top-bar.
	 */
	const KEY = 'invoice_settings';

	/**
	 * Order object to generate the line items.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Payment
	 */
	protected $payment;

	/**
	 * The invoice attributes.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $attributes;

	/**
	 * Instance of the printer. For now, we use mPDF.
	 *
	 * @since 2.0.0
	 * @var Mpdf
	 */
	protected $printer;

	/**
	 * Constructs the invoice object.
	 *
	 * @param \WP_Ultimo\Models\Payment $payment The payment.
	 * @param array                     $atts Attributes to make available on template.
	 */
	public function __construct($payment, $atts = []) {

		$this->set_payment($payment);

		$saved_atts = self::get_settings();

		$atts = array_merge($saved_atts, $atts);

		$this->set_attributes($atts);
	}

	/**
	 * Magic getter for attributes.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The attribute name to get.
	 * @return mixed
	 */
	public function __get($key) {

		return $this->attributes[ $key ] ?? '';
	}

	/**
	 * Returns an exception message that is safe to write to the logs.
	 *
	 * Strips HTML tags and removes the plugin and WordPress directory prefixes,
	 * since mPDF font errors can include absolute filesystem paths.
	 *
	 * @since 2.18.0
	 *
	 * @param \Throwable $e The exception thrown while generating the PDF.
	 * @return string
	 */
	public static function get_loggable_error(\Throwable $e): string {

		$message = wp_strip_all_tags(str_replace([WP_ULTIMO_PLUGIN_DIR, ABSPATH], '', $e->getMessage()));

		// Runtime_Exception messages are written by the plugin and are meant to be read as-is.
		if ($e instanceof Runtime_Exception) {
			return $message;
		}

		return get_class($e) . ': ' . $message;
	}

	/**
	 * Returns the scripts in the given HTML for which no font is available.
	 *
	 * The scripts are detected with mPDF's own script database, and the font
	 * is the one mPDF would select for that script (including its Vietnamese
	 * and Arabic-script language rules), so the check follows what mPDF will
	 * really try to load. A script is unsupported when that font file is not
	 * on disk, for example because scripts/remove-mpdf-fonts.php stripped it.
	 * Scripts that use core PDF fonts need no font file and are not reported.
	 * Neither are Chinese, Japanese and Korean: with the '+aCJK' mode mPDF
	 * references its built-in Adobe CJK fonts (gb, big5, sjis and uhc), which
	 * are not files and are not embedded, so viewers substitute a system font.
	 *
	 * @since 2.18.0
	 *
	 * @param string $html The HTML (or plain text) that is about to be rendered.
	 * @return string[] Human readable names of the unsupported scripts, e.g. "Hebrew".
	 */
	public function get_unsupported_scripts(string $html): array {

		$text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if ( ! preg_match_all('/[^\x00-\x7F]/u', $text, $matches)) {
			return [];
		}

		if (null === $this->printer) {
			$this->pdf_setup();
		}

		$script_to_language = new ScriptToLanguage();
		$language_to_font   = new LanguageToFont();

		// Language codes mPDF assigns to the Arabic-script and Vietnamese text it detects.
		$delimiter_languages = [
			'sindhi'  => 'sd',
			'urdu'    => 'ur',
			'pashto'  => 'ps',
			'persian' => 'fa',
			'viet'    => 'vi',
		];

		$unsupported = [];

		$scripts = [];

		foreach (array_unique($matches[0]) as $char) {
			$scripts[ Ucdn::get_script(mb_ord($char, 'UTF-8')) ] = true;
		}

		foreach (array_keys($scripts) as $script) {
			if (in_array($script, [Ucdn::SCRIPT_COMMON, Ucdn::SCRIPT_INHERITED, Ucdn::SCRIPT_UNKNOWN], true)) {
				continue;
			}

			$label = $this->get_script_label($script);

			$languages = [$label => $script_to_language->getLanguageByScript($script)];

			foreach ($delimiter_languages as $key => $language) {
				$is_arabic_language = Ucdn::SCRIPT_ARABIC === $script && 'viet' !== $key;
				$is_latin_language  = Ucdn::SCRIPT_LATIN === $script && 'viet' === $key;

				$delimiters = $script_to_language->getLanguageDelimiters($key);

				if (($is_arabic_language || $is_latin_language) && preg_match('/[' . $delimiters . ']/u', $text)) {
					$languages[ ucfirst($key) ] = $language;
				}
			}

			foreach ($languages as $language_label => $language) {
				if ( ! $language) {
					continue;
				}

				[, $unifont] = $language_to_font->getLanguageOptions($language, $this->printer->useAdobeCJK);

				// mPDF always treats its built-in Adobe CJK fonts as available (see Mpdf::setCSS()).
				if ('' !== $unifont && ! in_array($unifont, ['sjis', 'uhc', 'big5', 'gb'], true) && ! $this->is_font_available($unifont)) {
					$unsupported[ $language_label ] = $language_label;
				}
			}
		}

		return array_values($unsupported);
	}

	/**
	 * Returns a human readable name for an mPDF script constant.
	 *
	 * @since 2.18.0
	 *
	 * @param int $script One of the Ucdn::SCRIPT_* values.
	 * @return string
	 */
	private function get_script_label(int $script): string {

		foreach ((new \ReflectionClass(Ucdn::class))->getConstants() as $name => $value) {
			if (0 === strpos($name, 'SCRIPT_') && $value === $script) {
				return ucwords(strtolower(str_replace('_', ' ', substr($name, 7))));
			}
		}

		return __('Unknown', 'ultimate-multisite');
	}

	/**
	 * Checks whether the regular font file of an mPDF font name is on disk.
	 *
	 * @since 2.18.0
	 *
	 * @param string $unifont The mPDF font name, as defined in the fontdata array.
	 * @return bool
	 */
	private function is_font_available(string $unifont): bool {

		$file = $this->printer->fontdata[ $unifont ]['R'] ?? '';

		if ('' === $file) {
			return false;
		}

		$font_dirs = (new ConfigVariables())->getDefaults()['fontDir'];

		foreach ((array) $font_dirs as $font_dir) {
			if (file_exists($font_dir . '/' . $file)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders the invoice and writes it to the printer.
	 *
	 * Fails early, with a message the site owner can act on, when the invoice
	 * contains text in a script that no bundled font can render. Without this
	 * check mPDF fails midway with an opaque "Cannot find TTF TrueType font
	 * file" error.
	 *
	 * @since 2.18.0
	 * @throws Runtime_Exception When the invoice contains scripts without a bundled font.
	 * @return void
	 */
	private function write_document(): void {

		$html = $this->render();

		// Text that mPDF renders outside of the template: the footer and the watermark.
		$unsupported_scripts = $this->get_unsupported_scripts(
			$html . ' ' . $this->footer_message . ' ' . ($this->payment->is_payable() ? '' : $this->paid_tag_text)
		);

		if ($unsupported_scripts) {
			throw new Runtime_Exception(
				esc_html(
					sprintf(
						/* translators: %s: comma-separated list of scripts, e.g. "Hebrew, Thai". */
						__('The invoice contains text the bundled PDF fonts cannot render (%s). Replace those characters to generate the invoice.', 'ultimate-multisite'),
						implode(', ', $unsupported_scripts)
					)
				)
			);
		}

		$this->printer->WriteHTML($html);
	}

	/**
	 * Returns mPDF fontdata overrides for fonts removed to reduce package size.
	 *
	 * The composer post-install script (scripts/remove-mpdf-fonts.php) strips
	 * several font files from vendor/mpdf/mpdf/ttfonts/ to reduce the plugin's
	 * distribution size. mPDF's default font configuration still references
	 * these removed files, so any HTML that triggers a monospace font lookup
	 * (e.g. <code>, <pre>, or CSS font-family: monospace) throws:
	 *
	 *   MpdfException: Cannot find TTF TrueType font file "DejaVuSansMono.ttf"
	 *
	 * This method returns fontdata entries that remap the removed DejaVu Mono
	 * variants to FreeMono, which is retained in the ttfonts directory.
	 * FreeMono is a metrically compatible monospace font from the GNU FreeFont
	 * project and is an acceptable substitute for invoice rendering.
	 *
	 * Only the Regular and Bold variants are available; Oblique/BoldOblique
	 * are mapped to their upright counterparts to avoid a second missing-file
	 * exception.
	 *
	 * The full-width DejaVu Sans and DejaVu Serif files were stripped too, but
	 * mPDF still selects them for some scripts (for example 'dejavusans' for
	 * Armenian and Georgian). They are remapped to the retained condensed
	 * variants, which have the same glyph coverage, so those scripts render
	 * without shipping any additional font file.
	 *
	 * Garuda (Thai) is packaged without its two oblique files, so its italic
	 * styles are remapped to the upright files. Fonts that mPDF defines with
	 * only some styles (for example Dhyana, Khmer OS) need no remap: mPDF
	 * simulates the missing bold or italic style from the regular file.
	 *
	 * @since 2.3.2
	 * @return array
	 */
	private function get_missing_fontdata(): array {

		return [
			'dejavusansmono' => [
				'R'  => 'FreeMono.ttf',
				'B'  => 'FreeMonoBold.ttf',
				'I'  => 'FreeMono.ttf',
				'BI' => 'FreeMonoBold.ttf',
			],
			'dejavusans'     => [
				'R'  => 'DejaVuSansCondensed.ttf',
				'B'  => 'DejaVuSansCondensed-Bold.ttf',
				'I'  => 'DejaVuSansCondensed.ttf',
				'BI' => 'DejaVuSansCondensed-Bold.ttf',
			],
			'dejavuserif'    => [
				'R'  => 'DejaVuSerifCondensed.ttf',
				'B'  => 'DejaVuSerifCondensed-Bold.ttf',
				'I'  => 'DejaVuSerifCondensed.ttf',
				'BI' => 'DejaVuSerifCondensed-Bold.ttf',
			],
			// Thai. The oblique files are not packaged, so italics use the upright files.
			'garuda'         => [
				'R'      => 'Garuda.ttf',
				'B'      => 'Garuda-Bold.ttf',
				'I'      => 'Garuda.ttf',
				'BI'     => 'Garuda-Bold.ttf',
				'useOTL' => 0xFF,
			],
		];
	}

	/**
	 * Returns the mPDF fontdata configuration for bundled RTL fonts.
	 *
	 * The built-in mPDF Arabic language-to-font mapping maps 'ar', 'fa', 'ur'
	 * etc. to 'xbriyaz'. The XB Riyaz font files are removed by the composer
	 * post-install script to reduce package size, so 'xbriyaz' is remapped to
	 * DejaVu Sans Condensed, which is retained and covers the Arabic block.
	 *
	 * The useOTL flag enables Arabic letter-joining (shaping) and the
	 * useKashida flag enables kashida-based text justification. Without these
	 * flags Arabic letters render as isolated glyphs with spaces between them
	 * instead of connected words.
	 *
	 * The previously bundled Amiri font was dropped: its GSUB table uses
	 * contextual substitution lookups (type 5, format 3) that mPDF cannot
	 * parse, which threw a FontException for any Arabic text.
	 *
	 * Only the Regular and Bold files are retained, so Italic variants map to
	 * their upright counterparts.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	private function get_rtl_fontdata(): array {

		return [
			'xbriyaz' => [
				'R'          => 'DejaVuSansCondensed.ttf',
				'B'          => 'DejaVuSansCondensed-Bold.ttf',
				'I'          => 'DejaVuSansCondensed.ttf',
				'BI'         => 'DejaVuSansCondensed-Bold.ttf',
				'useOTL'     => 0xFF,
				'useKashida' => 75,
			],
		];
	}

	/**
	 * Setups the printer object. Uses mPdf.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function pdf_setup(): void {

		$this->printer = new Mpdf(
			[
				'mode'             => '+aCJK',
				'autoScriptToLang' => true,
				'autoLangToFont'   => true,
				'autoArabic'       => true,
				'tempDir'          => get_temp_dir(),
			]
		);

		/*
		 * Register the Arabic/RTL font mapping.
		 *
		 * The fontdata entry is merged into $this->fontdata so mPDF can locate
		 * the retained DejaVu Sans Condensed files under the 'xbriyaz' name
		 * when autoLangToFont selects it for Arabic script. These files live in
		 * the default mPDF ttfonts directory, which is already on the search path.
		 *
		 * useOTL => 0xFF enables OpenType Layout features (required for Arabic
		 * letter-joining/shaping). Without it, Arabic letters render as
		 * isolated glyphs with spaces between them instead of connected words.
		 * useKashida => 75 enables kashida-based text justification.
		 */
		foreach ($this->get_rtl_fontdata() as $font_name => $font_config) {
			$this->printer->fontdata[ $font_name ] = $font_config;

			if ( ! empty($font_config['R'])) {
				$this->printer->available_unifonts[] = $font_name;
			}

			if ( ! empty($font_config['B'])) {
				$this->printer->available_unifonts[] = $font_name . 'B';
			}

			if ( ! empty($font_config['I'])) {
				$this->printer->available_unifonts[] = $font_name . 'I';
			}

			if ( ! empty($font_config['BI'])) {
				$this->printer->available_unifonts[] = $font_name . 'BI';
			}
		}

		/*
		 * Override fontdata for fonts removed from the ttfonts directory.
		 *
		 * DejaVuSansMono and its variants were stripped to reduce package size.
		 * Remapping them to FreeMono (which is retained) prevents the
		 * MpdfException thrown when mPDF tries to load the missing files.
		 * No AddFontDirectory() call is needed — FreeMono lives in the default
		 * mPDF ttfonts directory that is already on the search path.
		 */
		foreach ($this->get_missing_fontdata() as $font_name => $font_config) {
			$this->printer->fontdata[ $font_name ] = $font_config;
		}

		$this->printer->curlFollowLocation = true;

		$this->printer->setDefaultFont($this->font);

		$this->printer->SetProtection(['print']);

		$this->printer->SetTitle(__('Invoice', 'ultimate-multisite'));

		$this->printer->SetAuthor($this->company_name);

		if ( ! $this->payment->is_payable()) {
			$this->printer->SetWatermarkText($this->paid_tag_text);
		}

		$this->printer->showWatermarkText = true;

		$this->printer->watermarkTextAlpha = 0.1;

		$this->printer->watermark_font = $this->font;

		$this->printer->SetDisplayMode('fullpage');

		if ($this->footer_message) {
			$this->printer->SetHTMLFooter($this->footer_message);
		}
	}

	/**
	 * Saves the PDF file to the disk.
	 *
	 * @since 2.0.0
	 *
	 * @param boolean $file_name The name of the file. Should include the .pdf extension.
	 * @return void
	 */
	public function save_file($file_name): void {

		$file_name = self::get_folder() . $file_name;

		$this->pdf($file_name);
	}

	/**
	 * Prints the PDF file to the browser.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function print_file(): void {

		$this->pdf();
	}

	/**
	 * Generates the HTML content of the Invoice template.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function render() {

		$atts = $this->attributes;

		$atts['payment'] = $this->payment;

		$atts['line_items'] = $this->payment->get_line_items();

		$atts['membership'] = $this->payment->get_membership();

		$atts['billing_address'] = $atts['membership'] ? $atts['membership']->get_billing_address()->to_array() : [];

		wp_enqueue_style('wu-invoice', wu_get_asset('invoice.css', 'css'), [], \WP_Ultimo::VERSION);

		return wu_get_template_contents('invoice/template', $atts);
	}

	/**
	 * Returns the PDF content as a string.
	 *
	 * @since 2.3.0
	 * @return string
	 */
	public function get_content(): string {

		wu_setup_memory_limit_trap();

		wu_try_unlimited_server_limits();

		$this->pdf_setup();

		if ( ! defined('WU_GENERATING_PDF')) {
			define('WU_GENERATING_PDF', true);
		}

		$this->write_document();

		return $this->printer->Output('', Destination::STRING_RETURN);
	}

	/**
	 * Handles the PDF generation.
	 *
	 * @since 2.0.0
	 *
	 * @param string|false $file_name The file name, to save. Empty or false to print to the browser.
	 * @return void
	 */
	protected function pdf($file_name = false) {

		wu_setup_memory_limit_trap();

		wu_try_unlimited_server_limits();

		$this->pdf_setup();

		// Define constant to indicate PDF generation context for templates
		if ( ! defined('WU_GENERATING_PDF')) {
			define('WU_GENERATING_PDF', true);
		}

		$this->write_document();

		if ($file_name) {
			$this->printer->Output($file_name, Destination::FILE);
		} else {
			$this->printer->Output();
		}
	}

	/**
	 * Get the value of payment.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function get_payment() {

		return $this->payment;
	}

	/**
	 * Set the value of payment.
	 *
	 * @since 2.0.0
	 * @param mixed $payment The Order object to add to the invoice.
	 * @return void
	 */
	public function set_payment($payment): void {

		$this->payment = $payment;
	}

	/**
	 * Get the value of attributes.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function get_attributes() {

		return $this->attributes;
	}

	/**
	 * Set the value of attributes.
	 *
	 * @since 2.0.0
	 * @param mixed $attributes The list of attributes to add to the invoice.
	 * @return void
	 */
	public function set_attributes($attributes): void {

		$attributes = wp_parse_args(
			$attributes,
			[
				'company_name'    => wu_get_setting('company_name', get_network_option(null, 'site_name')),
				'company_address' => wu_get_setting('company_address', ''),
				'primary_color'   => '#675645',
				'font'            => 'DejaVuSansCondensed',
				'logo_url'        => wu_get_network_logo(),
				'use_custom_logo' => false,
				'custom_logo'     => false,
				'footer_message'  => '',
				'paid_tag_text'   => __('Paid', 'ultimate-multisite'),
			]
		);

		$this->attributes = $attributes;
	}

	/**
	 * Generates the folder to keep invoices and returns the path.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_folder() {

		return wu_maybe_create_folder('wu-invoices');
	}

	/**
	 * Returns the list of saved settings to customize the invoices..
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function get_settings() {

		return wu_get_option(self::KEY, []);
	}

	/**
	 * Save settings.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings_to_save List of settings to save.
	 * @return boolean
	 */
	public static function save_settings($settings_to_save) {

		$invoice = new self(false);

		$allowed_keys = array_keys($invoice->get_attributes());

		foreach ($settings_to_save as $setting_to_save => $value) {
			if ( ! in_array($setting_to_save, $allowed_keys, true)) {
				unset($settings_to_save[ $setting_to_save ]);
			}
		}

		return wu_save_option(self::KEY, $settings_to_save);
	}
}
