<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFormId()
 * @method void setFormId(integer $value)
 * @method int getOrder()
 * @method void setOrder(integer $value)
 * @method string getType()
 * @method void setType(string $value)
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

	public function getExtraSettings(): array {
		return json_decode($this->getExtraSettingsJson() ?: '{}', true); // assoc=true, => Convert to associative Array
	}

	public function setExtraSettings(array $extraSettings) {
		// Remove extraSettings that are not set
		foreach ($extraSettings as $key => $value) {
			if ($value === false) {
				unset($extraSettings[$key]);
			}
		}

		$this->setExtraSettingsJson(json_encode($extraSettings, JSON_FORCE_OBJECT));
	}

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
