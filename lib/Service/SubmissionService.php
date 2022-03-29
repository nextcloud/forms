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

use DateTimeZone;

use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;

use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;

use League\Csv\EncloseField;
use League\Csv\EscapeFormula;
use League\Csv\Reader;
use League\Csv\Writer;

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

	/** @var IDateTimeFormatter */
	private $dateTimeFormatter;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	public function __construct(FormMapper $formMapper,
								QuestionMapper $questionMapper,
								SubmissionMapper $submissionMapper,
								AnswerMapper $answerMapper,
								IRootFolder $storage,
								IConfig $config,
								IDateTimeFormatter $dateTimeFormatter,
								IL10N $l10n,
								ILogger $logger,
								IUserManager $userManager,
								IUserSession $userSession) {
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->storage = $storage;
		$this->config = $config;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userManager = $userManager;

		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Export Submissions to Cloud-Filesystem
	 * @param string $hash of the form
	 * @param string $path The Cloud-Path to export to
	 * @return string The written fileName
	 * @throws NotPermittedException
	 */
	public function writeCsvToCloud(string $hash, string $path): string {
		$node = $this->storage->getUserFolder($this->currentUser->getUID())->get($path);

		// Get Data
		$csvData = $this->getSubmissionsCsv($hash);

		// If chosen path is a file, get folder, if file is csv, use filename.
		if ($node instanceof File) {
			if ($node->getExtension() === 'csv') {
				$csvData['fileName'] = $node->getName();
			}
			$node = $node->getParent();
		}

		// check if file exists, create otherwise.
		try {
			$file = $node->get($csvData['fileName']);
		} catch (\OCP\Files\NotFoundException $e) {
			$node->newFile($csvData['fileName']);
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
		$userTimezone = $this->config->getUserValue('core', 'timezone', $this->currentUser->getUID(), $defaultTimeZone);

		// Process initial header
		$header = [];
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
				if (substr($submission->getUserId(), 0, 10) === 'anon-user-') {
					// TRANSLATORS Shown on export for anonymous Submissions
					$row[] = $this->l10n->t('Anonymous user');
				} else {
					$row[] = $submission->getUserId();
				}
			} else {
				$row[] = $user->getDisplayName();
			}
			
			// Date
			$row[] = $this->dateTimeFormatter->formatDateTime($submission->getTimestamp(), 'full', 'full', new DateTimeZone($userTimezone), $this->l10n);

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

		return $csv->getContent();
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
			if ($question['isRequired'] && (!$questionAnswered || !array_filter($answers[$questionId], 'strlen'))) {
				return false;
			}

			// Perform further checks only for answered questions
			// TODO Check if date questions have valid answers
			if ($questionAnswered) {
				// Check if non multiple questions have not more than one answer
				if ($question['type'] !== Constants::ANSWER_TYPE_MULTIPLE && count($answers[$questionId]) > 1) {
					return false;
				}
	
				// Check if all answers are within the possible options
				if (in_array($question['type'], Constants::ANSWER_PREDEFINED)) {
					foreach ($answers[$questionId] as $answer) {
						// Search corresponding option, return false if non-existent
						if (array_search($answer, array_column($question['options'], 'id')) === false) {
							return false;
						}
					}
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
}
