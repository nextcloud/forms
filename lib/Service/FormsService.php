<?php
/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;

/**
 * Trait for getting forms information in a service
 */
class FormsService {
	
	/** @var FormMapper */
	private $formMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var OptionMapper */
	private $optionMapper;

	public function __construct(FormMapper $formMapper,
								QuestionMapper $questionMapper,
								OptionMapper $optionMapper) {
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
	}


	public function getOptions(int $questionId): array {
		$optionList = [];
		try{
			$optionEntities = $this->optionMapper->findByQuestion($questionId);
			foreach ($optionEntities as $optionEntity) {
				$optionList[] = $optionEntity->read();
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $optionList;
		}
	}

	public function getQuestions(int $formId): array {
		$questionList = [];
		try{
			$questionEntities = $this->questionMapper->findByForm($formId);
			foreach ($questionEntities as $questionEntity) {
				$question = $questionEntity->read();
				$question['options'] = $this->getOptions($question['id']);
				$questionList[] =  $question;
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		}finally{
			return $questionList;
		}
	}

	/**
	 * Get a form data
	 *
	 * @param integer $id
	 * @return array
	 * @throws IMapperException
	 */
	public function getForm(int $id): array {
		$form = $this->formMapper->findById($id);
		$result = $form->read();
		$result['questions'] = $this->getQuestions($id);

		return $result;
	}

}
