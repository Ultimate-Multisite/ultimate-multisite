<?php
/**
 * Tests for Self_Boot_Builder.
 *
 * Covers the builder class (staging-dir injection, template rendering) and the
 * key runtime behaviours of the generated installer (existing-install detection,
 * CSRF guard, step-resume logic).
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 * @subpackage Self_Boot
 * @since 2.5.0
 */

namespace WP_Ultimo\Tests\Site_Exporter;

use WP_UnitTestCase;
use WP_Ultimo\Site_Exporter\Self_Boot_Builder;

/**
 * Test class for Self_Boot_Builder.
 *
 * @package WP_Ultimo\Tests\Site_Exporter
 * @since 2.5.0
 */
class Self_Boot_Builder_Test extends WP_UnitTestCase {

	/**
	 * Temp directory used by each test (cleaned up in tear_down).
	 *
	 * @var string
	 */
	private string $tmp_dir;

	/**
	 * Path to the rendered index.php (set by helper method).
	 *
	 * @var string
	 */
	private string $rendered_index_path;

	/**
	 * Set up a fresh temp directory for each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->tmp_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
			. DIRECTORY_SEPARATOR
			. 'wu-self-boot-test-' . uniqid('', true)
			. DIRECTORY_SEPARATOR;

		mkdir($this->tmp_dir, 0755, true);
	}

	/**
	 * Remove the temp directory after each test.
	 */
	public function tear_down(): void {
		$this->remove_dir($this->tmp_dir);
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Builder class tests
	// -------------------------------------------------------------------------

	/**
	 * Test add_to_staging_dir() creates index.php and readme.txt in the dir.
	 */
	public function test_self_boot_builder_adds_index_and_readme_to_staging_dir(): void {
		$result = Self_Boot_Builder::add_to_staging_dir($this->tmp_dir, 'single-site', []);

		$this->assertTrue($result, 'add_to_staging_dir() should return true on success');
		$this->assertFileExists(
			$this->tmp_dir . 'index.php',
			'index.php must be created in the staging directory'
		);
		$this->assertFileExists(
			$this->tmp_dir . 'readme.txt',
			'readme.txt must be created in the staging directory'
		);
	}

	/**
	 * Test add_to_staging_dir() for network bundles creates files with correct content.
	 */
	public function test_self_boot_builder_adds_network_files_to_staging_dir(): void {
		$manifest = [
			'source' => [
				'url'                  => 'https://network.example.com',
				'wp_version'           => '6.5',
				'php_version'          => '8.2',
				'is_subdomain_install' => true,
			],
		];

		Self_Boot_Builder::add_to_staging_dir($this->tmp_dir, 'network', $manifest);

		$index_content  = file_get_contents($this->tmp_dir . 'index.php');
		$readme_content = file_get_contents($this->tmp_dir . 'readme.txt');

		$this->assertStringContainsString('network', $index_content, 'index.php must reference bundle type "network"');
		$this->assertStringContainsString('network', $readme_content, 'readme.txt must reference bundle type "network"');
		$this->assertStringNotContainsString('{{BUNDLE_TYPE}}', $index_content, 'Token {{BUNDLE_TYPE}} must be substituted');
		$this->assertStringNotContainsString('{{SOURCE_URL}}', $index_content, 'Token {{SOURCE_URL}} must be substituted');
	}

	/**
	 * Test add_to_staging_dir() returns false for a non-existent directory.
	 */
	public function test_self_boot_builder_returns_false_for_missing_staging_dir(): void {
		$result = Self_Boot_Builder::add_to_staging_dir('/non-existent-dir-wu-boot/', 'single-site', []);

		$this->assertFalse($result, 'add_to_staging_dir() must return false when dir does not exist');
	}

	/**
	 * Test render_index_template() substitutes all known tokens.
	 */
	public function test_render_index_template_substitutes_all_tokens(): void {
		$manifest = [
			'source' => [
				'url'                  => 'https://example.com',
				'wp_version'           => '6.5',
				'php_version'          => '8.1',
				'is_subdomain_install' => false,
			],
		];

		$rendered = Self_Boot_Builder::render_index_template('single-site', $manifest);

		$this->assertNotEmpty($rendered, 'Rendered template must not be empty');
		$this->assertStringNotContainsString('{{BUNDLE_TYPE}}', $rendered, '{{BUNDLE_TYPE}} token must be replaced');
		$this->assertStringNotContainsString('{{PLUGIN_VERSION}}', $rendered, '{{PLUGIN_VERSION}} token must be replaced');
		$this->assertStringNotContainsString('{{EXPORT_DATE}}', $rendered, '{{EXPORT_DATE}} token must be replaced');
		$this->assertStringNotContainsString('{{SOURCE_URL}}', $rendered, '{{SOURCE_URL}} token must be replaced');
		$this->assertStringNotContainsString('{{SOURCE_WP_VERSION}}', $rendered, '{{SOURCE_WP_VERSION}} token must be replaced');
		$this->assertStringNotContainsString('{{SOURCE_PHP_VERSION}}', $rendered, '{{SOURCE_PHP_VERSION}} token must be replaced');
		$this->assertStringNotContainsString('{{SUBDOMAIN_INSTALL}}', $rendered, '{{SUBDOMAIN_INSTALL}} token must be replaced');
	}

	/**
	 * Test render_readme() substitutes all known tokens.
	 */
	public function test_render_readme_substitutes_all_tokens(): void {
		$manifest = ['source' => ['url' => 'https://example.com']];
		$rendered = Self_Boot_Builder::render_readme('network', $manifest);

		$this->assertNotEmpty($rendered, 'Rendered readme must not be empty');
		$this->assertStringNotContainsString('{{BUNDLE_TYPE}}', $rendered, '{{BUNDLE_TYPE}} token must be replaced');
		$this->assertStringNotContainsString('{{SOURCE_URL}}', $rendered, '{{SOURCE_URL}} token must be replaced');
	}

	// -------------------------------------------------------------------------
	// Rendered installer PHP syntax test
	// -------------------------------------------------------------------------

	/**
	 * The rendered index.php must be syntactically valid PHP 8.2.
	 *
	 * Runs `php -l` on the rendered output in a subprocess.
	 */
	public function test_self_boot_index_template_is_php_82_compatible(): void {
		$rendered = Self_Boot_Builder::render_index_template('single-site', []);

		if (empty($rendered)) {
			$this->markTestSkipped('Template file not available in this environment.');
			return;
		}

		$tmp_file = $this->tmp_dir . 'rendered-index-test.php';
		file_put_contents($tmp_file, $rendered);

		$php_bin = PHP_BINARY;
		$output  = [];
		$exit    = -1;

		exec(escapeshellarg($php_bin) . ' -l ' . escapeshellarg($tmp_file) . ' 2>&1', $output, $exit);

		$this->assertSame(
			0,
			$exit,
			'Rendered index.php must pass `php -l` (syntax check). Output: ' . implode("\n", $output)
		);
	}

	// -------------------------------------------------------------------------
	// Installer runtime behavior tests
	// -------------------------------------------------------------------------

	/**
	 * The installer must refuse to run when wp-config.php is already present.
	 *
	 * Writes the rendered installer to a temp dir, creates a fake wp-config.php,
	 * then runs the installer via PHP CLI and asserts the output contains the
	 * "already installed" message.
	 */
	public function test_self_boot_index_refuses_on_existing_install(): void {
		$rendered = Self_Boot_Builder::render_index_template('single-site', []);

		if (empty($rendered)) {
			$this->markTestSkipped('Template file not available in this environment.');
			return;
		}

		$installer_path = $this->write_test_installer($rendered);

		// Place a fake wp-config.php to simulate existing install.
		file_put_contents($this->tmp_dir . 'wp-config.php', '<?php // stub');

		$output = $this->run_installer_cli($installer_path, 'GET', []);

		$this->assertStringContainsString(
			'WordPress Already Installed',
			$output,
			'Installer must refuse and show "WordPress Already Installed" when wp-config.php is present'
		);
	}

	/**
	 * The installer must reject POST requests that do not include a valid CSRF token.
	 *
	 * Simulates a form submission without a token and verifies the installer
	 * returns the CSRF mismatch error message.
	 */
	public function test_self_boot_token_csrf(): void {
		$rendered = Self_Boot_Builder::render_index_template('single-site', []);

		if (empty($rendered)) {
			$this->markTestSkipped('Template file not available in this environment.');
			return;
		}

		$installer_path = $this->write_test_installer($rendered);

		// POST with no token — no .wu-bootstrap-token file exists.
		$output = $this->run_installer_cli(
			$installer_path,
			'POST',
			[
				'db_host' => 'localhost',
				'db_name' => 'test',
				'db_user' => 'root',
				'db_pass' => '',
			]
		);

		$this->assertStringContainsString(
			'Security token',
			$output,
			'Installer must show a CSRF/security-token error when posting without a valid token'
		);
	}

	/**
	 * The installer must resume from the last good step after a partial failure.
	 *
	 * Writes a state file with `db_credentials` and `site_info` marked complete,
	 * then runs the installer and asserts it proceeds to the core-acquisition step
	 * rather than re-showing the credential form.
	 */
	public function test_self_boot_resume_after_partial_failure(): void {
		$rendered = Self_Boot_Builder::render_index_template('single-site', []);

		if (empty($rendered)) {
			$this->markTestSkipped('Template file not available in this environment.');
			return;
		}

		$installer_path = $this->write_test_installer($rendered);
		$state_file     = $this->tmp_dir . '.wu-bootstrap-state.json';

		// Simulate partial completion: first two steps done.
		$partial_state = [
			'completed_steps' => ['db_credentials', 'site_info'],
			'data'            => [
				'db_host'     => 'localhost',
				'db_name'     => 'test',
				'db_user'     => 'root',
				'db_pass'     => '',
				'db_prefix'   => 'wp_',
				'site_url'    => 'http://example.com',
				'admin_user'  => 'admin',
				'admin_pass'  => 'secret123',
				'admin_email' => 'admin@example.com',
				'site_title'  => 'Test Site',
			],
			'errors'          => ['core_acquired' => 'Simulated failure on previous run'],
		];
		file_put_contents($state_file, json_encode($partial_state));

		$output = $this->run_installer_cli($installer_path, 'GET', []);

		// The installer should be on step 3 (core_acquired), not re-showing DB form.
		$this->assertStringNotContainsString(
			'Database Host',
			$output,
			'Installer must NOT re-show DB credentials form when first two steps are already complete'
		);

		$this->assertStringContainsString(
			'Core',
			$output,
			'Installer must show the core-acquisition step when resuming from partial state'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Write the rendered installer to a file in the temp dir and return its path.
	 *
	 * Wraps the installer content in a guard that prevents automatic HTML output
	 * during CLI-mode tests (the `REQUEST_METHOD` is set by the wrapper script).
	 *
	 * @param string $rendered Rendered installer PHP code.
	 * @return string Absolute path to the installer file.
	 */
	private function write_test_installer(string $rendered): string {
		$path = $this->tmp_dir . 'index.php';
		file_put_contents($path, $rendered);
		return $path;
	}

	/**
	 * Execute the installer PHP file in a subprocess with simulated HTTP context.
	 *
	 * @param string $installer_path Absolute path to the installer index.php.
	 * @param string $method         HTTP method ('GET' or 'POST').
	 * @param array  $post_data      POST data to simulate.
	 * @return string Combined stdout + stderr output.
	 */
	private function run_installer_cli(string $installer_path, string $method = 'GET', array $post_data = []): string {
		if (! function_exists('proc_open')) {
			$this->markTestSkipped('proc_open() is not available.');
			return '';
		}

		$installer_dir  = dirname($installer_path);
		$post_json      = json_encode($post_data);
		$method_escaped = escapeshellarg($method);

		// Build a wrapper script that sets $_SERVER and $_POST, then includes the installer.
		$wrapper = <<<PHP
<?php
\$_SERVER['HTTP_HOST']   = 'localhost';
\$_SERVER['REQUEST_URI'] = '/';
\$_SERVER['HTTPS']       = 'off';
\$_SERVER['REQUEST_METHOD'] = $method_escaped;
\$post_data = json_decode('$post_json', true);
\$_POST = is_array(\$post_data) ? \$post_data : [];
\$_GET  = [];

// Suppress output buffering to capture all output.
ob_start();
chdir(dirname(__FILE__) . '/installer');

// Redirect HTML output to string so the test can inspect it.
include dirname(__FILE__) . '/installer/index.php';
\$out = ob_get_clean();
echo \$out;
PHP;

		// Ensure installer is in a predictable subdirectory.
		$sub_dir = $installer_dir . '/installer/';
		if (! is_dir($sub_dir)) {
			mkdir($sub_dir, 0755, true);
			copy($installer_path, $sub_dir . 'index.php');

			// Copy state/token files if they exist.
			$state_file = $installer_dir . '/.wu-bootstrap-state.json';
			$token_file = $installer_dir . '/.wu-bootstrap-token';
			if (file_exists($state_file)) {
				copy($state_file, $sub_dir . '.wu-bootstrap-state.json');
			}
			if (file_exists($token_file)) {
				copy($token_file, $sub_dir . '.wu-bootstrap-token');
			}

			// Copy fake wp-config.php if it exists (for existing-install test).
			$config = $installer_dir . '/wp-config.php';
			if (file_exists($config)) {
				copy($config, $sub_dir . 'wp-config.php');
			}
		}

		$wrapper_path = $installer_dir . '/test-wrapper.php';
		file_put_contents($wrapper_path, $wrapper);

		$php_bin = escapeshellarg(PHP_BINARY);
		$script  = escapeshellarg($wrapper_path);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$proc = proc_open("$php_bin $script", $descriptors, $pipes);

		if (! is_resource($proc)) {
			return '';
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($proc);

		return (string) $stdout . (string) $stderr;
	}

	/**
	 * Recursively remove a directory and all its contents.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_dir(string $dir): void {
		if (! is_dir($dir)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $file) {
			if ($file->isDir()) {
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}

		rmdir($dir);
	}
}
