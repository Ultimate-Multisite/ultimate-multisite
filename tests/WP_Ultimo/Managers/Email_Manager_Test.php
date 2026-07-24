<?php
/**
 * Unit tests for Email_Manager class.
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Email_Manager;

/**
 * Unit tests for Email_Manager class.
 */
class Email_Manager_Test extends \WP_UnitTestCase {

	/**
	 * Email Manager instance.
	 *
	 * @var Email_Manager
	 */
	protected $manager;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->manager = Email_Manager::get_instance();
	}

	/**
	 * Test that create_all_system_emails registers emails before creating them.
	 *
	 * This tests the fix for the setup wizard issue where system emails
	 * weren't being created because registered_default_system_emails was empty.
	 */
	public function test_create_all_system_emails_registers_before_creating(): void {
		// Use reflection to access the protected property
		$reflection = new \ReflectionClass($this->manager);
		$property   = $reflection->getProperty('registered_default_system_emails');
		$property->setAccessible(true);

		// Reset the property to null to simulate the initial state
		$property->setValue($this->manager, null);

		// Delete any existing system emails to ensure a clean test state
		$existing_emails = wu_get_all_system_emails();
		foreach ($existing_emails as $email) {
			$email->delete();
		}

		// Get count of existing emails before (should be 0 now)
		$emails_before = wu_get_all_system_emails();
		$count_before  = count($emails_before);

		// Call create_all_system_emails
		$this->manager->create_all_system_emails();

		// After calling create_all_system_emails, the property should be populated
		$registered_emails = $property->getValue($this->manager);
		$this->assertIsArray($registered_emails, 'registered_default_system_emails should be an array');
		$this->assertNotEmpty($registered_emails, 'registered_default_system_emails should not be empty');

		// Verify emails were actually created
		$emails_after = wu_get_all_system_emails();
		$count_after  = count($emails_after);

		$this->assertGreaterThan($count_before, $count_after, 'System emails should have been created');
	}

	/**
	 * Test that register_all_default_system_emails populates the registry.
	 */
	public function test_register_all_default_system_emails_populates_registry(): void {
		// Use reflection to access the protected property
		$reflection = new \ReflectionClass($this->manager);
		$property   = $reflection->getProperty('registered_default_system_emails');
		$property->setAccessible(true);

		// Reset the property to null
		$property->setValue($this->manager, null);

		// Call register_all_default_system_emails
		$this->manager->register_all_default_system_emails();

		// Verify the property is now populated
		$registered_emails = $property->getValue($this->manager);
		$this->assertIsArray($registered_emails, 'registered_default_system_emails should be an array');
		$this->assertNotEmpty($registered_emails, 'registered_default_system_emails should contain email definitions');

		// Verify some expected default emails are registered
		$this->assertArrayHasKey('payment_received_admin', $registered_emails);
		$this->assertArrayHasKey('payment_received_customer', $registered_emails);
		$this->assertArrayHasKey('site_published_admin', $registered_emails);
		$this->assertArrayHasKey('site_published_customer', $registered_emails);
		$this->assertSame('{{payment_email_admin_subject}}', $registered_emails['payment_received_admin']['title']);
		$this->assertSame('{{payment_email_customer_subject}}', $registered_emails['payment_received_customer']['title']);
		$this->assertFalse($registered_emails['payment_received_customer']['send_copy_to_admin']);
	}

	/**
	 * Test that is_created correctly identifies existing emails.
	 */
	public function test_is_created_identifies_existing_emails(): void {
		// Create a test email
		$email_data = [
			'slug'    => 'test_email_unique_' . time(),
			'title'   => 'Test Email',
			'content' => 'Test content',
			'event'   => 'test_event',
			'target'  => 'admin',
			'type'    => 'system_email',
			'status'  => 'publish',
		];

		$email = wu_create_email($email_data);
		$this->assertNotWPError($email, 'Email should be created successfully');

		// Test is_created returns true for the existing email
		$is_created = $this->manager->is_created($email_data['slug']);
		$this->assertTrue($is_created, 'is_created should return true for existing email');

		// Test is_created returns false for non-existent email
		$is_not_created = $this->manager->is_created('non_existent_email_slug_' . time());
		$this->assertFalse($is_not_created, 'is_created should return false for non-existent email');
	}

	/**
	 * Test that create_system_email doesn't create duplicates.
	 */
	public function test_create_system_email_prevents_duplicates(): void {
		// Register default system emails first
		$this->manager->register_all_default_system_emails();

		// Get count before
		$emails_before = wu_get_all_system_emails();
		$count_before  = count($emails_before);

		// Try to create the same system email twice
		$email_data = [
			'slug'    => 'payment_received_admin',
			'title'   => 'Test Payment Email',
			'content' => 'Test content',
			'event'   => 'payment_received',
			'target'  => 'admin',
		];

		$result1 = $this->manager->create_system_email($email_data);
		$result2 = $this->manager->create_system_email($email_data);

		// The second call should return early without creating a duplicate
		$this->assertNull($result2, 'Second create_system_email call should return null for duplicate');

		// Verify count didn't increase by more than 1
		$emails_after = wu_get_all_system_emails();
		$count_after  = count($emails_after);

		$this->assertLessThanOrEqual($count_before + 1, $count_after, 'Should not create duplicate emails');
	}
}
