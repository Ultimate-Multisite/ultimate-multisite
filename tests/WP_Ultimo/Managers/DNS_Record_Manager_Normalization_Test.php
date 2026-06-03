<?php
/**
 * Tests for DNS record response normalization.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Integrations\Host_Providers\DNS_Record;
use WP_Ultimo\Managers\DNS_Record_Manager;
use WP_UnitTestCase;

/**
 * Test DNS_Record_Manager response normalization.
 */
class DNS_Record_Manager_Normalization_Test extends WP_UnitTestCase {

	/**
	 * Test read-only PHPDNS records are mapped to the front-end table shape.
	 */
	public function test_normalize_records_for_response_maps_readonly_dns_records(): void {
		$manager = DNS_Record_Manager::get_instance();
		$method  = new \ReflectionMethod($manager, 'normalize_records_for_response');
		$method->setAccessible(true);

		$records = $method->invoke(
			$manager,
			[
				[
					'host' => 'www.example.com',
					'data' => '127.0.0.1',
					'ttl'  => 300,
					'type' => 'A',
				],
				new DNS_Record(
					[
						'id'      => 'txt-1',
						'type'    => 'TXT',
						'name'    => '@',
						'content' => 'v=spf1 -all',
					]
				),
			]
		);

		$this->assertSame('www.example.com', $records[0]['name']);
		$this->assertSame('127.0.0.1', $records[0]['content']);
		$this->assertNotEmpty($records[0]['id']);
		$this->assertSame('txt-1', $records[1]['id']);
		$this->assertSame('@', $records[1]['name']);
		$this->assertSame('v=spf1 -all', $records[1]['content']);
	}
}
