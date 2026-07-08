<?php
/**
 * Test case for Invoice PDF generation.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Invoices;

use WP_Ultimo\Invoices\Invoice;
use WP_Ultimo\Models\Payment;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Checkout\Line_Item;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_UnitTestCase;

/**
 * Test Invoice PDF generation functionality.
 */
class Invoice_Test extends WP_UnitTestCase {

	/**
	 * Shared customer for tests.
	 *
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Create shared fixtures.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$username = 'invoice_test_' . wp_rand(1000, 9999);

		$customer = wu_create_customer(
			[
				'username' => $username,
				'email'    => $username . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($customer)) {
			// Fallback: get any existing customer
			$customers = \WP_Ultimo\Models\Customer::query(['number' => 1]);
			$customer  = $customers[0] ?? wu_create_customer([
				'username' => 'inv_fallback_' . wp_rand(),
				'email'    => 'inv_fallback_' . wp_rand() . '@example.com',
				'password' => 'password123',
			]);
		}

		self::$customer = $customer;
	}

	/**
	 * Create a payment with line items for testing.
	 *
	 * @param array $overrides Payment attribute overrides.
	 * @return Payment
	 */
	private function create_test_payment(array $overrides = []): Payment {

		$payment = new Payment();
		$payment->set_customer_id(self::$customer->get_id());
		$payment->set_currency($overrides['currency'] ?? 'USD');
		$payment->set_subtotal($overrides['subtotal'] ?? 100.00);
		$payment->set_total($overrides['total'] ?? 110.00);
		$payment->set_tax_total($overrides['tax_total'] ?? 10.00);
		$payment->set_status($overrides['status'] ?? Payment_Status::COMPLETED);
		$payment->set_gateway($overrides['gateway'] ?? 'manual');

		$line_items = $overrides['line_items'] ?? [
			new Line_Item(
				[
					'type'        => 'product',
					'hash'        => 'test_plan',
					'title'       => 'Test Plan',
					'description' => 'Monthly hosting plan',
					'unit_price'  => 100.00,
					'quantity'    => 1,
					'taxable'     => true,
					'tax_rate'    => 10.00,
				]
			),
		];

		$payment->set_line_items($line_items);

		$payment->save();

		return $payment;
	}

