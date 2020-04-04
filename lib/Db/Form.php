<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @author Kai Schröer <git@schroeer.co>
 * @author René Gieling <github@dartcafe.de>
*
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
 * @method string getAccess()
 * @method void setAccess(string $value)
 * @method string getCreated()
 * @method void setCreated(string $value)
 * @method string getExpirationDate()
 * @method void setExpirationDate(string $value)
 * @method integer getIsAnonymous()
 * @method void setIsAnonymous(bool $value)
 * @method integer getSubmitOnce()
 * @method void setSubmitOnce(bool $value)
 */
class Form extends Entity {

	protected $hash;
	protected $title;
	protected $description;
	protected $ownerId;
	protected $access;
	protected $created;
	protected $expirationDate;
	protected $isAnonymous;
	protected $submitOnce;

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->addType('isAnonymous', 'bool');
		$this->addType('submitOnce', 'bool');
	}

	public function read() {
		$accessType = $this->getAccess();
		if (!strpos('|public|hidden|registered', $accessType)) {
			$accessType = 'select';
		}
		if ($this->getExpirationDate() === null) {
			$expired = false;
			$expires = false;
		} else {
			$expired = time() > strtotime($this->getExpirationDate());
			$expires = true;
		}

		return [
			'id' => $this->getId(),
			'hash' => $this->getHash(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'ownerId' => $this->getOwnerId(),
			'ownerDisplayName' => \OC_User::getDisplayName($this->getOwnerId()),
			'created' => $this->getCreated(),
			'access' => $accessType,
			'expires' => $expires,
			'expired' => $expired,
			'expirationDate' => $this->getExpirationDate(),
			'isAnonymous' => $this->getIsAnonymous(),
			'submitOnce' => $this->getSubmitOnce()
		];
	}
}
