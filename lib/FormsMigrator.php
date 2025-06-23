<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use OCP\UserMigration\UserMigrationException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class FormsMigrator implements IMigrator {
	use TMigratorBasicVersionHandling;

	private const PATH_ROOT = Application::APP_ID . '/';
	private const PATH_MYAPP_FILE = FormsMigrator::PATH_ROOT . 'forms.json';

	public function __construct(
		private AnswerMapper $answerMapper,
		private FormMapper $formMapper,
		private OptionMapper $optionMapper,
		private QuestionMapper $questionMapper,
		private SubmissionMapper $submissionMapper,
		private FormsService $formsService,
		private SubmissionService $submissionService,
		private IL10N $l10n,
		private IUserManager $userManager,
	) {
	}

	/**
	 * Export user data
	 *
	 * @throws UserMigrationException
	 */
	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$output->writeln('Exporting forms information in ' . FormsMigrator::PATH_MYAPP_FILE . '…');

		try {
			$data = [];

			$forms = $this->formMapper->findAllByOwnerId($user->getUID());
			foreach ($forms as $form) {
				$formData = $form->read();
				$formData['questions'] = $this->formsService->getQuestions($formData['id']);
				$formData['submissions'] = $this->submissionService->getSubmissions($formData['id']);

				// Unset ids and hash as we will anyways create new ones on import. UID is known through export.
				unset($formData['id']);
				unset($formData['hash']);
				unset($formData['ownerId']);
				foreach ($formData['questions'] as $qKey => $question) {
					// Do NOT unset ID of question here, as it is necessary for answers.
					unset($formData['questions'][$qKey]['formId']);
					foreach ($question['options'] as $oKey => $option) {
						unset($formData['questions'][$qKey]['options'][$oKey]['id']);
						unset($formData['questions'][$qKey]['options'][$oKey]['questionId']);
					}
				}
				foreach ($formData['submissions'] as $sKey => $submission) {
					unset($formData['submissions'][$sKey]['id']);
					unset($formData['submissions'][$sKey]['formId']);
					foreach ($submission['answers'] as $aKey => $answer) {
						// Do NOT unset questionId here, it is necessary to identify question/answers.
						unset($formData['submissions'][$sKey]['answers'][$aKey]['id']);
						unset($formData['submissions'][$sKey]['answers'][$aKey]['submissionId']);
					}
				}

				// Mark userIds with instance.
				foreach ($formData['submissions'] as $sKey => $submission) {
					// Anonymous submission or already migrated, just keep it.
					if (substr($submission['userId'], 0, 10) === 'anon-user-'
						|| substr($submission['userId'], 0, 8) === 'unknown~') {
						continue;
					}

					// Try loading federated UserId, otherwise just mark userId as unknown.
					$exportId = '';
					$userEntity = $this->userManager->get($submission['userId']);
					if ($userEntity instanceof IUser) {
						$exportId = $userEntity->getCloudId();
					} else {
						// Fallback, should not occur regularly.
						$exportId = 'unknown~' . $submission['userId'];
					}
					$formData['submissions'][$sKey]['userId'] = $exportId;
				}

				// Add to catalog
				$data[] = $formData;
			}

			$exportDestination->addFileContents(FormsMigrator::PATH_MYAPP_FILE, json_encode($data));
		} catch (Throwable $e) {
			throw new UserMigrationException('Could not export forms', 0, $e);
		}
	}

	/**
	 * Import user data
	 *
	 * @throws UserMigrationException
	 */
	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No version for ' . static::class . ', skipping import…');
			return;
		}

		$output->writeln('Importing forms information from ' . FormsMigrator::PATH_MYAPP_FILE . '…');

		$data = json_decode($importSource->getFileContents(FormsMigrator::PATH_MYAPP_FILE), true, 512, JSON_THROW_ON_ERROR);
		try {
			foreach ($data as $formData) {
				$form = new Form();
				$form->setHash($this->formsService->generateFormHash());
				$form->setTitle($formData['title']);
				$form->setDescription($formData['description']);
				$form->setOwnerId($user->getUID());
				$form->setAccess($formData['access']);
				$form->setExpires($formData['expires']);
				$form->setIsAnonymous($formData['isAnonymous']);
				$form->setSubmitMultiple($formData['submitMultiple']);
				$form->setAllowEditSubmissions($formData['allowEditSubmissions']);
				$form->setShowExpiration($formData['showExpiration']);

				$this->formMapper->insert($form);

				$questionIdMap = [];
				foreach ($formData['questions'] as $questionData) {
					$question = new Question();
					$question->setFormId($form->getId());
					$question->setOrder($questionData['order']);
					$question->setType($questionData['type']);
					$question->setIsRequired($questionData['isRequired']);
					$question->setText($questionData['text']);
					$question->setDescription($questionData['description']);
					$question->setExtraSettings($questionData['extraSettings']);

					$this->questionMapper->insert($question);

					// Store QuestionId to map Answers.
					$questionIdMap[$questionData['id']] = $question->getId();

					foreach ($questionData['options'] as $optionData) {
						$option = new Option();
						$option->setQuestionId($question->getId());
						$option->setText($optionData['text']);

						$this->optionMapper->insert($option);
					}
				}

				foreach ($formData['submissions'] as $submissionData) {
					$submission = new Submission();
					$submission->setFormId($form->getId());
					$submission->setUserId($submissionData['userId']);
					$submission->setTimestamp($submissionData['timestamp']);

					$this->submissionMapper->insert($submission);

					foreach ($submissionData['answers'] as $answerData) {
						$answer = new Answer();
						$answer->setSubmissionId($submission->getId());
						$answer->setQuestionId($questionIdMap[$answerData['questionId']]);
						$answer->setText($answerData['text']);

						$this->answerMapper->insert($answer);
					}
				}
			}
		} catch (Throwable $e) {
			throw new UserMigrationException('Could not properly import forms information', 0, $e);
		}
	}

	/**
	 * Unique AppID
	 */
	public function getId(): string {
		return 'forms';
	}

	/**
	 * App display name
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Forms');
	}

	/**
	 * Description for Data-Export
	 */
	public function getDescription(): string {
		return $this->l10n->t('Forms including questions and submissions');
	}
}
