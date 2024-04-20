<?php
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Service;

use DateTime;
use DateTimeZone;

use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;

use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

use Psr\Log\LoggerInterface;

class SubmissionService {
	/** @var FormMapper */
	private $formMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/** @var AnswerMapper */
	private $answerMapper;

	/** @var IRootFolder */
	private $storage;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l10n;

	/** @var LoggerInterface */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	/** @var IUser */
	private $currentUser;

	/** @var IMailer */
	private $mailer;

	public function __construct(FormMapper $formMapper,
		QuestionMapper $questionMapper,
		SubmissionMapper $submissionMapper,
		AnswerMapper $answerMapper,
		IRootFolder $storage,
		IConfig $config,
		IL10N $l10n,
		LoggerInterface $logger,
		IUserManager $userManager,
		IUserSession $userSession,
		IMailer $mailer,
		private ITempManager $tempManager,
		private FormsService $formsService,
	) {
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->storage = $storage;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->mailer = $mailer;

		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Get all the answers of a given submission
	 *
	 * @param int $submissionId the submission id
	 * @return array
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
	 * @return array
	 */
	public function getSubmissions(int $formId): array {
		$submissionList = [];
		try {
			$submissionEntities = $this->submissionMapper->findByForm($formId);
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
	 * Validate the new submission is unique
	 */
	public function isUniqueSubmission(Submission $newSubmission): bool {
		return $this->submissionMapper->countSubmissions($newSubmission->getFormId(), $newSubmission->getUserId()) === 1;
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
			$node = $this->storage->getUserFolder($ownerId)->get($path);
		} else {
			$node = $this->storage->getUserFolder($this->currentUser->getUID())->get($path);
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
		$defaultTimeZone = date_default_timezone_get();

		if ($this->currentUser == null) {
			$userTimezone = $this->config->getUserValue($form->getOwnerId(), 'core', 'timezone', $defaultTimeZone);
		} else {
			$userTimezone = $this->config->getUserValue($this->currentUser->getUID(), 'core', 'timezone', $defaultTimeZone);
		}

		// Process initial header
		$header = [];
		$header[] = $this->l10n->t('User ID');
		$header[] = $this->l10n->t('User display name');
		$header[] = $this->l10n->t('Timestamp');
		foreach ($questions as $question) {
			$header[] = $question->getText();
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
			$answers = array_reduce($this->answerMapper->findBySubmission($submission->getId()), function (array $carry, Answer $answer) {
				$questionId = $answer->getQuestionId();

				// If key exists, insert separator
				if (array_key_exists($questionId, $carry)) {
					$carry[$questionId] .= '; ' . $answer->getText();
				} else {
					$carry[$questionId] = $answer->getText();
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
	 * @param array<int, array<int, string>> $data
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
		foreach ($data as $rowIndex => $row) {
			foreach ($row as $columnIndex => $value) {
				$activeWorksheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
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
	 * Validate all answers against the questions
	 * @param array $questions Array of the questions of the form
	 * @param array $answers Array of the submitted answers
	 * @return boolean If the submission is valid
	 */
	public function validateSubmission(array $questions, array $answers): bool {
		// Check by questions
		foreach ($questions as $question) {
			$questionId = $question['id'];
			$questionAnswered = array_key_exists($questionId, $answers);

			// Check if all required questions have an answer
			if ($question['isRequired'] &&
				(!$questionAnswered ||
				!array_filter($answers[$questionId], 'strlen') ||
				(!empty($question['extraSettings']['allowOtherAnswer']) && !array_filter($answers[$questionId], fn ($value) => $value !== Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX)))
			) {
				return false;
			}

			// Perform further checks only for answered questions - otherwise early return
			if (!$questionAnswered) {
				continue;
			}

			// Check number of answers
			$answersCount = count($answers[$questionId]);
			if ($question['type'] === Constants::ANSWER_TYPE_MULTIPLE) {
				$minOptions = $question['extraSettings']['optionsLimitMin'] ?? -1;
				$maxOptions = $question['extraSettings']['optionsLimitMax'] ?? -1;
				// If number of answers is limited check the limits
				if (($minOptions > 0 && $answersCount < $minOptions)
					|| ($maxOptions > 0 && $answersCount > $maxOptions)) {
					return false;
				}
			} elseif ($answersCount > 1) {
				// Check if non multiple questions have not more than one answer
				return false;
			}

			/*
			 * Check if date questions have valid answers
			 * $answers[$questionId][0] -> date/time questions can only have one answer
			 */
			if (in_array($question['type'], Constants::ANSWER_TYPES_DATETIME) &&
				!$this->validateDateTime($answers[$questionId][0], Constants::ANSWER_PHPDATETIME_FORMAT[$question['type']])) {
				return false;
			}

			// Check if all answers are within the possible options
			if (in_array($question['type'], Constants::ANSWER_TYPES_PREDEFINED) && empty($question['extraSettings']['allowOtherAnswer'])) {
				foreach ($answers[$questionId] as $answer) {
					// Search corresponding option, return false if non-existent
					if (array_search($answer, array_column($question['options'], 'id')) === false) {
						return false;
					}
				}
			}

			// Handle custom validation of short answers
			if ($question['type'] === Constants::ANSWER_TYPE_SHORT && !$this->validateShortQuestion($question, $answers[$questionId][0])) {
				return false;
			}
		}

		// Check for excess answers
		foreach ($answers as $id => $answerArray) {
			// Search corresponding question, return false if not found
			$questionIndex = array_search($id, array_column($questions, 'id'));
			if ($questionIndex === false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate correct date/time formats
	 * @param string $dateStr String with date from answer
	 * @param string $format String with the format to validate
	 * @return boolean If the submitted date/time is valid
	 */
	private function validateDateTime(string $dateStr, string $format) {
		$d = DateTime::createFromFormat($format, $dateStr);
		return $d && $d->format($format) === $dateStr;
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
