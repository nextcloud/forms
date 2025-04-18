<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Integration\Db;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\IConfig;

/**
 * @group DB
 */
class SharedFormsTest extends IntegrationBase {

	public function setUp(): void {
		$this->users = ['test' => 'Test user', 'user1' => 'User no. 1'];

		$this->testForms = [
			[
				'hash' => 'aaaa',
				'title' => 'Title of a Form',
				'description' => 'Just a simple form.',
				'owner_id' => 'test',
				'access_enum' => Constants::FORM_ACCESS_NOPUBLICSHARE,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => 'Back to website',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [
					[
						'shareType' => 0,
						'shareWith' => 'user1',
						'permissions' => ['submit'],
					],
				],
			],
			[
				'hash' => 'bbbb',
				'title' => 'Title of a public Form',
				'description' => '',
				'owner_id' => 'test',
				'access_enum' => Constants::FORM_ACCESS_SHOWTOALLUSERS,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => 'Back to website',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [],
			],
			[
				'hash' => 'cccc',
				'title' => 'Title of a public invisible Form',
				'description' => '',
				'owner_id' => 'test',
				'access_enum' => Constants::FORM_ACCESS_PERMITALLUSERS,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => 'Back to website',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [],
			],
			[
				'hash' => 'dddd',
				'title' => 'Shown AND shared form',
				'description' => 'Just a simple form.',
				'owner_id' => 'test',
				'access_enum' => Constants::FORM_ACCESS_SHOWTOALLUSERS,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => 'Back to website',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [
					[
						'shareType' => 0,
						'shareWith' => 'user1',
						'permissions' => ['submit'],
					],
					// two shares to the same user - valid but should result in just one result entry
					[
						'shareType' => 0,
						'shareWith' => 'user1',
						'permissions' => ['submit'],
					],
				],
			],
			[
				'hash' => 'eeee',
				'title' => 'Unrelated form',
				'description' => 'Just a simple form.',
				'owner_id' => 'test',
				'access_enum' => Constants::FORM_ACCESS_NOPUBLICSHARE,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => 'Back to website',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [
					[
						'shareType' => 0,
						'shareWith' => 'user2',
						'permissions' => ['submit'],
					],
				],
			],
		];

		parent::setUp();
	}

	/**
	 * Test that only shared forms that are shown to user are listed
	 */
	public function testShownSharedForms() {
		$formMapper = \OCP\Server::get(FormMapper::class);
		$forms = $formMapper->findSharedForms('user1');

		$this->assertEquals(3, count($forms));
		$this->assertEqualsCanonicalizing(
			['aaaa', 'bbbb', 'dddd'],
			array_map(fn ($form) => $form->read()['hash'], $forms),
		);
	}

	/**
	 * Test that all forms shared and public permit are shown without the `filterShown` parameter
	 */
	public function testPublicSharedForms() {
		$formMapper = \OCP\Server::get(FormMapper::class);
		$forms = $formMapper->findSharedForms('user1', filterShown: false);

		$this->assertEquals(4, count($forms));
		$this->assertEqualsCanonicalizing(
			['aaaa', 'bbbb', 'cccc', 'dddd'],
			array_map(fn ($form) => $form->read()['hash'], $forms),
		);
	}

	/**
	 * Test that no public shared forms are shown to user if admin disabled it
	 * @dataProvider dataForbidPublicShowAccess
	 */
	public function testShowNoSharedFormsIfDisabled(array $configValues) {
		$config = \OCP\Server::get(IConfig::class);
		foreach ($configValues as $key => $value) {
			$config->setAppValue(Application::APP_ID, $key, json_encode($value));
		}

		$formMapper = \OCP\Server::get(FormMapper::class);
		$forms = $formMapper->findSharedForms('user1');

		$this->assertEquals(2, count($forms));
		$this->assertEqualsCanonicalizing(
			['aaaa', 'dddd'],
			array_map(fn ($form) => $form->read()['hash'], $forms),
		);
	}

	/**
	 * Test that a form with public access can be accessed even if show permissions are not granted (can fill out but not see in sidebar)
	 */
	public function testAllowPublicAccessOnDeniedPublicVisibility(): void {
		$config = \OCP\Server::get(IConfig::class);
		$config->setAppValue(Application::APP_ID, Constants::CONFIG_KEY_ALLOWSHOWTOALL, json_encode(false));

		$formMapper = \OCP\Server::get(FormMapper::class);
		$forms = $formMapper->findSharedForms('user1', filterShown: false);

		$this->assertEqualsCanonicalizing(
			['aaaa', 'bbbb', 'cccc', 'dddd'],
			array_map(fn ($form) => $form->read()['hash'], $forms),
		);
	}

	/**
	 * Test that no public shared forms are available to user if admin disabled it
	 * @dataProvider dataForbidPublicAccess
	 */
	public function testShowNoSharedFormsAccessIfDisabled(array $configValues): void {
		$config = \OCP\Server::get(IConfig::class);
		foreach ($configValues as $key => $value) {
			$config->setAppValue(Application::APP_ID, $key, json_encode($value));
		}

		$formMapper = \OCP\Server::get(FormMapper::class);
		$forms = $formMapper->findSharedForms('user1', filterShown: false);

		$this->assertEquals(2, count($forms));
		$this->assertEqualsCanonicalizing(
			['aaaa', 'dddd'],
			array_map(fn ($form) => $form->read()['hash'], $forms),
		);
	}

	public static function dataForbidPublicAccess(): array {
		return [
			'no-permit' => [
				[
					Constants::CONFIG_KEY_ALLOWPERMITALL => false,
				],
			],
			'non-at-all' => [
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => false,
					Constants::CONFIG_KEY_ALLOWPERMITALL => false,
				],
			],
		];
	}

	public static function dataForbidPublicShowAccess(): array {
		return array_merge(self::dataForbidPublicAccess(), [
			'no-show-to-all' => [
				[
					Constants::CONFIG_KEY_ALLOWSHOWTOALL => false,
				],
			],
		]);
	}
}
