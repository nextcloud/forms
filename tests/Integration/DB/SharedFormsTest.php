<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Integration\Api;

use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;

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
}
