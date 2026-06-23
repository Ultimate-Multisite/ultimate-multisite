<?php
/**
 * Tests for Template_Repository class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Template_Library;

use WP_UnitTestCase;

/**
 * Test class for Template_Repository.
 */
class Template_Repository_Test extends WP_UnitTestCase {

	/**
	 * @var Template_Repository
	 */
	private $repository;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->repository = new Template_Repository();

		$reflection = new \ReflectionClass($this->repository);
		$templates  = $reflection->getProperty('templates');

		$templates->setValue(
			$this->repository,
			[
				[
					'slug'          => 'test-template',
					'name'          => 'Test Template',
					'description'   => 'Template used by repository tests.',
					'industry_type' => 'test',
					'categories'    => [
						[
							'slug' => 'test-category',
							'name' => 'Test Category',
						],
					],
				],
			]
		);
	}

	/**
	 * Test get_installer returns a Template_Installer instance.
	 */
	public function test_get_installer_returns_instance(): void {
		$installer = $this->repository->get_installer();

		$this->assertInstanceOf(Template_Installer::class, $installer);
	}

	/**
	 * Test get_api_client returns an API_Client instance.
	 */
	public function test_get_api_client_returns_instance(): void {
		$client = $this->repository->get_api_client();

		$this->assertInstanceOf(API_Client::class, $client);
	}

	/**
	 * Test get_template returns WP_Error for unknown slug.
	 */
	public function test_get_template_returns_error_for_unknown(): void {
		$result = $this->repository->get_template('nonexistent-template-slug-xyz');

		$this->assertWPError($result);
		$this->assertSame('template_not_found', $result->get_error_code());
	}

	/**
	 * Test get_templates_by_category returns array.
	 */
	public function test_get_templates_by_category_returns_array(): void {
		$result = $this->repository->get_templates_by_category('nonexistent-category');

		$this->assertIsArray($result);
	}

	/**
	 * Test search_templates returns array.
	 */
	public function test_search_templates_returns_array(): void {
		$result = $this->repository->search_templates('test');

		$this->assertIsArray($result);
	}

	/**
	 * Test get_categories returns array.
	 */
	public function test_get_categories_returns_array(): void {
		$result = $this->repository->get_categories();

		$this->assertIsArray($result);
	}

	/**
	 * Test clear_cache returns bool.
	 */
	public function test_clear_cache_returns_bool(): void {
		$result = $this->repository->clear_cache();

		$this->assertIsBool($result);
	}
}
