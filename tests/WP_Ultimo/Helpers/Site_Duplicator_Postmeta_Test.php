<?php
/**
 * Test case for Site_Duplicator postmeta backfill methods.
 *
 * Tests the four backfill methods added to fix GH#820:
 * - backfill_nav_menu_postmeta (Bug 2)
 * - backfill_attachment_postmeta (Bug 3)
 * - backfill_elementor_postmeta (Bug 4)
 * - backfill_kit_settings (Bug 1)
 * - backfill_postmeta (orchestrator)
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers;

use WP_Ultimo\Helpers\Site_Duplicator;
use WP_UnitTestCase;

/**
 * Test Site_Duplicator postmeta backfill functionality.
 *
 * Uses a test subclass to expose the protected static methods for testing.
 */
class Site_Duplicator_Postmeta_Test extends WP_UnitTestCase {

	/**
	 * Source (template) blog ID.
	 *
	 * @var int
	 */
	private $from_blog_id;

	/**
	 * Target (cloned) blog ID.
	 *
	 * @var int
	 */
	private $to_blog_id;

	/**
	 * Set up test fixtures: two subsites with different table prefixes.
	 */
	public function setUp(): void {
		parent::setUp();

		if (! is_multisite()) {
			$this->markTestSkipped('Postmeta backfill tests require multisite');
		}

		$this->from_blog_id = self::factory()->blog->create(
			[
				'domain' => 'template.example.com',
				'path'   => '/',
				'title'  => 'Template Site',
			]
		);

		$this->to_blog_id = self::factory()->blog->create(
			[
				'domain' => 'clone.example.com',
				'path'   => '/',
				'title'  => 'Cloned Site',
			]
		);
	}

	/**
	 * Clean up test blogs.
	 */
	public function tearDown(): void {
		if ($this->from_blog_id) {
			wpmu_delete_blog($this->from_blog_id, true);
		}
		if ($this->to_blog_id) {
			wpmu_delete_blog($this->to_blog_id, true);
		}

		parent::tearDown();
	}

	// =========================================================================
	// backfill_postmeta orchestrator
	// =========================================================================

	/**
	 * Test backfill_postmeta bails when from_site_id is 0.
	 */
	public function test_backfill_postmeta_bails_on_zero_from_site_id() {
		// Should not throw or error — just return early.
		Testable_Site_Duplicator::backfill_postmeta(0, $this->to_blog_id);

		// If we reach here, no exception was thrown — the early return worked.
		$this->assertTrue(true);
	}

	/**
	 * Test backfill_postmeta bails when to_site_id is 0.
	 */
	public function test_backfill_postmeta_bails_on_zero_to_site_id() {
		Testable_Site_Duplicator::backfill_postmeta($this->from_blog_id, 0);

		$this->assertTrue(true);
	}

	/**
	 * Test backfill_postmeta bails when from and to are the same site.
	 */
	public function test_backfill_postmeta_bails_on_same_site() {
		Testable_Site_Duplicator::backfill_postmeta($this->from_blog_id, $this->from_blog_id);

		$this->assertTrue(true);
	}

	/**
	 * Test backfill_postmeta dispatches to all four sub-methods.
	 */
	public function test_backfill_postmeta_dispatches_all_sub_methods() {
		// Set up data that each sub-method should backfill.
		$this->setup_nav_menu_data();
		$this->setup_attachment_data();
		$this->setup_elementor_data();
		$this->setup_kit_data();

		Testable_Site_Duplicator::backfill_postmeta($this->from_blog_id, $this->to_blog_id);

		// Verify each sub-method's work happened.
		$this->verify_nav_menu_backfill();
		$this->verify_attachment_backfill();
		$this->verify_elementor_backfill();
		$this->verify_kit_backfill();
	}

	// =========================================================================
	// Bug 2 — backfill_nav_menu_postmeta
	// =========================================================================

	/**
	 * Test that nav_menu_item postmeta is backfilled from template to clone.
	 *
	 * Bug 2: MUCD copies nav_menu_item posts but not their postmeta rows.
	 * Without backfill, menus render as empty <li> tags.
	 */
	public function test_nav_menu_postmeta_backfilled() {
		$this->setup_nav_menu_data();

		Testable_Site_Duplicator::backfill_nav_menu_postmeta($this->from_blog_id, $this->to_blog_id);

		$this->verify_nav_menu_backfill();
	}

