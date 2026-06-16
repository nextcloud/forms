<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\BackgroundJob\SendConfirmationMailJob;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCP\AppFramework\Db\IMapperException;
use OCP\BackgroundJob\IJobList;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IMemcache;
use OCP\Mail\IEmailValidator;
use Psr\Log\LoggerInterface;

class ConfirmationEmailService {
	private const RATE_LIMIT_TTL = 86400; // 24 hours

	private readonly ICache $rateLimitCache;

	public function __construct(
		private readonly ConfigService $configService,
		private readonly AnswerMapper $answerMapper,
		private readonly QuestionMapper $questionMapper,
		private readonly IEmailValidator $emailValidator,
		private readonly IJobList $jobList,
		ICacheFactory $cacheFactory,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
		$this->rateLimitCache = $cacheFactory->createDistributed('forms_confirmation_email');
	}

	public function send(Form $form, Submission $submission): void {
		if (!$form->getConfirmationEmailEnabled()) {
			return;
		}

		if (!$this->configService->getAllowConfirmationEmail()) {
			$this->logger->debug('Confirmation email feature is disabled by administrator', [
				'formId' => $form->getId(),
			]);
			return;
		}

		$questions = $this->loadQuestions($form->getId());
		$answerMap = $this->buildAnswerMap($submission);

		$recipientQuestion = $this->findRecipientQuestion($form, $questions);
		if ($recipientQuestion === null) {
			if ($form->getConfirmationEmailQuestionId() !== null) {
				$this->logger->debug('Configured confirmation email recipient question is not a valid email question', [
					'formId' => $form->getId(),
					'submissionId' => $submission->getId(),
					'configuredQuestionId' => $form->getConfirmationEmailQuestionId(),
				]);
			}
			return;
		}

		$recipientEmail = $answerMap[$recipientQuestion['id']][0] ?? null;
		if ($recipientEmail === null || !$this->emailValidator->isValid($recipientEmail)) {
			$this->logger->debug('No valid email address found in submission for confirmation email', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
			]);
			return;
		}

		if (!$this->checkRateLimit($recipientEmail, $form->getId(), $submission->getId())) {
			return;
		}

		[$subject, $body] = $this->buildEmailContent($form, $questions, $answerMap);

		$this->jobList->add(SendConfirmationMailJob::class, [
			'recipient' => $recipientEmail,
			'subject' => $subject,
			'body' => $body,
			'formId' => $form->getId(),
			'submissionId' => $submission->getId(),
		]);
		$this->logger->debug('Confirmation email queued', [
			'formId' => $form->getId(),
			'submissionId' => $submission->getId(),
		]);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function validateRecipientQuestionId(Form $form, mixed $recipientId): void {
		if ($recipientId === null) {
			return;
		}

		if (!is_int($recipientId)) {
			throw new \InvalidArgumentException('Invalid confirmationEmailQuestionId');
		}

		try {
			$question = $this->questionMapper->findById($recipientId);
		} catch (IMapperException $e) {
			throw new \InvalidArgumentException('Invalid confirmationEmailQuestionId', previous: $e);
		}

		if ($question->getFormId() !== $form->getId()
			|| $question->getOrder() === 0
			|| !$question->isEmailType()) {
			throw new \InvalidArgumentException('Invalid confirmationEmailQuestionId');
		}
	}

	/**
	 * Load questions for a form. Skips options and file-type processing not needed for email.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function loadQuestions(int $formId): array {
		$questions = [];
		try {
			foreach ($this->questionMapper->findByForm($formId) as $entity) {
				$questions[] = $entity->read();
			}
		} catch (\Exception $e) {
			$this->logger->debug('Failed to load questions for confirmation email placeholder substitution', [
				'formId' => $formId,
				'exception' => $e,
			]);
		}
		return $questions;
	}

	/**
	 * @return array<int, string[]>
	 */
	private function buildAnswerMap(Submission $submission): array {
		$map = [];
		foreach ($this->answerMapper->findBySubmission($submission->getId()) as $answer) {
			$map[$answer->getQuestionId()][] = $answer->getText();
		}
		return $map;
	}

	/**
	 * @param list<array<string, mixed>> $questions
	 * @param array<int, string[]> $answerMap
	 * @return array{string, string}
	 */
	private function buildEmailContent(Form $form, array $questions, array $answerMap): array {
		$subject = $form->getConfirmationEmailSubject();
		$body = $form->getConfirmationEmailBody();

		if (empty($subject)) {
			$subject = $this->l10n->t('Thank you for your submission');
		}
		if (empty($body)) {
			$body = $this->l10n->t('Thank you for submitting the form "%s".', [$form->getTitle()]);
		}

		$replacements = [
			'{formTitle}' => $form->getTitle(),
			'{formDescription}' => $form->getDescription() ?? '',
		];

		foreach ($questions as $question) {
			$fieldKey = !empty($question['name'] ?? '') ? $question['name'] : ($question['text'] ?? '');
			$fieldKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fieldKey));
			if ($fieldKey === '' || empty($answerMap[$question['id']])) {
				continue;
			}
			$placeholder = '{' . $fieldKey . '}';
			if (isset($replacements[$placeholder])) {
				$this->logger->warning('Confirmation email placeholder key collision, skipping duplicate', [
					'formId' => $form->getId(),
					'key' => $fieldKey,
				]);
				continue;
			}
			$replacements[$placeholder] = implode('; ', $answerMap[$question['id']]);
		}

		return [
			str_replace(array_keys($replacements), array_values($replacements), $subject),
			str_replace(array_keys($replacements), array_values($replacements), $body),
		];
	}

	/**
	 * @param list<array<string, mixed>> $questions
	 * @return array<string, mixed>|null
	 */
	private function findRecipientQuestion(Form $form, array $questions): ?array {
		$recipientQuestionId = $form->getConfirmationEmailQuestionId();
		if ($recipientQuestionId === null) {
			return null;
		}

		foreach ($questions as $questionData) {
			if (($questionData['id'] ?? null) !== $recipientQuestionId) {
				continue;
			}

			if (Question::checkEmailType(
				$questionData['type'] ?? '',
				(array)($questionData['extraSettings'] ?? [])
			)) {
				return $questionData;
			}

			return null;
		}

		return null;
	}

	private function checkRateLimit(string $email, int $formId, int $submissionId): bool {
		$cacheKey = 'email_rl_' . hash('sha256', $formId . ':' . strtolower($email));

		if (!$this->rateLimitCache instanceof IMemcache) {
			// Atomic increment requires IMemcache; without it we cannot safely count.
			$this->logger->debug('Distributed cache unavailable, skipping confirmation email rate limit', [
				'formId' => $formId,
			]);
			return true;
		}

		$rateLimit = $this->configService->getConfirmationEmailRateLimit();

		if ($this->rateLimitCache->add($cacheKey, 1, self::RATE_LIMIT_TTL)) {
			$count = 1;
		} else {
			$count = $this->rateLimitCache->inc($cacheKey);
			if (!is_int($count)) {
				$this->logger->warning('Failed to increment confirmation email rate limit counter', [
					'formId' => $formId,
					'submissionId' => $submissionId,
				]);
				return false;
			}
		}

		if ($count > $rateLimit) {
			$this->logger->warning('Per-recipient confirmation email rate limit reached', [
				'formId' => $formId,
				'submissionId' => $submissionId,
			]);
			return false;
		}

		return true;
	}
}
