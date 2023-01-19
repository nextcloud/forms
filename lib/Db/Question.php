<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Simon Vieille <simon@deblan.fr>
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

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 * @method integer getOrder()
 * @method void setOrder(integer $value)
 * @method string getType()
 * @method void setType(string $value)
 * @method string getText()
 * @method void setText(string $value)
 * @method string getDescription()
 * @method void setDescription(string $value)
 * @method object getExtraSettings()
 * @method void setExtraSettings(object $value)
 */
class Question extends Entity {
	protected $formId;
	protected $order;
	protected $type;
	protected $isRequired;
	protected $text;
	protected $description;
	protected $extraSettingsJson;

	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('type', 'string');
		$this->addType('isRequired', 'bool');
		$this->addType('text', 'string');
		$this->addType('description', 'string');
	}

	public function getExtraSettings(): object {
		return json_decode($this->getExtraSettingsJson() ?: '{}');
	}

	/**
	 * @param object|array $extraSettings
	 */
	public function setExtraSettings($extraSettings) {
		// TODO: When the php requirement is >= 8.0 change parameter typing to `object|array` to allow assoc. arrays from `Question::fromParams`
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
			'description' => (string)$this->getDescription(),
			'extraSettings' => $this->getExtraSettings(),
		];
	}
}
