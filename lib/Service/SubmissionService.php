<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use DateTime;
use DateTimeZone;

use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;

use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\UploadedFileMapper;
use OCA\Forms\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type FormsSubmission from ResponseDefinitions
 * @psalm-import-type FormsAnswer from ResponseDefinitions
 */
class SubmissionService {
	private ?IUser $currentUser;

	public function __construct(
		private QuestionMapper $questionMapper,
		private SubmissionMapper $submissionMapper,
		private AnswerMapper $answerMapper,
		private UploadedFileMapper $uploadedFileMapper,
		private IRootFolder $rootFolder,
		private IConfig $config,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		IUserSession $userSession,
		private IMailer $mailer,
		private ITempManager $tempManager,
		private FormsService $formsService,
		private IUrlGenerator $urlGenerator,
	) {
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Get all the answers of a given submission
	 *
	 * @param int $submissionId the submission id
	 * @return list<FormsAnswer>
	 */
	private function getAnswers(int $submissionId): array {
		$answerList = [];
		try {
			$answerEntities = $this->answerMapper->findBySubmission($submissionId);
			foreach ($answerEntities as $answerEntity) {
				$answerList[] = $answerEntity->read();
			}
		} catch (DoesNotExistException $e) {
			//Just ignore, if no Data. Returns empty Answers-Array
		} finally {
			return $answerList;
		}
	}

	/**
	 * Get all submissions of a form
	 *
	 * @param int $formId the form id
	 * @param string|null $userId optional user id to filter submissions
	 * @param string|null $query optional search query to filter submissions
	 * @param int|null $limit the maximum number of submissions to return
	 * @param int $offset the number of submissions to skip
	 * @return list<array{
	 *     id: int,
	 *     formId: int,
	 *     userId: string,
	 *     timestamp: int,
	 *     answers: list<FormsAnswer>,
	 * }>
	 */
	public function getSubmissions(int $formId, ?string $userId = null, ?string $query = null, ?int $limit = null, int $offset = 0): array {
		$submissionList = [];
		try {
			$submissionEntities = $this->submissionMapper->findByForm($formId, $userId, $query, $limit, $offset);

			foreach ($submissionEntities as $submissionEntity) {
				$submission = $submissionEntity->read();
				$submission['answers'] = $this->getAnswers($submission['id']);
				$submissionList[] = $submission;
			}
		} catch (DoesNotExistException $e) {
			// Just ignore, if no Data. Returns empty Submissions-Array
		} finally {
			return $submissionList;
		}
	}

	/**
	 * Load specific submission
	 *
	 * @param integer $submissionId id of the submission
	 * @return array{
	 *     id: int,
	 *     formId: int,
	 *     userId: string,
	 *     timestamp: int,
	 *     answers: list<FormsAnswer>,
	 * }
	 */
	public function getSubmission(int $submissionId): ?array {
		try {
			$submissionEntity = $this->submissionMapper->findById($submissionId);
			$submission = $submissionEntity->read();
			$submission['answers'] = $this->getAnswers($submission['id']);
			return $submission;
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Export Submissions to Cloud-Filesystem
	 *
	 * @param Form $form the form
	 * @param string $path The Cloud-Path to export to
	 * @param string $fileFormat Format to export to
	 * @param string $ownerId of the form creator
	 * @return File The written file
	 * @throws NotPermittedException
	 */
	public function writeFileToCloud(Form $form, string $path, string $fileFormat, ?string $ownerId = null): File {
		if (!isset(Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat])) {
			throw new \InvalidArgumentException('Invalid file format');
		}

		$this->logger->debug('Export submissions for form: {hash} to Cloud at: /{path} in format {fileFormat}', [
			'hash' => $form->getHash(),
			'path' => $path,
			'fileFormat' => $fileFormat,
		]);

		$fileName = $this->formsService->getFileName($form, $fileFormat);

		/** @var \OCP\Files\Folder|File $node */
		if ($ownerId) {
			$node = $this->rootFolder->getUserFolder($ownerId)->get($path);
		} else {
			$node = $this->rootFolder->getUserFolder($this->currentUser->getUID())->get($path);
		}

		// If chosen path is a file with expected extension - overwrite it and use parent folder otherwise.
		if ($node instanceof File) {
			if ($node->getExtension() === $fileFormat) {
				$fileName = $node->getName();
			}
			/** @var \OCP\Files\Folder $node */
			$node = $node->getParent();
		}

		// Check if file exists, create otherwise.
		try {
			/** @var File $file */
			$file = $node->get($fileName);
		} catch (\OCP\Files\NotFoundException $e) {
			$node->newFile($fileName);
			/** @var File $file */
			$file = $node->get($fileName);
		}

		// Get Data
		$submissionsData = $this->getSubmissionsData($form, $fileFormat, $file);

		// Write the data to file
		try {
			$file->putContent($submissionsData);
		} catch (NotPermittedException $e) {
			$this->logger->warning('Failed to export Submissions: Not allowed to write to file');
			throw new OCSException('Not allowed to write to file.', previous: $e);
		}

		return $file;
	}

	/**
	 * Create/update file from Submissions to form
	 *
	 * @param Form $form Form to export
	 * @param string $fileFormat Format to export to
	 * @param File|null $file File with already exported submissions to append to
	 * @return string File content
	 */
	public function getSubmissionsData(Form $form, string $fileFormat, ?File $file = null): string {
		if (!isset(Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat])) {
			throw new \InvalidArgumentException('Invalid file format');
		}

		try {
			$submissionEntities = $this->submissionMapper->findByForm($form->getId());
		} catch (DoesNotExistException $e) {
			// Just ignore, if no Data. Returns empty Submissions-Array
		}

		// Oldest first
		$submissionEntities = array_reverse($submissionEntities);

		$questions = $this->questionMapper->findByForm($form->getId());
		$defaultTimeZone = $this->config->getSystemValueString('default_timezone', 'UTC');

		if (!$this->currentUser) {
			$userTimezone = $this->config->getUserValue($form->getOwnerId(), 'core', 'timezone', $defaultTimeZone);
		} else {
			$userTimezone = $this->config->getUserValue($this->currentUser->getUID(), 'core', 'timezone', $defaultTimeZone);
		}

		// Process initial header
		$header = [];
		$header[] = $this->l10n->t('User ID');
		$header[] = $this->l10n->t('User display name');
		$header[] = $this->l10n->t('Timestamp');
		/** @var array<int, Question> $questionPerQuestionId */
		$questionPerQuestionId = [];
		foreach ($questions as $question) {
			$header[] = $question->getText();
			$questionPerQuestionId[$question->getId()] = $question;
		}

		// Init dataset
		$data = [];

		// Process each answers
		foreach ($submissionEntities as $submission) {
			$row = [];

			// User
			$user = $this->userManager->get($submission->getUserId());
			if ($user === null) {
				// Give empty userId
				$row[] = '';
				// TRANSLATORS Shown on export if no Display-Name is available.
				$row[] = $this->l10n->t('Anonymous user');
			} else {
				$row[] = $user->getUID();
				$row[] = $user->getDisplayName();
			}

			// Date
			$row[] = date_format(date_timestamp_set(new DateTime(), $submission->getTimestamp())->setTimezone(new DateTimeZone($userTimezone)), 'c');

			// Answers, make sure we keep the question order
			$answers = array_reduce($this->answerMapper->findBySubmission($submission->getId()),
				function (array $carry, Answer $answer) use ($questionPerQuestionId) {
					$questionId = $answer->getQuestionId();

					if (isset($questionPerQuestionId[$questionId])
						&& $questionPerQuestionId[$questionId]->getType() === Constants::ANSWER_TYPE_FILE) {
						if (array_key_exists($questionId, $carry)) {
							$carry[$questionId]['label'] .= "; \n" . $answer->getText();
						} else {
							$carry[$questionId] = [
								'label' => $answer->getText(),
								'url' => $this->urlGenerator->linkToRouteAbsolute('files.View.showFile', ['fileid' => $answer->getFileId()])
							];
						}
					} else {
						if (array_key_exists($questionId, $carry)) {
							$carry[$questionId] .= '; ' . $answer->getText();
						} else {
							$carry[$questionId] = $answer->getText();
						}
					}

					return $carry;
				}, []);

			foreach ($questions as $question) {
				$row[] = $answers[$question->getId()] ?? null;
			}

			$data[] = $row;
		}

		return $this->exportData($header, $data, $fileFormat, $file);
	}

	/**
	 * @param array<int, string> $header
	 * @param array<int, array<int, string>|non-empty-list<array{label: string, url: string}>> $data
	 */
	private function exportData(array $header, array $data, string $fileFormat, ?File $file = null): string {
		if ($file && $file->getContent()) {
			$existentFile = $this->tempManager->getTemporaryFile($fileFormat);
			file_put_contents($existentFile, $file->getContent());
			$spreadsheet = IOFactory::load($existentFile);
		} else {
			$spreadsheet = new Spreadsheet();
		}

		$activeWorksheet = $spreadsheet->getSheet(0);
		foreach ($header as $columnIndex => $value) {
			$activeWorksheet->setCellValue([$columnIndex + 1, 1], $value);
		}
		foreach ($data as $rowIndex => $rowData) {
			foreach ($rowData as $columnIndex => $value) {
				$column = $columnIndex + 1;
				$row = $rowIndex + 2;

				if (is_array($value)) {
					$activeWorksheet->getCell([$column, $row])
						->setValueExplicit($value['label'])
						->getHyperlink()
						->setUrl($value['url']);

					$activeWorksheet->getStyle([$column, $row])
						->getAlignment()
						->setWrapText(true);
				} else {
					// Explicitly set the type of the value to string for values that start with '=' to prevent it being interpreted as formulas
					if (is_string($value)) {
						$activeWorksheet->getCell([$column, $row])
							->setValueExplicit($fileFormat === 'csv'
								? $this->escapeCSV($value)
								: $value,
							);
					} else {
						$activeWorksheet->setCellValue([$column, $row], $value);
					}
				}
			}
		}

		$exportedFile = $this->tempManager->getTemporaryFile($fileFormat);
		$writer = IOFactory::createWriter($spreadsheet, ucfirst($fileFormat));
		if ($writer instanceof Csv) {
			$writer->setUseBOM(true);
		}
		$writer->save($exportedFile);

		return file_get_contents($exportedFile);
	}

	/**
	 * Escape a value for writing it to a CSV file.
	 * This is needed to ensure the CSV, when loaded into an spreadsheet application, does not execute potential formulas.
	 */
	private function escapeCSV(string $value): string {
		$BAD_CHARACTERS = ['=', '+', '-', '@', "\t", "\r"];
		if (strlen($value) > 0 && in_array(mb_str_split($value)[0], $BAD_CHARACTERS)) {
			// Escape the value by adding a leading single quote
			return "'$value";
		}
		return $value;
	}

	/**
	 * Validate all answers against the questions
	 * @param array $questions Array of the questions of the form
	 * @param array $answers Array of the submitted answers
	 * @param string $formOwnerId Owner of the form
	 * @throw \InvalidArgumentException if validation failed
	 */
	public function validateSubmission(array $questions, array $answers, string $formOwnerId): void {
		// Check by questions
		foreach ($questions as $question) {
			$questionId = $question['id'];
			$questionAnswered = array_key_exists($questionId, $answers);

			// Check if all required questions have an answer
			if ($question['isRequired']
				&& (!$questionAnswered
				|| !array_filter($answers[$questionId], static function (string|array $value): bool {
					// file type
					if (is_array($value)) {
						return !empty($value['uploadedFileId']);
					}

					return $value !== '';
				})
				|| (!empty($question['extraSettings']['allowOtherAnswer']) && !array_filter($answers[$questionId], fn ($value) => $value !== Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX)))
			) {
				throw new \InvalidArgumentException(sprintf('Question "%s" is required.', $question['text']));
			}

			// Perform further checks only for answered questions
			if (!$questionAnswered) {
				continue;
			}

			// Check number of answers for multiple answers
			$answersCount = count($answers[$questionId]);
			if ($question['type'] === Constants::ANSWER_TYPE_MULTIPLE) {
				$minOptions = $question['extraSettings']['optionsLimitMin'] ?? -1;
				$maxOptions = $question['extraSettings']['optionsLimitMax'] ?? -1;
				// If number of answers is limited check the limits
				if (($minOptions > 0 && $answersCount < $minOptions)
					&& ($maxOptions > 0 && $answersCount > $maxOptions)) {
					throw new \InvalidArgumentException(sprintf('Question "%s" requires between %d and %d answers.', $question['text'], $minOptions, $maxOptions));
				} elseif ($minOptions > 0 && $answersCount < $minOptions) {
					throw new \InvalidArgumentException(sprintf('Question "%s" requires at least %d answers.', $question['text'], $minOptions));
				} elseif ($maxOptions > 0 && $answersCount > $maxOptions) {
					throw new \InvalidArgumentException(sprintf('Question "%s" requires at most %d answers.', $question['text'], $maxOptions));
				}
			} elseif ($answersCount != 2 && $question['type'] === Constants::ANSWER_TYPE_DATE && isset($question['extraSettings']['dateRange'])) {
				// Check if date range questions have exactly two answers
				throw new \InvalidArgumentException(sprintf('Question "%s" can only have two answers.', $question['text']));
			} elseif ($answersCount != 2 && $question['type'] === Constants::ANSWER_TYPE_TIME && isset($question['extraSettings']['timeRange'])) {
				// Check if date range questions have exactly two answers
				throw new \InvalidArgumentException(sprintf('Question "%s" can only have two answers.', $question['text']));
			} elseif ($answersCount > 1
						&& $question['type'] !== Constants::ANSWER_TYPE_FILE
						&& !($question['type'] === Constants::ANSWER_TYPE_DATE && isset($question['extraSettings']['dateRange'])
						|| $question['type'] === Constants::ANSWER_TYPE_TIME && isset($question['extraSettings']['timeRange']))) {
				// Check if non-multiple questions have not more than one answer
				throw new \InvalidArgumentException(sprintf('Question "%s" can only have one answer.', $question['text']));
			}

			/*
			 * Validate answers for date/time questions
			 * If a date range is specified, validate all answers in the range
			 * Otherwise, validate the single answer for the date/time question
			 */
			if (in_array($question['type'], Constants::ANSWER_TYPES_DATETIME)) {
				$this->validateDateTime($answers[$questionId], Constants::ANSWER_PHPDATETIME_FORMAT[$question['type']], $question['text'] ?? null, $question['extraSettings'] ?? null);
			}

			// Check if all answers are within the possible options
			if (in_array($question['type'], Constants::ANSWER_TYPES_PREDEFINED) && empty($question['extraSettings']['allowOtherAnswer'])) {
				foreach ($answers[$questionId] as $answer) {
					// Handle linear scale questions
					if ($question['type'] === Constants::ANSWER_TYPE_LINEARSCALE) {
						$optionsLowest = $question['extraSettings']['optionsLowest'] ?? 1;
						$optionsHighest = $question['extraSettings']['optionsHighest'] ?? 5;
						if (!ctype_digit($answer) || intval($answer) < $optionsLowest || intval($answer) > $optionsHighest) {
							throw new \InvalidArgumentException(sprintf('The answer for question "%s" must be an integer between %d and %d.', $question['text'], $optionsLowest, $optionsHighest));
						}
					}
					// Search corresponding option, return false if non-existent
					elseif (!in_array($answer, array_column($question['options'], 'id'))) {
						throw new \InvalidArgumentException(sprintf('Answer "%s" for question "%s" is not a valid option.', $answer, $question['text']));
					}
				}
			}

			// Handle custom validation of short answers
			if ($question['type'] === Constants::ANSWER_TYPE_SHORT && !$this->validateShortQuestion($question, $answers[$questionId][0])) {
				throw new \InvalidArgumentException(sprintf('Invalid input for question "%s".', $question['text']));
			}

			// Handle color questions
			if (
				$question['type'] === Constants::ANSWER_TYPE_COLOR
				&& $answers[$questionId][0] !== ''
				&& !preg_match('/^#[a-f0-9]{6}$/i', $answers[$questionId][0])
			) {
				throw new \InvalidArgumentException(sprintf('Invalid color string for question "%s".', $question['text']));
			}

			// Handle file questions
			if ($question['type'] === Constants::ANSWER_TYPE_FILE) {
				$maxAllowedFilesCount = $question['extraSettings']['maxAllowedFilesCount'] ?? 0;
				if ($maxAllowedFilesCount > 0 && count($answers[$questionId]) > $maxAllowedFilesCount) {
					throw new \InvalidArgumentException(sprintf('Too many files uploaded for question "%s". Maximum allowed: %d', $question['text'], $maxAllowedFilesCount));
				}

				foreach ($answers[$questionId] as $answer) {
					$uploadedFile = $this->uploadedFileMapper->findByUploadedFileId($answer['uploadedFileId']);
					if (!$uploadedFile) {
						throw new \InvalidArgumentException(sprintf('File "%s" for question "%s" not exists anymore. Please delete and re-upload the file.', $answer['fileName'] ?? $answer['uploadedFileId'], $question['text']));
					}

					$nodes = $this->rootFolder->getUserFolder($formOwnerId)->getById($uploadedFile->getFileId());
					if (empty($nodes)) {
						throw new \InvalidArgumentException(sprintf('File "%s" for question "%s" not exists anymore. Please delete and re-upload the file.', $answer['fileName'] ?? $answer['uploadedFileId'], $question['text']));
					}
				}
			}
		}

		// Check for excess answers
		foreach ($answers as $id => $answerArray) {
			// Search corresponding question, return false if not found
			if (!in_array($id, array_column($questions, 'id'))) {
				throw new \InvalidArgumentException(sprintf('Answer for non-existent question with ID %d.', $id));
			}
		}
	}

	/**
	 * Validate correct date/time formats
	 * @param array $answers Array with date from answer
	 * @param string $format String with the format to validate
	 * @param string|null $text String with the title of the question
	 * @param array|null $extraSettings Array with extra settings for validation
	 */
	private function validateDateTime(array $answers, string $format, ?string $text = null, ?array $extraSettings = null): void {
		$previousDate = null;

		foreach ($answers as $dateStr) {
			$d = DateTime::createFromFormat($format, $dateStr);
			if (!$d || $d->format($format) !== $dateStr) {
				throw new \InvalidArgumentException(sprintf('Invalid date/time format for question "%s".', $text));
			}

			if ($previousDate !== null && $d < $previousDate) {
				throw new \InvalidArgumentException(sprintf('Date/time values for question "%s" must be in ascending order.', $text));
			}
			$previousDate = $d;

			if ($extraSettings) {
				if ((isset($extraSettings['dateMin']) && $d < (new DateTime())->setTimestamp($extraSettings['dateMin']))
					|| (isset($extraSettings['dateMax']) && $d > (new DateTime())->setTimestamp($extraSettings['dateMax']))
					|| (isset($extraSettings['timeMin']) && $d < DateTime::createFromFormat($format, $extraSettings['timeMin']))
					|| (isset($extraSettings['timeMax']) && $d > DateTime::createFromFormat($format, $extraSettings['timeMax']))
				) {
					throw new \InvalidArgumentException(sprintf('Date/time is not in the allowed range for question "%s".', $text));
				}
			}
		}
	}

	/**
	 * Validate short question answers if special validation types are set
	 */
	private function validateShortQuestion(array $question, string $data): bool {
		if (!isset($question['extraSettings']) || !isset($question['extraSettings']['validationType'])) {
			// No type defined, so fallback to 'text' => no special handling
			return true;
		}

		switch ($question['extraSettings']['validationType']) {
			case 'email':
				return $this->mailer->validateMailAddress($data);
			case 'number':
				return is_numeric($data);
			case 'phone':
				// some special characters are used (depending on the locale)
				$sanitized = str_replace([' ', '(', ')', '.', '/', '-', 'x'], '', $data);
				// allow leading + for international numbers
				if (str_starts_with($sanitized, '+')) {
					$sanitized = substr($sanitized, 1);
				}
				return preg_match('/^[0-9]{3,}$/', $sanitized) === 1;
			case 'regex':
				// empty regex matches everything
				if (!isset($question['extraSettings']['validationRegex'])) {
					return true;
				}
				return preg_match($question['extraSettings']['validationRegex'], $data) === 1;
			default:
				$this->logger->error('Invalid input type for question detected, please report this issue!', ['validationType' => $question['extraSettings']['validationType']]);
				// The result is just a non-validated text on the results, but not a fully declined submission. So no need to throw but simply return false here.
				return false;
		}
	}
}
