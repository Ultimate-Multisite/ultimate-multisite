<?php
/**
 * Regression tests for mpdf PSR HTTP message shim PHP 8 / PSR v2 compatibility.
 *
 * These tests guard against the autoload-order failure mode where psr/http-message v2
 * (bundled by plugins such as plugin-check) is loaded before the mpdf shim classes.
 * PSR v2 interfaces declare explicit return types; any shim method that lacks a matching
 * return type declaration causes a PHP fatal at class-load time.
 *
 * The patch in patches/mpdf-psr-http-message-shim-php8-compat.patch adds return types
 * to all four shim classes. This test suite catches any regression where that patch is
 * reverted, only partially applied, or stops applying on a future vendor reinstall.
 *
 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/issues/808
 * @see https://github.com/Ultimate-Multisite/ultimate-multisite/pull/782
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @group mpdf-psr-shim
 */

namespace WP_Ultimo\Tests;

/**
 * Autoload-order regression tests for the mpdf PSR-7 HTTP message shim.
 */
class MpdfPsrHttpMessageShim_Test extends \WP_UnitTestCase {

	// ------------------------------------------------------------------
	// Class existence
	// ------------------------------------------------------------------

	public function test_psr_interfaces_are_present() {
		$this->assertTrue(interface_exists(\Psr\Http\Message\RequestInterface::class), 'PSR RequestInterface must be autoloadable');
		$this->assertTrue(interface_exists(\Psr\Http\Message\ResponseInterface::class), 'PSR ResponseInterface must be autoloadable');
		$this->assertTrue(interface_exists(\Psr\Http\Message\StreamInterface::class), 'PSR StreamInterface must be autoloadable');
		$this->assertTrue(interface_exists(\Psr\Http\Message\UriInterface::class), 'PSR UriInterface must be autoloadable');
	}

	public function test_shim_classes_are_present() {
		$this->assertTrue(class_exists(\Mpdf\PsrHttpMessageShim\Request::class), 'Shim Request class must be autoloadable');
		$this->assertTrue(class_exists(\Mpdf\PsrHttpMessageShim\Response::class), 'Shim Response class must be autoloadable');
		$this->assertTrue(class_exists(\Mpdf\PsrHttpMessageShim\Stream::class), 'Shim Stream class must be autoloadable');
		$this->assertTrue(class_exists(\Mpdf\PsrHttpMessageShim\Uri::class), 'Shim Uri class must be autoloadable');
	}

	// ------------------------------------------------------------------
	// Interface implementation
	// These would cause a PHP fatal if PSR v2 (with return types) is loaded
	// before a shim class that lacks matching return type declarations.
	// ------------------------------------------------------------------

	public function test_shim_request_implements_psr_request_interface() {
		$this->assertTrue(
			is_a(\Mpdf\PsrHttpMessageShim\Request::class, \Psr\Http\Message\RequestInterface::class, true),
			'Shim Request must implement Psr\\Http\\Message\\RequestInterface'
		);
	}

	public function test_shim_response_implements_psr_response_interface() {
		$this->assertTrue(
			is_a(\Mpdf\PsrHttpMessageShim\Response::class, \Psr\Http\Message\ResponseInterface::class, true),
			'Shim Response must implement Psr\\Http\\Message\\ResponseInterface'
		);
	}

	public function test_shim_stream_implements_psr_stream_interface() {
		$this->assertTrue(
			is_a(\Mpdf\PsrHttpMessageShim\Stream::class, \Psr\Http\Message\StreamInterface::class, true),
			'Shim Stream must implement Psr\\Http\\Message\\StreamInterface'
		);
	}

	public function test_shim_uri_implements_psr_uri_interface() {
		$this->assertTrue(
			is_a(\Mpdf\PsrHttpMessageShim\Uri::class, \Psr\Http\Message\UriInterface::class, true),
			'Shim Uri must implement Psr\\Http\\Message\\UriInterface'
		);
	}

	// ------------------------------------------------------------------
	// Return-type declarations on Request shim
	// Reflection verifies the patch added ": type" to each method.
	// If the patch is reverted the assertNotNull on $return_type will fail.
	// ------------------------------------------------------------------

