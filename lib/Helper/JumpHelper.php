<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Helper;

use OCA\Forms\Constants;
use OCA\Forms\Db\Question;
use OCA\Forms\Service\FormsService;

/**
 * The rules of jumps between questions: validating, copying and removing them
 */
final class JumpHelper {
	/**
	 * Whether the value is a jump target: the id of a question or the end of the form
	 *
	 * @param mixed $target the value
	 */
	public static function isJumpTarget(mixed $target): bool {
		return (is_int($target) && $target > 0) || $target === Constants::JUMP_TARGET_SUBMIT;
	}

	/**
	 * Whether the value is a list of jumps per answer, at most one per answer
	 *
	 * @param mixed $goTo the value of `goTo`
	 * @param string $questionType the question type
	 */
	public static function isGoToValid(mixed $goTo, string $questionType): bool {
		if (!is_array($goTo) || !array_is_list($goTo)) {
			return false;
		}

		$answers = [];
		foreach ($goTo as $jump) {
			$answer = self::getJumpAnswer($jump, $questionType);
			if ($answer === null || isset($answers[$answer])) {
				return false;
			}
			$answers[$answer] = true;
		}

		return true;
	}

	/**
	 * The answer of a jump per answer with one answer key and a target: `value:<value>` for a value from 0 to 10 of a linear scale, `option:<id>` for an option, `other` for the answer "Other" of a radio question
	 *
	 * @param mixed $jump the jump
	 * @param string $questionType the question type
	 * @return ?string the answer, `null` for a jump with an invalid structure or for an answer the question type has no jumps for
	 */
	private static function getJumpAnswer(mixed $jump, string $questionType): ?string {
		if (!is_array($jump) || count($jump) !== 2 || !self::isJumpTarget($jump['target'] ?? null)) {
			return null;
		}
		if ($questionType === Constants::ANSWER_TYPE_LINEARSCALE) {
			return is_int($jump['value'] ?? null) && $jump['value'] >= 0 && $jump['value'] <= 10 ? 'value:' . $jump['value'] : null;
		}
		if (is_int($jump['optionId'] ?? null) && $jump['optionId'] > 0) {
			return 'option:' . $jump['optionId'];
		}
		if (($jump['other'] ?? null) === true && $questionType === Constants::ANSWER_TYPE_MULTIPLEUNIQUE) {
			return 'other';
		}
		return null;
	}

	/**
	 * The extra settings of a copied question, with its jumps pointing at the copied questions and options.
	 * Jumps whose target or option has no copy are removed.
	 *
	 * @param array $extraSettings the extra settings of the original question
	 * @param ?array<int, int> $questionIdMap the ids of the copied questions by original id, `null` keeps the targets
	 * @param array<int, int> $optionIdMap the ids of the copied options by original id
	 * @return array the extra settings of the copy
	 */
	public static function mapJumps(array $extraSettings, ?array $questionIdMap, array $optionIdMap): array {
		$mapTarget = static function (mixed $target) use ($questionIdMap): string|int|null {
			if (!is_int($target)) {
				return $target === Constants::JUMP_TARGET_SUBMIT ? $target : null;
			}
			return $questionIdMap === null ? $target : ($questionIdMap[$target] ?? null);
		};

		if (array_key_exists('next', $extraSettings)) {
			$next = $mapTarget($extraSettings['next']);
			if ($next === null) {
				unset($extraSettings['next']);
			} else {
				$extraSettings['next'] = $next;
			}
		}

		if (array_key_exists('goTo', $extraSettings)) {
			$goTo = [];
			foreach (is_array($extraSettings['goTo']) ? $extraSettings['goTo'] : [] as $jump) {
				if (!is_array($jump)) {
					continue;
				}
				$jump['target'] = $mapTarget($jump['target'] ?? null);
				if (array_key_exists('optionId', $jump)) {
					$jump['optionId'] = is_int($jump['optionId']) ? ($optionIdMap[$jump['optionId']] ?? null) : null;
					if ($jump['optionId'] === null) {
						continue;
					}
				}
				if ($jump['target'] !== null) {
					$goTo[] = $jump;
				}
			}
			$extraSettings['goTo'] = $goTo;
		}

		return $extraSettings;
	}

