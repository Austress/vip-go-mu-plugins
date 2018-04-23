<?php

namespace Automattic\VIP\Files;

use WP_Error;

class API_Client_Test extends \WP_UnitTestCase {
	private $http_requests;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-api-client.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->api_client = new API_Client( 'https://files.go-vip.co', 123456, 'super-sekret-token' );

		$this->http_requests = [];
	}

	public function tearDown() {
		$this->api_client = null;
		$this->http_requests = null;

		remove_all_filters( 'pre_http_request' );

		parent::tearDown();
	}

	public function mock_http_response( $mocked_response ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response ) {
			$this->http_requests[] = [
				'url' => $url,
				'args' => $args,
			];

			return $mocked_response;
		}, 10, 3 );
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\API_Client' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function test__call_api() {
		$this->mock_http_response( [] ); // don't care about the response

		$call_api_method = self::get_method( 'call_api' );

		$call_api_method->invokeArgs( $this->api_client, [
			'/path/to/image.jpg',
			'POST',
			[
				'headers' => [
					'Another-Header' => 'Yay!',
				],
			]
		] );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/path/to/image.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'POST', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertEquals( 10, $actual_http_request['args']['timeout'], 'Incorrect timeout' );
		$this->assertEquals( [
			'X-Client-Site-ID' => 123456,
			'X-Access-Token' => 'super-sekret-token',
			'Another-Header' => 'Yay!',
		], $actual_http_request['args']['headers'], 'Incorrect headers' );
	}

	public function get_test_data__get_api_url() {
		return [
			'path_with_leadingslash' => [
				'/path/to/image.jpg',
				'https://files.go-vip.co/path/to/image.jpg',
			],
			'path_without_leadingslash' => [
				'another/path/to/image.jpg',
				'https://files.go-vip.co/another/path/to/image.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_api_url
	 */
	public function test__get_api_url( $path, $expected_url ) {
		$actual_url = $this->api_client->get_api_url( $path );

		$this->assertEquals( $expected_url, $actual_url );
	}

	public function get_test_data__is_file() {
		return [
			'WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],
			'other-status' => [
				[
					'response' => [
						'code' => 401,
					]
				],
				new WP_Error( 'is_file-failed', 'Failed to check if file `/file.jpg` exists (response code: 401)' ),
			],
			'invalid-file' => [
				[
					'response' => [
						'code' => 404,
					]
				],
				false,
			],
			'valid-file' => [
				[
					'response' => [
						'code' => 200,
					]
				],
				true,
			]
		];
	}

	/**
	 * @dataProvider get_test_data__is_file
	 */
	public function test__is_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->is_file( '/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__is_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->is_file( '/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertArraySubset( [
			'X-Action' => 'file_exists'
		], $actual_http_request['args']['headers'], 'Missing `X-Action` header' );
	}

	public function get_test_data__delete_file() {
		return [
			'WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],

			'delete-failed' => [
				[
					'response' => [
						'code' => 500,
					]
				],
				new WP_Error( 'delete_file-failed', 'Failed to delete file `/file.jpg` (response code: 500)' ),
			],

			'delete-succeeded' => [
				[
					'response' => [
						'code' => 200,
					]
				],
				true,
			]
		];
	}

	/**
	 * @dataProvider get_test_data__delete_file
	 */
	public function test__delete_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->delete_file( '/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__delete_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->delete_file( '/delete/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/delete/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'DELETE', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
	}

	public function get_test_data__get_file() {
		return [
			'WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],

			'file-does-not-exist' => [
				[
					'response' => [
						'code' => 404,
					],
					'body' => null,
				],
				new WP_Error( 'get_file-failed', 'Failed to get file `/file.jpg` (response code: 404)' ),
			],

			'file-exists' => [
				[
					'response' => [
						'code' => 200,
					],
					'body' => 'these-are-my-file-contents',
				],
				'these-are-my-file-contents',
			]
		];
	}

	/**
	 * @dataProvider get_test_data__get_file
	 */
	public function test__get_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->get_file( '/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__get_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->get_file( '/get/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/get/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
	}

	public function get_test_data__upload_timeout() {
		return [
			'empty-file' => [
				0,
				10,
			],

			'1kb' => [
				1024,
				10,
			],

			'500kb' => [
				512000,
				11,
			],

			'1GB' => [
				1073741824,
				2107,
			]
		];
	}

	/**
	 * @dataProvider get_test_data__upload_timeout
	 */
	public function test__calculate_upload_timeout( $file_size, $expected_timeout ) {
		$calculate_upload_timeout_method = self::get_method( 'calculate_upload_timeout' );

		$actual_timeout = $calculate_upload_timeout_method->invokeArgs( $this->api_client, [
			$file_size,
		] );

		$this->assertEquals( $expected_timeout, $actual_timeout );
	}

	public function test__upload_file__invalid_file() {
		$file_path = '/path/to/invalid/file.txt';
		$upload_path = '/wp-content/uploads/file.txt';
		$expected_error_code = 'upload_file-failed-invalid_path';

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertWPError( $actual_result, 'WP_Error not returned' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Unexpected error code' );
	}

	public function test__upload_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$file_path = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$this->api_client->upload_file( $file_path, $upload_path );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/file.txt', $actual_http_request['url'], 'Incorrect API URL' );

		$this->assertArraySubset( [
			'Content-Type' => 'image/jpeg',
			'Content-Length' => 13,
			'Connection' => 'Keep-Alive',
		], $actual_http_request['args']['headers'], 'Missing `Content-*` headers' );

		$this->assertEquals( 10, $actual_http_request['args']['timeout'], 'Incorrect timeout' );
	}

	public function get_test_data__upload_file__errors() {
		return [
			'return-WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				'oh-no',
			],

			'status-204' => [
				[
					'response' => [
						'code' => 204,
					],
				],
				'upload_file-failed-quota_reached'
			],

			'status-non-200' => [
				[
					'response' => [
						'code' => 400,
					],
				],
				'upload_file-failed'
			],

			'invalid-json' => [
				[
					'response' => [
						'code' => 200,
					],
					'body' => '{{{',
				],
				'upload_file-failed-json_decode-error'
			],
		];
	}

	/**
	 * @dataProvider get_test_data__upload_file__errors
	 */
	public function test__upload_file__error( $mocked_response, $expected_error_code ) {
		$this->mock_http_response( $mocked_response );

		$file_path = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__upload_file__success() {
		$this->mock_http_response( [
			'response' => [
				'code' => 200,
			],
			'body' => '{"filename":"/wp-content/uploads/file.txt"}',
		] );

		$file_path = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertEquals( $upload_path, $actual_result );
	}

}