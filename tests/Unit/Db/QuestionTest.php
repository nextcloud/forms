<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Db;

use OCA\Forms\Constants;
use OCA\Forms\Db\Question;
use Test\TestCase;

class QuestionTest extends TestCase {
	/**
	 * @dataProvider isEmailTypeData
	 */
	public function testIsEmailTypeStatic(string $type, array $extraSettings, bool $expected): void {
		$this->assertSame($expected, Question::isEmailTypeStatic($type, $extraSettings));
	}

	public static function isEmailTypeData(): array {
		return [
			'valid-email' => [
				Constants::ANSWER_TYPE_SHORT,
				['validationType' => 'email'],
				true
			],
			'invalid-type-long' => [
				Constants::ANSWER_TYPE_LONG,
				['validationType' => 'email'],
				false
			],
			'invalid-validation-type-text' => [
				Constants::ANSWER_TYPE_SHORT,
				['validationType' => 'text'],
				false
			],
			'invalid-validation-type-none' => [
				Constants::ANSWER_TYPE_SHORT,
				[],
				false
			],
			'invalid-type-multiple' => [
				Constants::ANSWER_TYPE_MULTIPLE,
				['validationType' => 'email'],
				false
			],
		];
	}

	public function testIsEmailTypeEntity(): void {
		$question = new Question();
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setExtraSettings(['validationType' => 'email']);

		$this->assertTrue($question->isEmailType());

		$question->setExtraSettings(['validationType' => 'text']);
		$this->assertFalse($question->isEmailType());
	}
}
