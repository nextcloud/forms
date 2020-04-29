<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method integer getTimestamp()
 * @method void setTimestamp(integer $value)
 */
class Submission extends Entity {
	protected $formId;
	protected $userId;
	protected $timestamp;

	/**
	 * Submission constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('timestamp', 'integer');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'formId' => $this->getFormId(),
			'userId' => $this->getUserId(),
			'timestamp' => $this->getTimestamp(),
		];
	}
}