	/**
	 * Test Invoice class can be instantiated with a payment.
	 */
	public function test_invoice_instantiation() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$this->assertInstanceOf(Invoice::class, $invoice);
		$this->assertSame($payment, $invoice->get_payment());
	}

	/**
	 * Test Invoice has default attributes.
	 */
	public function test_invoice_has_default_attributes() {

		$payment    = $this->create_test_payment();
		$invoice    = new Invoice($payment);
		$attributes = $invoice->get_attributes();

		$this->assertArrayHasKey('company_name', $attributes);
		$this->assertArrayHasKey('company_address', $attributes);
		$this->assertArrayHasKey('primary_color', $attributes);
		$this->assertArrayHasKey('font', $attributes);
		$this->assertArrayHasKey('logo_url', $attributes);
		$this->assertArrayHasKey('use_custom_logo', $attributes);
		$this->assertArrayHasKey('custom_logo', $attributes);
		$this->assertArrayHasKey('footer_message', $attributes);
		$this->assertArrayHasKey('paid_tag_text', $attributes);
	}

	/**
	 * Test Invoice default font is DejaVuSansCondensed.
	 */
	public function test_invoice_default_font() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$this->assertEquals('DejaVuSansCondensed', $invoice->font);
	}

	/**
	 * Test Invoice default primary color.
	 */
	public function test_invoice_default_primary_color() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$this->assertEquals('#675645', $invoice->primary_color);
	}

	/**
	 * Test custom attributes override defaults.
	 */
	public function test_custom_attributes_override_defaults() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment, [
			'company_name'  => 'Test Company',
			'primary_color' => '#ff0000',
			'font'          => 'DejaVuSerifCondensed',
		]);

		$this->assertEquals('Test Company', $invoice->company_name);
		$this->assertEquals('#ff0000', $invoice->primary_color);
		$this->assertEquals('DejaVuSerifCondensed', $invoice->font);
	}

	/**
	 * Test magic getter returns empty string for unknown attributes.
	 */
	public function test_magic_getter_returns_empty_for_unknown() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$this->assertEquals('', $invoice->nonexistent_attribute);
	}

	/**
	 * Test Invoice render method generates HTML.
	 */
	public function test_invoice_render_generates_html() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertIsString($html);
		$this->assertNotEmpty($html);
	}

	/**
	 * Test rendered HTML contains invoice structure.
	 */
	public function test_render_contains_invoice_structure() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment, ['company_name' => 'Acme Corp']);

		$html = $invoice->render();

		$this->assertStringContainsString('invoice-box', $html);
		$this->assertStringContainsString('Acme Corp', $html);
	}

	/**
	 * Test rendered HTML contains line item data.
	 */
	public function test_render_contains_line_items() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Test Plan', $html);
	}

	/**
	 * Test rendered HTML contains payment total.
	 */
	public function test_render_contains_payment_total() {

		$payment = $this->create_test_payment([
			'total'    => 110.00,
			'currency' => 'USD',
		]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('110', $html);
	}

	/**
	 * Test render with multiple line items.
	 */
	public function test_render_with_multiple_line_items() {

		$line_items = [
			new Line_Item([
				'type'       => 'product',
				'hash'       => 'plan_1',
				'title'      => 'Basic Plan',
				'unit_price' => 50.00,
				'quantity'   => 1,
				'taxable'    => true,
				'tax_rate'   => 10.00,
			]),
			new Line_Item([
				'type'       => 'fee',
				'hash'       => 'setup_fee',
				'title'      => 'Setup Fee',
				'unit_price' => 25.00,
				'quantity'   => 1,
				'taxable'    => false,
				'tax_rate'   => 0,
			]),
		];

		$payment = $this->create_test_payment(['line_items' => $line_items]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Basic Plan', $html);
		$this->assertStringContainsString('Setup Fee', $html);
	}

	/**
	 * Test render with completed payment shows payment method section.
	 */
	public function test_render_completed_payment_shows_payment_method() {

		$payment = $this->create_test_payment(['status' => Payment_Status::COMPLETED]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		// Completed payments show "Payment Method" heading
		$this->assertStringContainsString('Payment Method', $html);
	}

	/**
	 * Test render with pending payment does not show payment method section.
	 */
	public function test_render_pending_payment_hides_payment_method() {

		$payment = $this->create_test_payment(['status' => Payment_Status::PENDING]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		// Pending payments are payable, so no payment method section
		$this->assertStringNotContainsString('Payment Method', $html);
	}

	/**
	 * Test Invoice PDF generation does not fatal error.
	 */
	public function test_pdf_generation_does_not_fatal() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;

		$this->assertFileExists($file_path);

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test generated PDF file is non-empty.
	 */
	public function test_generated_pdf_has_content() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-content-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;

		$this->assertGreaterThan(0, filesize($file_path));

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test generated PDF file starts with PDF header.
	 */
	public function test_generated_pdf_has_valid_header() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-header-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;

		$header = file_get_contents($file_path, false, null, 0, 5);
		$this->assertEquals('%PDF-', $header);

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test PDF generation with EUR currency.
	 */
	public function test_pdf_generation_with_eur_currency() {

		$payment = $this->create_test_payment([
			'currency'  => 'EUR',
			'total'     => 58.31,
			'subtotal'  => 49.00,
			'tax_total' => 9.31,
		]);
		$invoice = new Invoice($payment);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-eur-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;
		$this->assertFileExists($file_path);
		$this->assertGreaterThan(0, filesize($file_path));

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test PDF generation with zero-amount payment.
	 */
	public function test_pdf_generation_with_zero_amount() {

		$line_items = [
			new Line_Item([
				'type'       => 'product',
				'hash'       => 'free_plan',
				'title'      => 'Free Plan',
				'unit_price' => 0,
				'quantity'   => 1,
				'taxable'    => false,
				'tax_rate'   => 0,
			]),
		];

		$payment = $this->create_test_payment([
			'total'      => 0,
			'subtotal'   => 0,
			'tax_total'  => 0,
			'line_items' => $line_items,
		]);
		$invoice = new Invoice($payment);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-free-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;
		$this->assertFileExists($file_path);

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test PDF generation with custom company info.
	 */
	public function test_pdf_generation_with_custom_company_info() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment, [
			'company_name'    => 'HogaCloud GmbH',
			'company_address' => "Musterstraße 123\n69168 Wiesloch\nDeutschland",
			'primary_color'   => '#003366',
		]);

		$html = $invoice->render();

		$this->assertStringContainsString('HogaCloud GmbH', $html);
		$this->assertStringContainsString('69168 Wiesloch', $html);
	}

	/**
	 * Test PDF generation with footer message.
	 */
	public function test_pdf_generation_with_footer() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment, [
			'footer_message' => 'Thank you for your business!',
		]);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-footer-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;
		$this->assertFileExists($file_path);

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test Invoice settings save and retrieve.
	 */
	public function test_settings_save_and_retrieve() {

		$settings = [
			'company_name'  => 'Test Corp',
			'primary_color' => '#123456',
			'font'          => 'FreeMono',
		];

		Invoice::save_settings($settings);

		$saved = Invoice::get_settings();

		$this->assertEquals('Test Corp', $saved['company_name']);
		$this->assertEquals('#123456', $saved['primary_color']);
		$this->assertEquals('FreeMono', $saved['font']);
	}

	/**
	 * Test save_settings filters out unknown keys.
	 */
	public function test_save_settings_filters_unknown_keys() {

		$settings = [
			'company_name' => 'Test Corp',
			'evil_key'     => 'should be filtered',
		];

		Invoice::save_settings($settings);

		$saved = Invoice::get_settings();

		$this->assertArrayNotHasKey('evil_key', $saved);
	}

	/**
	 * Test Invoice get_folder creates directory.
	 */
	public function test_get_folder_creates_directory() {

		$folder = Invoice::get_folder();

		$this->assertNotEmpty($folder);
		$this->assertDirectoryExists($folder);
	}

	/**
	 * Test set_payment and get_payment.
	 */
	public function test_set_and_get_payment() {

		$payment1 = $this->create_test_payment();
		$payment2 = $this->create_test_payment(['total' => 200.00]);

		$invoice = new Invoice($payment1);

		$this->assertSame($payment1, $invoice->get_payment());

		$invoice->set_payment($payment2);

		$this->assertSame($payment2, $invoice->get_payment());
	}

	/**
	 * Test set_attributes and get_attributes.
	 */
	public function test_set_and_get_attributes() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$new_atts = [
			'company_name'  => 'Updated Corp',
			'primary_color' => '#000000',
		];

		$invoice->set_attributes($new_atts);

		$attributes = $invoice->get_attributes();

		$this->assertEquals('Updated Corp', $attributes['company_name']);
		$this->assertEquals('#000000', $attributes['primary_color']);
		// Default values should still be present
		$this->assertArrayHasKey('font', $attributes);
	}

	/**
	 * Test rendering with payment that has no line items.
	 */
	public function test_render_with_no_line_items() {

		$payment = $this->create_test_payment(['line_items' => []]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertIsString($html);
		$this->assertStringContainsString('invoice-box', $html);
	}

	/**
	 * Test rendering with payment that has no membership.
	 */
	public function test_render_with_no_membership() {

		$payment = $this->create_test_payment();
		// Payment is not associated with a membership
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertIsString($html);
		$this->assertStringContainsString('invoice-box', $html);
	}

	/**
	 * Test rendering with special characters in company name.
	 */
	public function test_render_with_special_characters() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment, [
			'company_name'    => 'Müller & Söhne GmbH',
			'company_address' => "Königstraße 42\nBerlin",
		]);

		$html = $invoice->render();

		$this->assertStringContainsString('Müller', $html);
		$this->assertStringContainsString('Söhne', $html);
	}

	/**
	 * Test PDF generation with special characters does not crash MPDF.
	 */
	public function test_pdf_with_special_characters() {

		$line_items = [
			new Line_Item([
				'type'        => 'product',
				'hash'        => 'special_plan',
				'title'       => 'Ünïcödé Plän — Special Édition',
				'description' => 'Lörem ïpsum dölor sït ämet',
				'unit_price'  => 49.00,
				'quantity'    => 1,
				'taxable'     => true,
				'tax_rate'    => 19.00,
			]),
		];

		$payment = $this->create_test_payment([
			'currency'   => 'EUR',
			'line_items' => $line_items,
		]);

		$invoice = new Invoice($payment, [
			'company_name'    => 'Ünïcödé Tëst GmbH',
			'company_address' => "Königstraße 42\n10117 Berlin\nDeutschland",
		]);

		$folder    = Invoice::get_folder();
		$file_name = 'test-invoice-unicode-' . time() . '.pdf';

		$invoice->save_file($file_name);

		$file_path = $folder . $file_name;
		$this->assertFileExists($file_path);

		$header = file_get_contents($file_path, false, null, 0, 5);
		$this->assertEquals('%PDF-', $header);

		// Clean up
		if (file_exists($file_path)) {
			unlink($file_path);
		}
	}

	/**
	 * Test Invoice render contains Bill To section.
	 */
	public function test_render_contains_bill_to_section() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Bill to', $html);
	}

	/**
	 * Test Invoice render contains Invoice number.
	 */
	public function test_render_contains_invoice_number() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Invoice #', $html);
	}

	/**
	 * Test render includes tax information.
	 */
	public function test_render_includes_tax_column() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Tax', $html);
	}

	/**
	 * Test render includes discount column.
	 */
	public function test_render_includes_discount_column() {

		$payment = $this->create_test_payment();
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Discount', $html);
	}

	/**
	 * Test render with tax-inclusive line item shows note.
	 */
	public function test_render_with_tax_inclusive_shows_note() {

		$line_items = [
			new Line_Item([
				'type'          => 'product',
				'hash'          => 'inclusive_plan',
				'title'         => 'Tax Inclusive Plan',
				'unit_price'    => 119.00,
				'quantity'      => 1,
				'taxable'       => true,
				'tax_rate'      => 19.00,
				'tax_inclusive' => true,
			]),
		];

		$payment = $this->create_test_payment(['line_items' => $line_items]);
		$invoice = new Invoice($payment);

		$html = $invoice->render();

		$this->assertStringContainsString('Tax included in price', $html);
	}
}