	public function test_request_get_request_target_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getRequestTarget', 'string');
	}

	public function test_request_get_method_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getMethod', 'string');
	}

	public function test_request_get_uri_has_uri_interface_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getUri', \Psr\Http\Message\UriInterface::class);
	}

	public function test_request_get_protocol_version_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getProtocolVersion', 'string');
	}

	public function test_request_get_headers_has_array_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getHeaders', 'array');
	}

	public function test_request_has_header_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'hasHeader', 'bool');
	}

	public function test_request_get_header_has_array_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getHeader', 'array');
	}

	public function test_request_get_header_line_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getHeaderLine', 'string');
	}

	public function test_request_get_body_has_stream_interface_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Request::class, 'getBody', \Psr\Http\Message\StreamInterface::class);
	}

	// ------------------------------------------------------------------
	// Return-type declarations on Response shim
	// ------------------------------------------------------------------

	public function test_response_get_status_code_has_int_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'getStatusCode', 'int');
	}

	public function test_response_get_reason_phrase_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'getReasonPhrase', 'string');
	}

	public function test_response_get_protocol_version_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'getProtocolVersion', 'string');
	}

	public function test_response_get_headers_has_array_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'getHeaders', 'array');
	}

	public function test_response_has_header_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'hasHeader', 'bool');
	}

	public function test_response_get_body_has_stream_interface_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Response::class, 'getBody', \Psr\Http\Message\StreamInterface::class);
	}

	// ------------------------------------------------------------------
	// Return-type declarations on Stream shim
	// ------------------------------------------------------------------

	public function test_stream_get_size_has_nullable_int_return_type() {
		$this->assert_method_nullable_int_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'getSize');
	}

	public function test_stream_tell_has_int_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'tell', 'int');
	}

	public function test_stream_eof_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'eof', 'bool');
	}

	public function test_stream_is_seekable_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'isSeekable', 'bool');
	}

	public function test_stream_is_writable_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'isWritable', 'bool');
	}

	public function test_stream_is_readable_has_bool_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'isReadable', 'bool');
	}

	public function test_stream_get_contents_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Stream::class, 'getContents', 'string');
	}

	// ------------------------------------------------------------------
	// Return-type declarations on Uri shim
	// ------------------------------------------------------------------

	public function test_uri_get_scheme_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getScheme', 'string');
	}

	public function test_uri_get_authority_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getAuthority', 'string');
	}

	public function test_uri_get_user_info_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getUserInfo', 'string');
	}

	public function test_uri_get_host_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getHost', 'string');
	}

	public function test_uri_get_port_has_nullable_int_return_type() {
		$this->assert_method_nullable_int_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getPort');
	}

	public function test_uri_get_path_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getPath', 'string');
	}

	public function test_uri_get_query_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getQuery', 'string');
	}

	public function test_uri_get_fragment_has_string_return_type() {
		$this->assert_method_return_type(\Mpdf\PsrHttpMessageShim\Uri::class, 'getFragment', 'string');
	}

	// ------------------------------------------------------------------
	// Behavioural instantiation checks
	// Verify the shim classes can be instantiated and return the correct types.
	// These would also fail if a fatal occurs at class-load time.
	// ------------------------------------------------------------------

	public function test_request_get_method_returns_string() {
		$uri = new \Mpdf\PsrHttpMessageShim\Uri('http://example.com/');
		$request = new \Mpdf\PsrHttpMessageShim\Request('GET', $uri);
		$result = $request->getMethod();
		$this->assertIsString($result);
		$this->assertSame('GET', $result);
	}

	public function test_request_get_request_target_returns_string() {
		$uri = new \Mpdf\PsrHttpMessageShim\Uri('http://example.com/path');
		$request = new \Mpdf\PsrHttpMessageShim\Request('GET', $uri);
		$this->assertIsString($request->getRequestTarget());
	}

	public function test_response_get_status_code_returns_int() {
		$response = new \Mpdf\PsrHttpMessageShim\Response(201);
		$code = $response->getStatusCode();
		$this->assertIsInt($code);
		$this->assertSame(201, $code);
	}

	public function test_stream_get_size_returns_int_or_null() {
		$stream = \Mpdf\PsrHttpMessageShim\Stream::create('hello world');
		$size = $stream->getSize();
		// getSize() returns ?int — both int and null are valid.
		$this->assertTrue(null === $size || is_int($size), 'Stream::getSize() must return int or null');
	}

	public function test_uri_get_port_returns_int_or_null() {
		// http uses default port 80 — getPort() should return null for default ports.
		$uri = new \Mpdf\PsrHttpMessageShim\Uri('http://example.com/');
		$port = $uri->getPort();
		$this->assertTrue(null === $port || is_int($port), 'Uri::getPort() must return int or null');
	}

	public function test_uri_get_port_returns_int_for_non_standard_port() {
		$uri = new \Mpdf\PsrHttpMessageShim\Uri('http://example.com:8080/');
		$port = $uri->getPort();
		$this->assertIsInt($port);
		$this->assertSame(8080, $port);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Assert a method on a class has an explicit return type matching $expected_type.
	 *
	 * @param string $class_name    Fully-qualified class name.
	 * @param string $method_name   Method to inspect.
	 * @param string $expected_type Expected type name (primitive or FQCN).
	 */
	private function assert_method_return_type(string $class_name, string $method_name, string $expected_type): void {
		$ref = new \ReflectionClass($class_name);
		$method = $ref->getMethod($method_name);
		$return_type = $method->getReturnType();
		$short_name = $ref->getShortName();

		$this->assertNotNull(
			$return_type,
			"{$short_name}::{$method_name}() must have an explicit return type declaration (psr/http-message v2 compatibility)"
		);
		$this->assertSame(
			$expected_type,
			(string) $return_type,
			"{$short_name}::{$method_name}() must declare return type '{$expected_type}'"
		);
	}

	/**
	 * Assert a method has a nullable int return type (?int).
	 *
	 * @param string $class_name  Fully-qualified class name.
	 * @param string $method_name Method to inspect.
	 */
	private function assert_method_nullable_int_return_type(string $class_name, string $method_name): void {
		$ref = new \ReflectionClass($class_name);
		$method = $ref->getMethod($method_name);
		$return_type = $method->getReturnType();
		$short_name = $ref->getShortName();

		$this->assertNotNull(
			$return_type,
			"{$short_name}::{$method_name}() must have an explicit return type declaration (psr/http-message v2 compatibility)"
		);
		$this->assertInstanceOf(
			\ReflectionNamedType::class,
			$return_type,
			"{$short_name}::{$method_name}() must have a named return type"
		);
		$this->assertTrue(
			$return_type->allowsNull(),
			"{$short_name}::{$method_name}() return type must be nullable (?int)"
		);
		$this->assertSame(
			'int',
			$return_type->getName(),
			"{$short_name}::{$method_name}() must have base type 'int' (i.e. ?int)"
		);
	}
}