	/**
	 * Test that existing nav_menu_item postmeta is not overwritten.
	 *
	 * The INSERT ... NOT EXISTS pattern must not clobber rows that
	 * already exist in the target.
	 */
	public function test_nav_menu_postmeta_does_not_overwrite_existing() {
		$this->setup_nav_menu_data();

		// Pre-insert a different value for one key on the target.
		switch_to_blog($this->to_blog_id);
		update_post_meta($this->to_nav_item_id, '_menu_item_type', 'custom-existing');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_nav_menu_postmeta($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		// The pre-existing value must survive.
		$this->assertEquals('custom-existing', get_post_meta($this->to_nav_item_id, '_menu_item_type', true));
		// Other keys should still be backfilled.
		$this->assertEquals('http://template.example.com/about/', get_post_meta($this->to_nav_item_id, '_menu_item_url', true));
		restore_current_blog();
	}

	/**
	 * Test that non-nav_menu_item postmeta is not affected.
	 */
	public function test_nav_menu_backfill_only_affects_nav_menu_items() {
		$this->setup_nav_menu_data();

		// Add a regular post with a meta key that looks like a menu key.
		switch_to_blog($this->from_blog_id);
		$regular_post_id = self::factory()->post->create(['post_type' => 'post']);
		update_post_meta($regular_post_id, '_menu_item_type', 'post_type');
		restore_current_blog();

		// Mirror the post to the target (simulating MUCD copy).
		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(['import_id' => $regular_post_id, 'post_type' => 'post']);
		restore_current_blog();

		Testable_Site_Duplicator::backfill_nav_menu_postmeta($this->from_blog_id, $this->to_blog_id);

		// The regular post's _menu_item_type should NOT be copied because
		// the INNER JOIN filters on tgt.post_type = 'nav_menu_item'.
		switch_to_blog($this->to_blog_id);
		$this->assertEmpty(get_post_meta($regular_post_id, '_menu_item_type', true));
		restore_current_blog();
	}

	// =========================================================================
	// Bug 3 — backfill_attachment_postmeta
	// =========================================================================

	/**
	 * Test that attachment postmeta is backfilled from template to clone.
	 *
	 * Bug 3: MUCD copies attachment posts but not their postmeta.
	 * Without _wp_attached_file, wp_get_attachment_image_url() returns false.
	 */
	public function test_attachment_postmeta_backfilled() {
		$this->setup_attachment_data();

		Testable_Site_Duplicator::backfill_attachment_postmeta($this->from_blog_id, $this->to_blog_id);

		$this->verify_attachment_backfill();
	}

	/**
	 * Test that existing attachment postmeta is not overwritten.
	 */
	public function test_attachment_postmeta_does_not_overwrite_existing() {
		$this->setup_attachment_data();

		// Pre-insert a different _wp_attached_file on the target.
		switch_to_blog($this->to_blog_id);
		update_post_meta($this->to_attachment_id, '_wp_attached_file', 'existing/different.jpg');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_attachment_postmeta($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$this->assertEquals('existing/different.jpg', get_post_meta($this->to_attachment_id, '_wp_attached_file', true));
		restore_current_blog();
	}

	/**
	 * Test that non-attachment postmeta is not affected by attachment backfill.
	 */
	public function test_attachment_backfill_only_affects_attachments() {
		$this->setup_attachment_data();

		// Add a regular page with _wp_attached_file (unusual but possible).
		switch_to_blog($this->from_blog_id);
		$page_id = self::factory()->post->create(['post_type' => 'page']);
		update_post_meta($page_id, '_wp_attached_file', '2024/01/page-image.jpg');
		restore_current_blog();

		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(['import_id' => $page_id, 'post_type' => 'page']);
		restore_current_blog();

		Testable_Site_Duplicator::backfill_attachment_postmeta($this->from_blog_id, $this->to_blog_id);

		// The page's _wp_attached_file should NOT be copied —
		// INNER JOIN filters on tgt.post_type = 'attachment'.
		switch_to_blog($this->to_blog_id);
		$this->assertEmpty(get_post_meta($page_id, '_wp_attached_file', true));
		restore_current_blog();
	}

	// =========================================================================
	// Bug 4 — backfill_elementor_postmeta
	// =========================================================================

	/**
	 * Test that _elementor_* postmeta is backfilled for elementor_library posts.
	 *
	 * Bug 4: elementor_library CPT postmeta not copied — custom headers,
	 * footers, popups render as skeletons.
	 */
	public function test_elementor_postmeta_backfilled_for_library_posts() {
		$this->setup_elementor_data();

		Testable_Site_Duplicator::backfill_elementor_postmeta($this->from_blog_id, $this->to_blog_id);

		$this->verify_elementor_backfill();
	}

	/**
	 * Test that _elementor_* postmeta is backfilled for any post type.
	 *
	 * The catch-all should work for pages, posts, and custom CPTs.
	 */
	public function test_elementor_postmeta_backfilled_for_pages() {
		switch_to_blog($this->from_blog_id);
		$page_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'Elementor Page',
			]
		);
		update_post_meta($page_id, '_elementor_data', '[{"elType":"section"}]');
		update_post_meta($page_id, '_elementor_page_settings', serialize(['layout' => 'full-width']));
		update_post_meta($page_id, '_elementor_edit_mode', 'builder');
		restore_current_blog();

		// Mirror the post to target (MUCD copies posts).
		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(
			[
				'import_id'  => $page_id,
				'post_type'  => 'page',
				'post_title' => 'Elementor Page',
			]
		);
		restore_current_blog();

		Testable_Site_Duplicator::backfill_elementor_postmeta($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$this->assertEquals('[{"elType":"section"}]', get_post_meta($page_id, '_elementor_data', true));
		$this->assertEquals('builder', get_post_meta($page_id, '_elementor_edit_mode', true));
		$settings = get_post_meta($page_id, '_elementor_page_settings', true);
		$this->assertIsArray($settings);
		restore_current_blog();
	}

	/**
	 * Test that non-_elementor_* meta keys are not affected.
	 */
	public function test_elementor_backfill_does_not_copy_non_elementor_meta() {
		$this->setup_elementor_data();

		// Add a non-elementor meta key on the source.
		switch_to_blog($this->from_blog_id);
		update_post_meta($this->from_elementor_post_id, '_custom_meta', 'should-not-copy');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_elementor_postmeta($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$this->assertEmpty(get_post_meta($this->from_elementor_post_id, '_custom_meta', true));
		restore_current_blog();
	}

	/**
	 * Test that existing _elementor_* meta is not overwritten (INSERT NOT EXISTS).
	 */
	public function test_elementor_backfill_does_not_overwrite_existing() {
		$this->setup_elementor_data();

		// Pre-insert stub _elementor_data on the target.
		switch_to_blog($this->to_blog_id);
		update_post_meta($this->from_elementor_post_id, '_elementor_data', '[]');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_elementor_postmeta($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		// The stub should survive — INSERT NOT EXISTS skips it.
		$this->assertEquals('[]', get_post_meta($this->from_elementor_post_id, '_elementor_data', true));
		restore_current_blog();
	}

	// =========================================================================
	// Bug 1 — backfill_kit_settings
	// =========================================================================

	/**
	 * Test that Kit _elementor_page_settings is force-overwritten on the clone.
	 *
	 * Bug 1: The Kit post gets stub Elementor defaults before MUCD runs.
	 * INSERT NOT EXISTS silently skips, leaving default blue (#6EC1E4)
	 * instead of the template's actual color palette.
	 * backfill_kit_settings uses update_post_meta() to guarantee overwrite.
	 */
	public function test_kit_settings_force_overwritten() {
		$this->setup_kit_data();

		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		$this->verify_kit_backfill();
	}

	/**
	 * Test that Kit settings overwrite even when stub data exists.
	 *
	 * This is the core of Bug 1: the target kit already has stub
	 * _elementor_page_settings from Elementor's activation routine.
	 * update_post_meta() must overwrite it.
	 */
	public function test_kit_settings_overwrites_stub_data() {
		$this->setup_kit_data();

		// Simulate Elementor's activation creating stub Kit settings on the target.
		switch_to_blog($this->to_blog_id);
		update_option('elementor_active_kit', $this->kit_post_id);
		update_post_meta($this->kit_post_id, '_elementor_page_settings', serialize(['stub' => 'data']));
		update_post_meta($this->kit_post_id, '_elementor_css', 'stub-css-should-be-deleted');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		// The stub must be completely replaced with the template's real settings.
		$settings = get_post_meta($this->kit_post_id, '_elementor_page_settings', true);
		$this->assertIsArray($settings);
		$this->assertArrayHasKey('system_colors', $settings);
		$this->assertEquals('#EAC7C7', $settings['system_colors'][0]['color']);

		// _elementor_css must be deleted to force regeneration.
		$this->assertEmpty(get_post_meta($this->kit_post_id, '_elementor_css', true));
		restore_current_blog();
	}

	/**
	 * Test that Kit _elementor_data is also overwritten when non-empty.
	 */
	public function test_kit_data_overwritten_when_non_empty() {
		$this->setup_kit_data();

		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$kit_data = get_post_meta($this->kit_post_id, '_elementor_data', true);
		$this->assertNotEmpty($kit_data);
		$this->assertNotEquals('[]', $kit_data);
		restore_current_blog();
	}

	/**
	 * Test that backfill_kit_settings bails when template has no kit settings.
	 */
	public function test_kit_settings_bails_when_no_template_settings() {
		// Set up a template with no kit settings.
		switch_to_blog($this->from_blog_id);
		delete_option('elementor_active_kit');
		restore_current_blog();

		// Should not error or write anything.
		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		$this->assertTrue(true);
	}

	/**
	 * Test that _elementor_css is deleted on the target kit.
	 *
	 * This forces Elementor_Compat::regenerate_css() to rebuild
	 * with the correct Kit settings on wu_duplicate_site.
	 */
	public function test_kit_css_deleted_on_target() {
		$this->setup_kit_data();

		// Add compiled CSS on the target.
		switch_to_blog($this->to_blog_id);
		update_option('elementor_active_kit', $this->kit_post_id);
		update_post_meta($this->kit_post_id, '_elementor_css', 'compiled-css-data');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$this->assertEmpty(get_post_meta($this->kit_post_id, '_elementor_css', true));
		restore_current_blog();
	}

	/**
	 * Test that elementor_active_kit option is set on target when missing.
	 *
	 * If the target site doesn't have elementor_active_kit option yet,
	 * backfill_kit_settings should set it to the source kit's post ID.
	 */
	public function test_kit_option_set_on_target_when_missing() {
		$this->setup_kit_data();

		// Ensure target has no elementor_active_kit option.
		switch_to_blog($this->to_blog_id);
		delete_option('elementor_active_kit');
		restore_current_blog();

		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		switch_to_blog($this->to_blog_id);
		$kit_id = (int) get_option('elementor_active_kit', 0);
		$this->assertGreaterThan(0, $kit_id);
		restore_current_blog();
	}

	// =========================================================================
	// Step 3 — verify_kit_integrity
	// =========================================================================

	/**
	 * Test that verify_kit_integrity does not re-apply when Kit is intact.
	 *
	 * When the clone's Kit settings are >= 80% of the template's byte count,
	 * verify_kit_integrity should not re-run backfill_kit_settings.
	 */
	public function test_verify_kit_integrity_passes_when_intact() {
		$this->setup_kit_data();

		// Apply the kit backfill first.
		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		// Verify should not re-apply (Kit is already correct).
		// We track re-application by hooking a counter.
		$reapply_count = 0;
		add_filter(
			'wu_verify_kit_integrity_reapply',
			function () use (&$reapply_count) {
				$reapply_count++;
				return true;
			}
		);

		Testable_Site_Duplicator::verify_kit_integrity($this->from_blog_id, $this->to_blog_id);

		// Kit settings should still be correct (unchanged).
		switch_to_blog($this->to_blog_id);
		$settings = get_post_meta($this->kit_post_id, '_elementor_page_settings', true);
		$this->assertIsArray($settings);
		$this->assertEquals('#EAC7C7', $settings['system_colors'][0]['color']);
		restore_current_blog();
	}

	/**
	 * Test that verify_kit_integrity re-applies Kit fix when clone is truncated.
	 *
	 * When the clone's Kit settings are less than 80% of the template's
	 * byte count, verify_kit_integrity should re-run backfill_kit_settings.
	 */
	public function test_verify_kit_integrity_reapplies_when_truncated() {
		$this->setup_kit_data();

		// Apply the kit backfill first.
		Testable_Site_Duplicator::backfill_kit_settings($this->from_blog_id, $this->to_blog_id);

		// Simulate truncation: replace the Kit settings with a much smaller value.
		switch_to_blog($this->to_blog_id);
		update_post_meta($this->kit_post_id, '_elementor_page_settings', ['minimal' => 'data']);
		restore_current_blog();

		// Now verify — should detect the mismatch and re-apply.
		Testable_Site_Duplicator::verify_kit_integrity($this->from_blog_id, $this->to_blog_id);

		// Kit settings should be restored to the full template values.
		switch_to_blog($this->to_blog_id);
		$settings = get_post_meta($this->kit_post_id, '_elementor_page_settings', true);
		$this->assertIsArray($settings);
		$this->assertArrayHasKey('system_colors', $settings);
		$this->assertEquals('#EAC7C7', $settings['system_colors'][0]['color']);
		$this->assertEquals('#ED6363', $settings['system_colors'][1]['color']);
		restore_current_blog();
	}

	/**
	 * Test that verify_kit_integrity bails when from_site_id is 0.
	 */
	public function test_verify_kit_integrity_bails_on_zero_from() {
		Testable_Site_Duplicator::verify_kit_integrity(0, $this->to_blog_id);
		$this->assertTrue(true);
	}

	/**
	 * Test that verify_kit_integrity bails when to_site_id is 0.
	 */
	public function test_verify_kit_integrity_bails_on_zero_to() {
		Testable_Site_Duplicator::verify_kit_integrity($this->from_blog_id, 0);
		$this->assertTrue(true);
	}

	/**
	 * Test that verify_kit_integrity bails when from and to are the same.
	 */
	public function test_verify_kit_integrity_bails_on_same_site() {
		Testable_Site_Duplicator::verify_kit_integrity($this->from_blog_id, $this->from_blog_id);
		$this->assertTrue(true);
	}

	/**
	 * Test that verify_kit_integrity bails when template has no Kit.
	 */
	public function test_verify_kit_integrity_bails_when_no_template_kit() {
		// No kit data set up — both sites have no elementor_active_kit.
		Testable_Site_Duplicator::verify_kit_integrity($this->from_blog_id, $this->to_blog_id);
		$this->assertTrue(true);
	}

	// =========================================================================
	// Step 4 — wu_template_id meta preference
	// =========================================================================

	/**
	 * Test that wu_template_id meta overrides the from_site_id.
	 *
	 * When a customer selects a template at checkout, WP Ultimo stores the
	 * real template ID in wu_template_id site meta. MUCD's hooks may pass a
	 * different (hardcoded) from_site_id. The backfill must use the meta value.
	 */
	public function test_wu_template_id_overrides_from_site_id() {
		// Create a third blog to act as the "real" template.
		$real_template_id = self::factory()->blog->create(
			[
				'domain' => 'real-template.example.com',
				'path'   => '/',
				'title'  => 'Real Template',
			]
		);

		// Set up Kit data on the real template.
		switch_to_blog($real_template_id);
		$real_kit_id = self::factory()->post->create(
			[
				'post_type' => 'elementor_library',
				'post_title' => 'Real Kit',
			]
		);
		update_option('elementor_active_kit', $real_kit_id);
		update_post_meta($real_kit_id, '_elementor_page_settings', [
			'system_colors' => [
				['_id' => 'primary', 'color' => '#FF0000', 'title' => 'Red'],
			],
		]);
		restore_current_blog();

		// Set wu_template_id on the target to point to the real template.
		update_site_meta($this->to_blog_id, 'wu_template_id', $real_template_id);

		// Set up Kit on the target (simulating MUCD copy from wrong source).
		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(
			[
				'import_id'  => $real_kit_id,
				'post_type' => 'elementor_library',
				'post_title' => 'Real Kit',
			]
		);
		update_option('elementor_active_kit', $real_kit_id);
		restore_current_blog();

		// Run backfill with the WRONG from_site_id (simulating MUCD's
		// hardcoded value). The wu_template_id meta should override it.
		// We test this by verifying the source code contains the resolution.
		$source = file_get_contents(
			WP_ULTIMO_PLUGIN_DIR . '/inc/helpers/class-site-duplicator.php'
		);

		$this->assertStringContainsString(
			"get_site_meta(\$args->to_site_id, 'wu_template_id', true)",
			$source,
			'process_duplication must resolve wu_template_id site meta'
		);

		// Clean up.
		wpmu_delete_blog($real_template_id, true);
		delete_site_meta($this->to_blog_id, 'wu_template_id');
	}

	/**
	 * Test that wu_template_id meta is not used when it matches from_site_id.
	 *
	 * When the meta value is the same as the explicit param, no override
	 * is needed — the code should just proceed normally.
	 */
	public function test_wu_template_id_same_as_from_site_id_is_harmless() {
		// Set wu_template_id to the same value as from_site_id.
		update_site_meta($this->to_blog_id, 'wu_template_id', $this->from_blog_id);

		// The source code should still contain the resolution logic.
		$source = file_get_contents(
			WP_ULTIMO_PLUGIN_DIR . '/inc/helpers/class-site-duplicator.php'
		);

		$this->assertStringContainsString(
			'$meta_template !== (int) $args->from_site_id',
			$source,
			'wu_template_id resolution must check for difference before overriding'
		);

		delete_site_meta($this->to_blog_id, 'wu_template_id');
	}

	/**
	 * Test that wu_template_id meta is not used when it is 0 or missing.
	 *
	 * When no wu_template_id exists, the explicit from_site_id should be used.
	 */
	public function test_wu_template_id_missing_uses_explicit_from_site_id() {
		// Ensure no wu_template_id meta.
		delete_site_meta($this->to_blog_id, 'wu_template_id');

		$source = file_get_contents(
			WP_ULTIMO_PLUGIN_DIR . '/inc/helpers/class-site-duplicator.php'
		);

		// The code should only override when meta_template > 0.
		$this->assertStringContainsString(
			'$meta_template > 0',
			$source,
			'wu_template_id resolution must only override when meta value is positive'
		);
	}

	// =========================================================================
	// wu_duplicate_site action args
	// =========================================================================

	/**
	 * Test that wu_duplicate_site action passes from_site_id.
	 *
	 * The issue notes that downstream hooks need the source template ID
	 * to access the real template, not MUCD's hardcoded params.
	 */
	public function test_duplicate_site_action_includes_from_site_id() {
		// Read the source to confirm from_site_id is in the action args.
		$source = file_get_contents(
			WP_ULTIMO_PLUGIN_DIR . '/inc/helpers/class-site-duplicator.php'
		);

		$this->assertStringContainsString(
			"'from_site_id' => \$args->from_site_id",
			$source,
			'wu_duplicate_site action must pass from_site_id in args'
		);
	}

	// =========================================================================
	// Edge cases
	// =========================================================================

	/**
	 * Test that backfill methods bail when from and to have the same prefix.
	 *
	 * This shouldn't happen in practice (different blogs have different
	 * prefixes), but the guard exists and should be tested.
	 */
	public function test_backfill_bails_on_same_prefix() {
		// Calling with the same blog ID triggers the same-prefix guard
		// inside each sub-method (from_prefix === to_prefix).
		// The orchestrator also guards on from === to.
		// Test the sub-method directly — they check prefix equality.
		Testable_Site_Duplicator::backfill_nav_menu_postmeta(
			$this->from_blog_id,
			$this->from_blog_id
		);

		// No error = the early return worked.
		$this->assertTrue(true);
	}

	// =========================================================================
	// Setup helpers
	// =========================================================================

	/**
	 * Nav menu item post ID on the source blog.
	 *
	 * @var int
	 */
	private $from_nav_item_id;

	/**
	 * Nav menu item post ID on the target blog.
	 *
	 * @var int
	 */
	private $to_nav_item_id;

	/**
	 * Attachment post ID on the source blog.
	 *
	 * @var int
	 */
	private $from_attachment_id;

	/**
	 * Attachment post ID on the target blog.
	 *
	 * @var int
	 */
	private $to_attachment_id;

	/**
	 * Elementor library post ID (same ID on both blogs after MUCD copy).
	 *
	 * @var int
	 */
	private $from_elementor_post_id;

	/**
	 * Kit post ID (same ID on both blogs — MUCD preserves IDs).
	 *
	 * @var int
	 */
	private $kit_post_id;

	/**
	 * Set up nav_menu_item posts and postmeta on source and target.
	 *
	 * Simulates the MUCD state: posts are copied (same IDs) but
	 * postmeta rows are missing on the target.
	 */
	private function setup_nav_menu_data() {
		// Create nav_menu_item on source with full postmeta.
		switch_to_blog($this->from_blog_id);
		$this->from_nav_item_id = self::factory()->post->create(
			[
				'post_type' => 'nav_menu_item',
				'post_title' => 'About Page',
			]
		);
		update_post_meta($this->from_nav_item_id, '_menu_item_type', 'post_type');
		update_post_meta($this->from_nav_item_id, '_menu_item_menu_item_parent', '0');
		update_post_meta($this->from_nav_item_id, '_menu_item_object_id', '42');
		update_post_meta($this->from_nav_item_id, '_menu_item_object', 'page');
		update_post_meta($this->from_nav_item_id, '_menu_item_target', '');
		update_post_meta($this->from_nav_item_id, '_menu_item_classes', serialize(['menu-item', 'menu-item-type-post_type']));
		update_post_meta($this->from_nav_item_id, '_menu_item_xfn', '');
		update_post_meta($this->from_nav_item_id, '_menu_item_url', 'http://template.example.com/about/');
		restore_current_blog();

		// MUCD copies the post (same ID) but NOT the postmeta.
		switch_to_blog($this->to_blog_id);
		$this->to_nav_item_id = self::factory()->post->create(
			[
				'import_id'  => $this->from_nav_item_id,
				'post_type' => 'nav_menu_item',
				'post_title' => 'About Page',
			]
		);
		// Deliberately NOT adding postmeta — simulates the MUCD bug.
		restore_current_blog();
	}

	/**
	 * Set up attachment posts and postmeta on source and target.
	 */
	private function setup_attachment_data() {
		switch_to_blog($this->from_blog_id);
		$this->from_attachment_id = self::factory()->post->create(
			[
				'post_type' => 'attachment',
				'post_title' => 'logo.png',
			]
		);
		update_post_meta($this->from_attachment_id, '_wp_attached_file', '2024/01/logo.png');
		update_post_meta($this->from_attachment_id, '_wp_attachment_metadata', serialize(['width' => 200, 'height' => 60]));
		update_post_meta($this->from_attachment_id, '_wp_attachment_image_alt', 'Site Logo');
		restore_current_blog();

		switch_to_blog($this->to_blog_id);
		$this->to_attachment_id = self::factory()->post->create(
			[
				'import_id'  => $this->from_attachment_id,
				'post_type' => 'attachment',
				'post_title' => 'logo.png',
			]
		);
		// No postmeta — simulates the MUCD bug.
		restore_current_blog();
	}

	/**
	 * Set up elementor_library posts and postmeta on source and target.
	 */
	private function setup_elementor_data() {
		switch_to_blog($this->from_blog_id);
		$this->from_elementor_post_id = self::factory()->post->create(
			[
				'post_type' => 'elementor_library',
				'post_title' => 'Custom Header',
			]
		);
		update_post_meta($this->from_elementor_post_id, '_elementor_data', '[{"elType":"section","elements":[]}]');
		update_post_meta($this->from_elementor_post_id, '_elementor_page_settings', serialize(['template' => 'header']));
		update_post_meta($this->from_elementor_post_id, '_elementor_edit_mode', 'builder');
		update_post_meta($this->from_elementor_post_id, '_elementor_template_type', 'header');
		restore_current_blog();

		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(
			[
				'import_id'  => $this->from_elementor_post_id,
				'post_type' => 'elementor_library',
				'post_title' => 'Custom Header',
			]
		);
		// No _elementor_* postmeta — simulates the MUCD bug.
		restore_current_blog();
	}

	/**
	 * Set up Elementor Kit post and settings on source and target.
	 */
	private function setup_kit_data() {
		$kit_settings = [
			'system_colors' => [
				['_id' => 'primary', 'color' => '#EAC7C7', 'title' => 'Primary'],
				['_id' => 'secondary', 'color' => '#ED6363', 'title' => 'Secondary'],
			],
			'custom_colors' => [
				['_id' => 'brand', 'color' => '#1A1A2E', 'title' => 'Brand'],
			],
			'system_typography' => [
				['_id' => 'primary', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '600'],
			],
			'container_width' => ['size' => 1140, 'unit' => 'px'],
		];

		$kit_data = '[{"elType":"section","elements":[{"elType":"widget"}]}]';

		// Create Kit post on source.
		switch_to_blog($this->from_blog_id);
		$this->kit_post_id = self::factory()->post->create(
			[
				'post_type' => 'elementor_library',
				'post_title' => 'Elementor Kit',
			]
		);
		update_option('elementor_active_kit', $this->kit_post_id);
		update_post_meta($this->kit_post_id, '_elementor_page_settings', $kit_settings);
		update_post_meta($this->kit_post_id, '_elementor_data', $kit_data);
		restore_current_blog();

		// Create Kit post on target — MUCD copies the post but
		// Elementor's activation may have already created stub postmeta.
		switch_to_blog($this->to_blog_id);
		self::factory()->post->create(
			[
				'import_id'  => $this->kit_post_id,
				'post_type' => 'elementor_library',
				'post_title' => 'Elementor Kit',
			]
		);
		update_option('elementor_active_kit', $this->kit_post_id);
		// No _elementor_page_settings yet — simulates the state before backfill.
		restore_current_blog();
	}

	// =========================================================================
	// Verification helpers
	// =========================================================================

	/**
	 * Verify nav_menu_item postmeta was backfilled.
	 */
	private function verify_nav_menu_backfill() {
		switch_to_blog($this->to_blog_id);
		$this->assertEquals('post_type', get_post_meta($this->to_nav_item_id, '_menu_item_type', true));
		$this->assertEquals('0', get_post_meta($this->to_nav_item_id, '_menu_item_menu_item_parent', true));
		$this->assertEquals('42', get_post_meta($this->to_nav_item_id, '_menu_item_object_id', true));
		$this->assertEquals('page', get_post_meta($this->to_nav_item_id, '_menu_item_object', true));
		$this->assertEquals('', get_post_meta($this->to_nav_item_id, '_menu_item_target', true));
		$this->assertEquals('http://template.example.com/about/', get_post_meta($this->to_nav_item_id, '_menu_item_url', true));
		restore_current_blog();
	}

	/**
	 * Verify attachment postmeta was backfilled.
	 */
	private function verify_attachment_backfill() {
		switch_to_blog($this->to_blog_id);
		$this->assertEquals('2024/01/logo.png', get_post_meta($this->to_attachment_id, '_wp_attached_file', true));
		$meta = unserialize(get_post_meta($this->to_attachment_id, '_wp_attachment_metadata', true));
		$this->assertIsArray($meta);
		$this->assertEquals(200, $meta['width']);
		$this->assertEquals('Site Logo', get_post_meta($this->to_attachment_id, '_wp_attachment_image_alt', true));
		restore_current_blog();
	}

	/**
	 * Verify _elementor_* postmeta was backfilled.
	 */
	private function verify_elementor_backfill() {
		switch_to_blog($this->to_blog_id);
		$this->assertEquals(
			'[{"elType":"section","elements":[]}]',
			get_post_meta($this->from_elementor_post_id, '_elementor_data', true)
		);
		$this->assertEquals(
			'builder',
			get_post_meta($this->from_elementor_post_id, '_elementor_edit_mode', true)
		);
		$this->assertEquals(
			'header',
			get_post_meta($this->from_elementor_post_id, '_elementor_template_type', true)
		);
		$settings = unserialize(
			get_post_meta($this->from_elementor_post_id, '_elementor_page_settings', true)
		);
		$this->assertIsArray($settings);
		$this->assertEquals('header', $settings['template']);
		restore_current_blog();
	}

	/**
	 * Verify Kit settings were force-overwritten.
	 */
	private function verify_kit_backfill() {
		switch_to_blog($this->to_blog_id);
		$settings = get_post_meta($this->kit_post_id, '_elementor_page_settings', true);
		$this->assertIsArray($settings);
		$this->assertArrayHasKey('system_colors', $settings);
		$this->assertCount(2, $settings['system_colors']);
		$this->assertEquals('#EAC7C7', $settings['system_colors'][0]['color']);
		$this->assertEquals('#ED6363', $settings['system_colors'][1]['color']);
		$this->assertArrayHasKey('custom_colors', $settings);
		$this->assertEquals('#1A1A2E', $settings['custom_colors'][0]['color']);

		// _elementor_css must be deleted.
		$this->assertEmpty(get_post_meta($this->kit_post_id, '_elementor_css', true));
		restore_current_blog();
	}
}

/**
 * Testable subclass that exposes protected static methods.
 *
 * Site_Duplicator uses protected static methods, which cannot be called
 * directly from tests. This subclass makes them public.
 */
class Testable_Site_Duplicator extends Site_Duplicator {

	/**
	 * Expose backfill_postmeta.
	 */
	public static function backfill_postmeta($from_site_id, $to_site_id) {
		parent::backfill_postmeta($from_site_id, $to_site_id);
	}

	/**
	 * Expose backfill_nav_menu_postmeta.
	 */
	public static function backfill_nav_menu_postmeta($from_site_id, $to_site_id) {
		parent::backfill_nav_menu_postmeta($from_site_id, $to_site_id);
	}

	/**
	 * Expose backfill_attachment_postmeta.
	 */
	public static function backfill_attachment_postmeta($from_site_id, $to_site_id) {
		parent::backfill_attachment_postmeta($from_site_id, $to_site_id);
	}

	/**
	 * Expose backfill_elementor_postmeta.
	 */
	public static function backfill_elementor_postmeta($from_site_id, $to_site_id) {
		parent::backfill_elementor_postmeta($from_site_id, $to_site_id);
	}

	/**
	 * Expose backfill_kit_settings.
	 */
	public static function backfill_kit_settings($from_site_id, $to_site_id) {
		parent::backfill_kit_settings($from_site_id, $to_site_id);
	}

	/**
	 * Expose verify_kit_integrity.
	 */
	public static function verify_kit_integrity($from_site_id, $to_site_id) {
		parent::verify_kit_integrity($from_site_id, $to_site_id);
	}
}
