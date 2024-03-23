<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
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

use OCA\Forms\Constants;
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
 * @method int|null getFileId()
 * @method void setFileId(int|null $value)
 * @method string|null getFileFormat()
 * @method void setFileFormat(string|null $value)
 * @method array getAccess()
 * @method void setAccess(array $value)
 * @method int getCreated()
 * @method void setCreated(int $value)
 * @method int getExpires()
 * @method void setExpires(int $value)
 * @method int getIsAnonymous()
 * @method void setIsAnonymous(bool $value)
 * @method int getSubmitMultiple()
 * @method void setSubmitMultiple(bool $value)
 * @method int getShowExpiration()
 * @method void setShowExpiration(bool $value)
 * @method int getLastUpdated()
 * @method void setLastUpdated(int $value)
 * @method ?string getSubmissionMessage()
 * @method void setSubmissionMessage(?string $value)
 * @method int getState()
 * @method void setState(?int $value)
 */
class Form extends Entity {
	protected $hash;
	protected $title;
	protected $description;
	protected $ownerId;
	protected $fileId;
	protected $fileFormat;
	protected $accessEnum;
	protected $created;
	protected $expires;
	protected $isAnonymous;
	protected $submitMultiple;
	protected $showExpiration;
	protected $submissionMessage;
	protected $lastUpdated;
	protected $state;

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->addType('created', 'integer');
		$this->addType('expires', 'integer');
		$this->addType('isAnonymous', 'bool');
		$this->addType('submitMultiple', 'bool');
		$this->addType('showExpiration', 'bool');
		$this->addType('lastUpdated', 'integer');
		$this->addType('state', 'integer');
	}

	// JSON-Decoding of access-column.
	public function getAccess(): array {
		$accessEnum = $this->getAccessEnum();
		$access = [];

		if ($accessEnum >= Constants::FORM_ACCESS_LEGACYLINK) {
			$access['legacyLink'] = true;
		}
		switch ($accessEnum % Constants::FORM_ACCESS_LEGACYLINK) {
			case Constants::FORM_ACCESS_NOPUBLICSHARE:
				$access['permitAllUsers'] = false;
				$access['showToAllUsers'] = false;
				break;
			case Constants::FORM_ACCESS_PERMITALLUSERS:
				$access['permitAllUsers'] = true;
				$access['showToAllUsers'] = false;
				break;
			case Constants::FORM_ACCESS_SHOWTOALLUSERS:
				$access['permitAllUsers'] = true;
				$access['showToAllUsers'] = true;
				break;
		}

		return $access;
	}

	// JSON-Encoding of access-column.
	public function setAccess(array $access) {
		// No further permissions -> 0
		// Permit all users, but don't show in navigation -> 1
		// Permit all users and show in navigation -> 2
		if (!$access['permitAllUsers'] && !$access['showToAllUsers']) {
			$value = Constants::FORM_ACCESS_NOPUBLICSHARE;
		} elseif ($access['permitAllUsers'] && !$access['showToAllUsers']) {
			$value = Constants::FORM_ACCESS_PERMITALLUSERS;
		} else {
			$value = Constants::FORM_ACCESS_SHOWTOALLUSERS;
		}
		
		// If legacyLink add 3
		if (isset($access['legacyLink'])) {
			$value += Constants::FORM_ACCESS_LEGACYLINK;
		}

		$this->setAccessEnum($value);
	}

	// Read full form
	public function read() {
		return [
			'id' => $this->getId(),
			'hash' => $this->getHash(),
			'title' => (string)$this->getTitle(),
			'description' => (string)$this->getDescription(),
			'ownerId' => $this->getOwnerId(),
			'fileId' => $this->getFileId(),
			'fileFormat' => $this->getFileFormat(),
			'created' => $this->getCreated(),
			'access' => $this->getAccess(),
			'expires' => (int)$this->getExpires(),
			'isAnonymous' => (bool)$this->getIsAnonymous(),
			'submitMultiple' => (bool)$this->getSubmitMultiple(),
			'showExpiration' => (bool)$this->getShowExpiration(),
			'lastUpdated' => (int)$this->getLastUpdated(),
			'submissionMessage' => $this->getSubmissionMessage(),
			'state' => $this->getState(),
		];
	}
}
