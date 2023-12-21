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

use League\Csv\EncloseField;
use League\Csv\EscapeFormula;
use League\Csv\Reader;
use League\Csv\Writer;
use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;

use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
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
		IMailer $mailer) {
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
	 * @param string $hash of the form
	 * @param string $path The Cloud-Path to export to
	 * @return string The written fileName
	 * @throws NotPermittedException
	 */
	public function writeCsvToCloud(string $hash, string $path): string {
		/** @var \OCP\Files\Folder|\OCP\Files\File $node */
		$node = $this->storage->getUserFolder($this->currentUser->getUID())->get($path);

		// Get Data
		$csvData = $this->getSubmissionsCsv($hash);

		// If chosen path is a file, get folder, if file is csv, use filename.
		if ($node instanceof File) {
			if ($node->getExtension() === 'csv') {
				$csvData['fileName'] = $node->getName();
			}
			/** @var \OCP\Files\Folder $node */
			$node = $node->getParent();
		}

		// check if file exists, create otherwise.
		try {
			/** @var \OCP\Files\File $file */
			$file = $node->get($csvData['fileName']);
		} catch (\OCP\Files\NotFoundException $e) {
			$node->newFile($csvData['fileName']);
			/** @var \OCP\Files\File $file */
			$file = $node->get($csvData['fileName']);
		}

		// Write the data to file
		$file->putContent($csvData['data']);

		return $csvData['fileName'];
	}

	/**
	 * Create CSV from Submissions to form
	 * @param string $hash Hash of the form
	 * @return array Array with 'fileName' and 'data'
	 */
	public function getSubmissionsCsv(string $hash): array {
		$form = $this->formMapper->findByHash($hash);

		try {
			$submissionEntities = $this->submissionMapper->findByForm($form->getId());
		} catch (DoesNotExistException $e) {
			// Just ignore, if no Data. Returns empty Submissions-Array
		}

		$questions = $this->questionMapper->findByForm($form->getId());
		$defaultTimeZone = date_default_timezone_get();
		$userTimezone = $this->config->getUserValue($this->currentUser->getUID(), 'core', 'timezone', $defaultTimeZone);

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
				if (key_exists($questionId, $carry)) {
					$carry[$questionId] .= '; ' . $answer->getText();
				} else {
					$carry[$questionId] = $answer->getText();
				}

				return $carry;
			}, []);

			foreach ($questions as $question) {
				$row[] = key_exists($question->getId(), $answers)
					? $answers[$question->getId()]
					: null;
			}

			$data[] = $row;
		}

		// TRANSLATORS Appendix for CSV-Export: 'Form Title (responses).csv'
		$fileName = $form->getTitle() . ' (' . $this->l10n->t('responses') . ').csv';
		// Sanitize file name, replace all invalid characters
		$fileName = str_replace(mb_str_split(\OCP\Constants::FILENAME_INVALID_CHARS), '-', $fileName);

		return [
			'fileName' => $fileName,
			'data' => $this->array2csv($header, $data),
		];
	}
	
	/**
	 * Convert an array to a csv string
	 * @param array $array
	 * @return string
	 */
	private function array2csv(array $header, array $records): string {
		if (empty($header) && empty($records)) {
			return '';
		}

		// load the CSV document from a string
		$csv = Writer::createFromString('');
		$csv->setOutputBOM(Reader::BOM_UTF8);
		$csv->addFormatter(new EscapeFormula());
		EncloseField::addTo($csv, "\t\x1f");

		// insert the header
		$csv->insertOne($header);

		// insert all the records
		$csv->insertAll($records);

		return $csv->toString();
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

			// Perform further checks only for answered questions
			if ($questionAnswered) {
				// Check if non multiple questions have not more than one answer
				if ($question['type'] !== Constants::ANSWER_TYPE_MULTIPLE && count($answers[$questionId]) > 1) {
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
