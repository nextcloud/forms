<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCA\Forms\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-import-type FormsQuestionExtraSettings from ResponseDefinitions
 * @psalm-import-type FormsQuestionType from ResponseDefinitions
 * @method int getFormId()
 * @method void setFormId(integer $value)
 * @method int getOrder()
 * @method void setOrder(integer $value)
 * @psalm-method FormsQuestionType getType()
 * @method string getType()
 * @psalm-method 'date'|'datetime'|'dropdown'|'file'|'long'|'multiple'|'multiple_unique'|'short'|'time' getType()
 * @method void setType(string $value)
 * @psalm-method void setType('date'|'datetime'|'dropdown'|'file'|'long'|'multiple'|'multiple_unique'|'short'|'time' $value)
 * @method bool getIsRequired()
 * @method void setIsRequired(bool $value)
 * @method string getText()
 * @method void setText(string $value)
 * @method string getDescription()
 * @method void setDescription(string $value)
 * @method string getName()
 * @method void setName(string $value)
 */
class Question extends Entity {
	protected $formId;
	protected $order;
	protected $type;
	protected $isRequired;
	protected $text;
	protected $name;
	protected $description;
	protected $extraSettingsJson;

	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('type', 'string');
		$this->addType('isRequired', 'boolean');
		$this->addType('text', 'string');
		$this->addType('description', 'string');
		$this->addType('name', 'string');
	}

	/**
	 * @return FormsQuestionExtraSettings
	 */
	public function getExtraSettings(): array {
		return json_decode($this->getExtraSettingsJson() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @param FormsQuestionExtraSettings $extraSettings
	 */
	public function setExtraSettings(array $extraSettings): void {
		// Remove extraSettings that are not set
		foreach ($extraSettings as $key => $value) {
			if ($value === false) {
				unset($extraSettings[$key]);
			}
		}

		$this->setExtraSettingsJson(json_encode($extraSettings, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT));
	}

	/**
	 * @return array{
	 *    id: int,
	 *    formId: int,
	 *    order: int,
	 *    type: FormsQuestionType,
	 *    isRequired: bool,
	 *    text: string,
	 *    name: string,
	 *    description: string,
	 *    extraSettings: FormsQuestionExtraSettings,
	 *  }
	 */
	public function read(): array {
		return [
			'id' => $this->getId(),
			'formId' => $this->getFormId(),
			'order' => (int)$this->getOrder(),
			'type' => $this->getType(),
			'isRequired' => (bool)$this->getIsRequired(),
			'text' => (string)$this->getText(),
			'name' => (string)$this->getName(),
			'description' => (string)$this->getDescription(),
			'extraSettings' => $this->getExtraSettings(),
		];
	}
}
