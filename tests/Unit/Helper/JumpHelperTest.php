<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Helper;

use OCA\Forms\Constants;
use OCA\Forms\Db\Question;
use OCA\Forms\Helper\JumpHelper;
use Test\TestCase;

class JumpHelperTest extends TestCase {
	public function testMapJumps(): void {
		$extraSettings = [
			'allowOtherAnswer' => true,
			'next' => 3,
			'goTo' => [
				['optionId' => 11, 'target' => 2],
				['optionId' => 12, 'target' => 'submit'],
				['optionId' => 13, 'target' => 2],
				['optionId' => 11, 'target' => 99],
				['other' => true, 'target' => 3],
			],
		];

		$this->assertSame([
			'allowOtherAnswer' => true,
			'next' => 30,
			'goTo' => [
				['optionId' => 110, 'target' => 20],
				['optionId' => 120, 'target' => 'submit'],
				['other' => true, 'target' => 30],
			],
		], JumpHelper::mapJumps($extraSettings, [2 => 20, 3 => 30], [11 => 110, 12 => 120]));
	}

	public function testMapJumpsKeepsTargetsWithoutQuestionMap(): void {
		$this->assertSame(
			['next' => 3, 'goTo' => [['optionId' => 110, 'target' => 2]]],
			JumpHelper::mapJumps(['next' => 3, 'goTo' => [['optionId' => 11, 'target' => 2]]], null, [11 => 110]),
		);
	}

	public function testMapJumpsRemovesUnmappedNextAndMalformedJumps(): void {
		$this->assertSame(
			['goTo' => []],
			JumpHelper::mapJumps(['next' => 9, 'goTo' => [5, ['optionId' => '11', 'target' => 2], ['target' => [1]]]], [2 => 20], [11 => 110]),
		);
	}

	public function testRemoveBackwardJumps(): void {
		$questions = array_map(static function (array $data): Question {
			$question = new Question();
			$question->setId($data[0]);
			$question->setExtraSettings($data[1]);
			return $question;
		}, [
			[1, ['next' => 3, 'goTo' => [['optionId' => 11, 'target' => 1], ['optionId' => 12, 'target' => 'submit']]]],
			[2, ['shuffleOptions' => true, 'goTo' => [['optionId' => 21, 'target' => 1], ['optionId' => 22, 'target' => 99]]]],
			[3, ['next' => 2]],
			[4, ['goTo' => []]],
		]);

		$changed = JumpHelper::removeBackwardJumps($questions);

		$this->assertSame([$questions[0], $questions[1], $questions[2]], $changed);
		$this->assertSame(['next' => 3, 'goTo' => [['optionId' => 12, 'target' => 'submit']]], $questions[0]->getExtraSettings());
		$this->assertSame(['shuffleOptions' => true, 'goTo' => [['optionId' => 22, 'target' => 99]]], $questions[1]->getExtraSettings());
		$this->assertSame([], $questions[2]->getExtraSettings());
		$this->assertSame(['goTo' => []], $questions[3]->getExtraSettings());
	}

	public function testMapJumpsOfCopies(): void {
		$questions = array_map(static function (array $data): Question {
			$question = new Question();
			$question->setId($data[0]);
			$question->setOrder($data[1]);
			$question->setType($data[3]);
			$question->setExtraSettings($data[2]);
			return $question;
		}, [
			[30, 3, ['next' => 1], Constants::ANSWER_TYPE_SHORT],
			[10, 1, ['goTo' => [['optionId' => 7, 'target' => 3], ['other' => true, 'target' => 2]], 'next' => 3], Constants::ANSWER_TYPE_MULTIPLEUNIQUE],
			[20, 2, ['shuffleOptions' => true], Constants::ANSWER_TYPE_MULTIPLEUNIQUE],
		]);

		$changed = JumpHelper::mapJumpsOfCopies($questions, [1 => 10, 2 => 20, 3 => 30], [10 => [7 => 70]]);

		$this->assertSame([$questions[0], $questions[1]], $changed);
		$this->assertSame([], $questions[0]->getExtraSettings());
		$this->assertSame(['goTo' => [['optionId' => 70, 'target' => 30], ['other' => true, 'target' => 20]]], $questions[1]->getExtraSettings());
		$this->assertSame(['shuffleOptions' => true], $questions[2]->getExtraSettings());
	}

	public function testMapJumpsOfCopiesWithoutJumpsLeavesTheCopiesAlone(): void {
		$question = new Question();
		$question->setOrder(1);
		$question->setExtraSettings(['shuffleOptions' => true]);

		$this->assertSame([], JumpHelper::mapJumpsOfCopies([$question], [], []));
		$this->assertSame(['shuffleOptions' => true], $question->getExtraSettings());
	}

	public function testMapJumpsOfCopiesDropsJumpsPerAnswerWithAnInvalidStructure(): void {
		$question = new Question();
		$question->setId(10);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_MULTIPLEUNIQUE);
		$question->setExtraSettings(['goTo' => [
			['optionId' => 7, 'target' => 2, 'label' => 'x'],
			['target' => 2],
			['optionId' => 7, 'target' => 'submit'],
			['optionId' => 7, 'target' => 2],
			['other' => true, 'target' => 2],
		]]);
		$target = new Question();
		$target->setId(20);
		$target->setOrder(2);
		$target->setType(Constants::ANSWER_TYPE_SHORT);

		$this->assertSame([$question], JumpHelper::mapJumpsOfCopies([$question, $target], [2 => 20], [10 => [7 => 70]]));
		$this->assertSame(['goTo' => [['optionId' => 70, 'target' => 'submit'], ['other' => true, 'target' => 20]]], $question->getExtraSettings());
	}
}
