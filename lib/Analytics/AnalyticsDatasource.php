<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Analytics;

/** @psalm-suppress UndefinedClass */
use OCA\Analytics\Datasource\IDatasource;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\IL10N;

class AnalyticsDatasource implements IDatasource {

	public function __construct(
		protected ?string $userId,
		private IL10N $l10n,
		private FormMapper $formMapper,
		private FormsService $formsService,
		private SubmissionService $submissionService,
	) {
	}

	/**
	 * @return string Display Name of the datasource
	 */
	public function getName(): string {
		return $this->l10n->t('Nextcloud Forms');
	}

	/**
	 * @return int 2 digit unique datasource id
	 */
	public function getId(): int {
		return 66;
	}

	/**
	 * available options of the data source
	 *
	 * return needs to be an array and can consist of many fields.
	 * every field needs to provide the following format
	 *  id          *mandatory*     = name of the option for the readData
	 *  name        *mandatory*     = displayed name of the input field in the UI
	 *  type        *optional*      = 'tf' to create a dropdown. Values need to be provided in the placeholder separated with "/".
	 *  placeholder *mandatory*     = help text for the input field in the UI
	 *                                for type=tf:
	 *                                  e.g. "true/false"
	 *                                  if value/text pairs are required for the dropdown/option, the values need to be separated with "-" in addition.
	 *                                  e.g. "eq-equal/gt-greater"
	 *                                  to avoid translation of the technical strings, separate them
	 *                                  'true-' - $this->l10n->t('Yes').'/false-'.$this->l10n->t('No')
	 *
	 *  example:
	 *  {['id' => 'datatype', 'name' => 'Type of Data', 'type' => 'tf', 'placeholder' => 'adaptation/absolute']}
	 *
	 * @return array
	 */
	public function getTemplate(): array {
		$formsString = '';
		$questionString = '';
		$template = [];

		$forms = $this->formMapper->findAllByOwnerId($this->userId);
		foreach ($forms as $form) {
			$formsString = $formsString . $form->getId() . '-' . $form->getTitle() . '/';
			/* Questions are not yet required
			$questions = $this->formsService->getQuestions($form->getId());
			foreach ($questions as $question) {
				$questionString = $questionString . $question['id'] . '-' . $question['text'] . '/';
			}*/
		}

		// add the tables to a dropdown in the data source settings
		$template[] = ['id' => 'formId', 'name' => $this->l10n->t('Select form'), 'type' => 'tf', 'placeholder' => $formsString];
		$template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'false-' . $this->l10n->t('No') . '/true-' . $this->l10n->t('Yes'), 'type' => 'tf'];
		return $template;
	}

	/**
	 * Read the Data
	 *
	 * return needs to be an array
	 *  [
	 *      'header' => $header,  //array('column header 1', 'column header 2','column header 3')
	 *      'dimensions' => array_slice($header, 0, count($header) - 1),
	 *      'data' => $data,
	 *      'error' => 0,         // INT 0 = no error
	 *  ]
	 *
	 * @param array $option
	 * @return array
	 */
	public function readData($option): array {
		$questionMap = [];
		$answerCounts = [];

		$formId = $option['formId'];

		$questions = $this->formsService->getQuestions($formId);
		foreach ($questions as $question) {
			$questionMap[$question['id']] = $question['text'];
		}

		$submissions = $this->submissionService->getSubmissions($formId);
		foreach ($submissions as $submission) {
			foreach ($submission['answers'] as $answer) {
				$questionText = $questionMap[$answer['questionId']];
				$key = $questionText . '|' . $answer['text'];
				if (isset($answerCounts[$key])) {
					$answerCounts[$key]++;
				} else {
					$answerCounts[$key] = 1;
				}
			}
		}

		foreach ($answerCounts as $key => $count) {
			[$questionText, $text] = explode('|', $key);
			$results[] = [$questionText, $text, $count];
		}

		// Sort by questionText ascending and count descending
		usort($results, function ($a, $b) {
			if ($a[0] === $b[0]) {
				return $b[2] <=> $a[2];
			}
			return $a[0] <=> $b[0];
		});

		$header = [];
		$header[0] = $this->l10n->t('Question');
		$header[1] = $this->l10n->t('Answer');
		$header[2] = $this->l10n->t('Count');

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $results,
			//'rawdata' => $data,
			'error' => 0,
		];
	}
}
