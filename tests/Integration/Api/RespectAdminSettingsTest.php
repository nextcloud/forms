<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Integration\Api;

use GuzzleHttp\Client;
use OCA\Forms\AppInfo\Application;
use OCA\Forms\Constants;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\IConfig;

/**
 * This tests that the API respects all admin settings
 * @group DB
 */
class RespectAdminSettingsTest extends IntegrationBase {
	/** @var GuzzleHttp\Client */
	private $http;

	protected array $users = [
		'test' => 'Test user',
	];

	/**
	 * Store Test Forms Array.
	 * Necessary as function due to object type-casting.
	 */
	private function setTestForms() {
		$this->testForms = [
			[
				'hash' => 'abcdefghij123456',
				'title' => 'Title of owned Form',
				'description' => '',
				'owner_id' => 'test',
				'access_enum' => 0,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'lockedBy' => null,
				'lockedUntil' => null,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'allowEditSubmissions' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => '',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [],
				'submissions' => [],
			],
			[
				'hash' => '1234567890abcdef',
				'title' => 'Title of a globally shared Form',
				'description' => '',
				'owner_id' => 'test1',
				'access_enum' => 2,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'lockedBy' => null,
				'lockedUntil' => null,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'allowEditSubmissions' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => '',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [],
				'submissions' => [],
			],
			[
				'hash' => 'bcdf011899881',
				'title' => 'Title of a directly shared Form',
				'description' => '',
				'owner_id' => 'test1',
				'access_enum' => 0,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'lockedBy' => null,
				'lockedUntil' => null,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'allowEditSubmissions' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => '',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [
					[
						'shareType' => 0,
						'shareWith' => 'test',
						'permissions' => ['submit'],
					],
				],
				'submissions' => [],
			],
		];
	}

	private static function sharedTestForms(): array {
		return [
			[
				'hash' => 'abcdefghij123456',
				'title' => 'Title of owned Form',
				'description' => '',
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'lockedBy' => null,
				'lockedUntil' => null,
				'questions' => [],
				'shares' => [],
				'ownerId' => 'test',
				'fileId' => null,
				'fileFormat' => null,
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'isAnonymous' => false,
				'submitMultiple' => false,
				'allowEditSubmissions' => false,
				'showExpiration' => false,
				'submissionMessage' => '',
				'permissions' => [
					'edit',
					'embed',
					'results',
					'results_delete',
					'submit',
				],
				'canSubmit' => true,
				'submissionCount' => 0,
				'confirmationEmailEnabled' => false,
				'confirmationEmailSubject' => null,
				'confirmationEmailBody' => null,
			],
		];
	}

	/**
	 * Set up test environment.
	 * Writing testforms into db, preparing http request
	 */
	public function setUp(): void {
		$this->setTestForms();
		$this->users = [
			'test' => 'Test Displayname',
			'user1' => 'User No. 1',
		];

		parent::setUp();

		// Set up http Client
		$this->http = new Client([
			'base_uri' => 'http://localhost:8080/ocs/v2.php/apps/forms/',
			'auth' => ['test', 'test'],
			'headers' => [
				'OCS-ApiRequest' => 'true',
				'Accept' => 'application/json'
			],
		]);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	// Small Wrapper for OCS-Response
	private function OcsResponse2Data($resp) {
		$arr = json_decode($resp->getBody()->getContents(), true);
		return $arr['ocs']['data'];
	}

	/**
	 * Allow to update form if there are no admin settings
	 */
	public function testAllowUpdate(): void {
		$resp = $this->http->request(
			'PATCH',
			"api/v3/forms/{$this->testForms[0]['id']}",
			[
				'json' => [
					'keyValuePairs' => ['access' => ['permitAllUsers' => true, 'showToAllUsers' => true]],
				],
			],
		);
		$this->assertEquals(200, $resp->getStatusCode());

		$resp = $this->http->request(
			'GET',
			"api/v3/forms/{$this->testForms[0]['id']}",
		);
		$data = $this->OcsResponse2Data($resp);
		// we do not know the ID and the update time is flaky
		unset($data['id']);
		unset($data['lastUpdated']);
		$data['lockedUntil'] = null;

		$expected = self::sharedTestForms()[0];
		$expected['access'] = ['permitAllUsers' => true, 'showToAllUsers' => true];
		$expected['lockedBy'] = 'test';

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	/**
	 * Forbid to update form if there are admin settings
	 * @dataProvider forbidUpdateAdminSettingsData
	 */
	public function testForbidUpdate(array $accessValue, array $adminConfigKeys): void {
		$config = \OCP\Server::get(IConfig::class);
		foreach ($adminConfigKeys as $key => $value) {
			$config->setAppValue(Application::APP_ID, $key, $value);
		}

		$resp = $this->http->request(
			'PATCH',
			"api/v3/forms/{$this->testForms[0]['id']}",
			[
				'json' => [
					'keyValuePairs' => ['access' => $accessValue],
				],
				// do not throw on 403
				'http_errors' => false,
			],
		);
		$this->assertEquals(403, $resp->getStatusCode());

		$resp = $this->http->request(
			'GET',
			"api/v3/forms/{$this->testForms[0]['id']}",
		);
		$data = $this->OcsResponse2Data($resp);
		// we do not know the ID or the update
		unset($data['id']);
		unset($data['lastUpdated']);
		$data['lockedUntil'] = null;

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals(self::sharedTestForms()[0], $data);
	}

	public static function forbidUpdateAdminSettingsData(): array {
		return [
			'set both without show-to-all permission' => [
				[
					'permitAllUsers' => true,
					'showToAllUsers' => true,
				],
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => 'false',
					Constants::CONFIG_KEY_ALLOWPERMITALL => 'true',
				],
			],
			'set both without permit-all permission' => [
				[
					'permitAllUsers' => true,
					'showToAllUsers' => true,
				],
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => 'true',
					Constants::CONFIG_KEY_ALLOWPERMITALL => 'false',
				],
			],
			'set show-to-all without permission' => [
				[
					'showToAllUsers' => true,
				],
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => 'false',
					Constants::CONFIG_KEY_ALLOWPERMITALL => 'true',
				],
			],
			'set permit-all without permission' => [
				[
					'permitAllUsers' => true,
				],
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => 'true',
					Constants::CONFIG_KEY_ALLOWPERMITALL => 'false',
				],
			],
		];
	}

	/**
	 * Test that forms with public access are listed
	 */
	public function testListFormsAllowed(): void {
		$resp = $this->http->request(
			'GET',
			'api/v3/forms?type=shared',
		);
		$this->assertEquals(200, $resp->getStatusCode());

		$data = $this->OcsResponse2Data($resp);
		$this->assertEqualsCanonicalizing(
			[
				'Title of a globally shared Form',
				'Title of a directly shared Form',
			],
			array_map(fn ($form) => $form['title'], $data),
		);
	}

	/**
	 * Test that only forms directly shared are listed if the admin setting forbid access to the form.
	 * Equivalent to creating form with "show to all" permission, but then the admin deactivates the "show all" globally.
	 */
	public function testListFormsNoAdminPermission(): void {
		// Disable global access
		\OCP\Server::get(IConfig::class)->setAppValue(Application::APP_ID, Constants::CONFIG_KEY_ALLOWPERMITALL, 'false');

		$resp = $this->http->request(
			'GET',
			'api/v3/forms?type=shared',
		);
		$this->assertEquals(200, $resp->getStatusCode());

		$data = $this->OcsResponse2Data($resp);
		$this->assertEqualsCanonicalizing(
			['Title of a directly shared Form'],
			array_map(fn ($form) => $form['title'], $data),
		);
	}

};
