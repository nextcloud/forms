<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
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

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getHash()
 * @method void setHash(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getDescription()
 * @method void setDescription(string $value)
 * @method string getOwnerId()
 * @method void setOwnerId(string $value)
 * @method array getAccess()
 * @method void setAccess(array $value)
 * @method integer getCreated()
 * @method void setCreated(integer $value)
 * @method integer getExpires()
 * @method void setExpires(integer $value)
 * @method integer getIsAnonymous()
 * @method void setIsAnonymous(bool $value)
 * @method integer getSubmitMultiple()
 * @method void setSubmitMultiple(bool $value)
 * @method integer getAllowEdit()
 * @method void setAllowEdit(bool $value)
 * @method integer getShowExpiration()
 * @method void setShowExpiration(bool $value)
 * @method integer getLastUpdated()
 * @method void setLastUpdated(integer $value)
 * @method ?string getSubmissionMessage()
 * @method void setSubmissionMessage(?string $value)
 */
class Form extends Entity {
	protected $hash;
	protected $title;
	protected $description;
	protected $ownerId;
	protected $accessJson;
	protected $created;
	protected $expires;
	protected $isAnonymous;
	protected $submitMultiple;
	protected $allowEdit;
	protected $showExpiration;
	protected $submissionMessage;
	protected $lastUpdated;

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->addType('created', 'integer');
		$this->addType('expires', 'integer');
		$this->addType('isAnonymous', 'bool');
		$this->addType('submitMultiple', 'bool');
		$this->addType('allowEdit', 'bool');
		$this->addType('showExpiration', 'bool');
		$this->addType('lastUpdated', 'integer');
	}

	// JSON-Decoding of access-column.
	public function getAccess(): array {
		return json_decode($this->getAccessJson(), true); // assoc=true, => Convert to associative Array
	}

	// JSON-Encoding of access-column.
	public function setAccess(array $access) {
		$this->setAccessJson(json_encode($access));
	}

	// Read full form
	public function read() {
		return [
			'id' => $this->getId(),
			'hash' => $this->getHash(),
			'title' => (string)$this->getTitle(),
			'description' => (string)$this->getDescription(),
			'ownerId' => $this->getOwnerId(),
			'created' => $this->getCreated(),
			'access' => $this->getAccess(),
			'expires' => (int)$this->getExpires(),
			'isAnonymous' => (bool)$this->getIsAnonymous(),
			'submitMultiple' => (bool)$this->getSubmitMultiple(),
			'allowEdit' => (bool)$this->getAllowEdit(),
			'showExpiration' => (bool)$this->getShowExpiration(),
			'lastUpdated' => (int)$this->getLastUpdated(),
			'submissionMessage' => $this->getSubmissionMessage(),
		];
	}
}