	/**
	 * Point the jumps of copied questions at the copied questions and options, and remove the jumps that go to the question itself, to an earlier question or into the middle of another section, jumps per answer with an invalid structure or for an answer with a jump already, and `goTo` or `next` on question types without it
	 *
	 * @param list<Question> $questions the copied questions
	 * @param array<int, int> $questionIdMap the ids of the copied questions by original id
	 * @param array<int, array<int, int>> $optionIdMaps the ids of the copied options by original id, per id of the copied question
	 * @return list<Question> the copied questions with jumps, with their new extra settings set
	 */
	public static function mapJumpsOfCopies(array $questions, array $questionIdMap, array $optionIdMaps): array {
		$copiesWithJumps = [];
		foreach ($questions as $question) {
			$extraSettings = $question->getExtraSettings();
			if (array_key_exists('goTo', $extraSettings) || array_key_exists('next', $extraSettings)) {
				$allowed = FormsService::getAllowedExtraSettings($question->getType());
				if (!isset($allowed['goTo'])) {
					unset($extraSettings['goTo']);
				}
				if (!isset($allowed['next'])) {
					unset($extraSettings['next']);
				}
				$extraSettings = self::mapJumps($extraSettings, $questionIdMap, $optionIdMaps[$question->getId()] ?? []);
				if (array_key_exists('goTo', $extraSettings)) {
					$goTo = [];
					foreach ($extraSettings['goTo'] as $jump) {
						$answer = self::getJumpAnswer($jump, $question->getType());
						if ($answer !== null && !isset($goTo[$answer])) {
							$goTo[$answer] = $jump;
						}
					}
					$extraSettings['goTo'] = array_values($goTo);
				}
				$question->setExtraSettings($extraSettings);
				self::removeJumpsOutsideScale($question);
				$copiesWithJumps[] = $question;
			}
		}

		if ($copiesWithJumps !== []) {
			usort($questions, static fn (Question $a, Question $b): int => $a->getOrder() <=> $b->getOrder());
			self::removeDisallowedJumps($questions);
		}

		return $copiesWithJumps;
	}

	/**
	 * Remove the jumps that go to the question itself, to an earlier question or into the middle of another section, and the jumps of a section to anything but a later section, keeping the jumps to the end of the form, to later questions of the same section, to later sections and to questions that are not in the list
	 *
	 * @param list<Question> $questions the questions of the form in their order
	 * @return list<Question> the questions whose jumps were removed, with their new extra settings set
	 */
	public static function removeDisallowedJumps(array $questions): array {
		$positions = array_flip(array_map(static fn (Question $question): int => $question->getId(), $questions));
		$sections = [];
		$section = 0;
		foreach ($questions as $position => $question) {
			if ($question->getType() === Constants::ANSWER_TYPE_SECTION) {
				$section++;
			}
			$sections[$position] = $section;
		}
		$changed = [];

		foreach ($questions as $position => $question) {
			$isDisallowed = static fn (mixed $target): bool => is_int($target) && isset($positions[$target])
				&& ($positions[$target] <= $position
					|| ($questions[$positions[$target]]->getType() !== Constants::ANSWER_TYPE_SECTION
						&& ($question->getType() === Constants::ANSWER_TYPE_SECTION || $sections[$positions[$target]] !== $sections[$position])));
			$extraSettings = $question->getExtraSettings();
			$newSettings = $extraSettings;

			if ($isDisallowed($extraSettings['next'] ?? null)) {
				unset($newSettings['next']);
			}
			if (is_array($extraSettings['goTo'] ?? null)) {
				$goTo = array_values(array_filter($extraSettings['goTo'], static fn (mixed $jump): bool => !$isDisallowed(is_array($jump) ? ($jump['target'] ?? null) : null)));
				if (count($goTo) !== count($extraSettings['goTo'])) {
					$newSettings['goTo'] = $goTo;
				}
			}

			if ($newSettings !== $extraSettings) {
				$question->setExtraSettings($newSettings);
				$changed[] = $question;
			}
		}

		return $changed;
	}

	/**
	 * Remove the jumps of a linear scale for values outside its bounds
	 *
	 * @param Question $question the question
	 * @return bool whether jumps were removed, with the new extra settings set
	 */
	public static function removeJumpsOutsideScale(Question $question): bool {
		$extraSettings = $question->getExtraSettings();
		if ($question->getType() !== Constants::ANSWER_TYPE_LINEARSCALE || !is_array($extraSettings['goTo'] ?? null)) {
			return false;
		}

		$lowest = $extraSettings['optionsLowest'] ?? 1;
		$highest = $extraSettings['optionsHighest'] ?? 5;
		$goTo = array_values(array_filter($extraSettings['goTo'], static fn (mixed $jump): bool => !is_int(is_array($jump) ? ($jump['value'] ?? null) : null) || ($jump['value'] >= $lowest && $jump['value'] <= $highest)));
		if (count($goTo) === count($extraSettings['goTo'])) {
			return false;
		}

		$extraSettings['goTo'] = $goTo;
		$question->setExtraSettings($extraSettings);
		return true;
	}
}
