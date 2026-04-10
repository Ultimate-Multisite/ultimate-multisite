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

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
	 * Returns the path to the bundled RTL fonts directory.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function get_rtl_font_dir(): string {

		return WP_ULTIMO_PLUGIN_DIR . 'assets/fonts/amiri/';
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
		];
	}

	/**
	 * Returns the mPDF fontdata configuration for bundled RTL fonts.
	 *
	 * Amiri is an Arabic Naskh-style font with full OpenType Layout (OTL)
	 * support. The useOTL flag enables Arabic letter-joining (shaping) and
	 * the useKashida flag enables kashida-based text justification.
	 * Without these flags Arabic letters render as isolated glyphs with
	 * spaces between them instead of connected words.
	 *
	 * The font is registered under both 'amiri' (for explicit use) and
	 * 'xbriyaz' (to satisfy mPDF's built-in Arabic language-to-font mapping,
	 * which maps 'ar', 'fa', 'ur' etc. to 'xbriyaz'). The XB Riyaz font
	 * files are removed by the composer post-install script to reduce package
	 * size; Amiri serves as a drop-in replacement with superior Arabic shaping.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	private function get_rtl_fontdata(): array {

		$amiri = [
			'R'          => 'Amiri-Regular.ttf',
			'B'          => 'Amiri-Bold.ttf',
			'I'          => 'Amiri-Italic.ttf',
			'BI'         => 'Amiri-BoldItalic.ttf',
			'useOTL'     => 0xFF,
			'useKashida' => 75,
		];

		return [
			'amiri'   => $amiri,
			'xbriyaz' => $amiri,
		];
	}

	/**
	 * Setups the printer object. Uses mPdf.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function pdf_setup(): void {

		$this->printer                     = new Mpdf(
			[
				'mode'             => '+aCJK',
				'autoScriptToLang' => true,
				'autoLangToFont'   => true,
				'autoArabic'       => true,
				'tempDir'          => get_temp_dir(),
			]
		);

		/*
		 * Register the bundled Amiri font for Arabic/RTL support.
		 *
		 * AddFontDirectory() appends to the existing font search path so the
		 * default mPDF fonts (DejaVu, etc.) remain available. The fontdata
		 * entry is merged into $this->fontdata so mPDF can locate Amiri by
		 * name when autoLangToFont selects it for Arabic script.
		 *
		 * useOTL => 0xFF enables OpenType Layout features (required for Arabic
		 * letter-joining/shaping). Without it, Arabic letters render as
		 * isolated glyphs with spaces between them instead of connected words.
		 * useKashida => 75 enables kashida-based text justification.
		 */
		$this->printer->AddFontDirectory($this->get_rtl_font_dir());

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

		$this->printer->WriteHTML($this->render());

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

		$this->printer->WriteHTML($this->render());

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
