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
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getDescription()
 * @method void setDescription(string $value)
 * @method string getOwner()
 * @method void setOwner(string $value)
 * @method string getCreated()
 * @method void setCreated(string $value)
 * @method string getAccess()
 * @method void setAccess(string $value)
 * @method string getExpire()
 * @method void setExpire(string $value)
 * @method string getHash()
 * @method void setHash(string $value)
 * @method integer getIsAnonymous()
 * @method void setIsAnonymous(integer $value)
 * @method integer getUnique()
 * @method void setUnique(boolean $value)
 */
class Event extends Entity {
	protected $title;
	protected $description;
	protected $owner;
	protected $created;
	protected $access;
	protected $expire;
	protected $hash;
	protected $isAnonymous;
	protected $fullAnonymous;
	protected $allowMaybe;
	protected $unique;

	/**
	 * Event constructor.
	 */
	public function __construct() {
		$this->addType('isAnonymous', 'integer');
	}

	public function read() {
		$accessType = $this->getAccess();
		if (!strpos('|public|hidden|registered', $accessType)) {
			$accessType = 'select';
		}
		if ($this->getExpire() === null) {
			$expired = false;
			$expiration = false;
		} else {
			$expired = time() > strtotime($this->getExpire());
			$expiration = true;
		}

		return [
			'id' => $this->getId(),
			'hash' => $this->getHash(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'owner' => $this->getOwner(),
			'ownerDisplayName' => \OC_User::getDisplayName($this->getOwner()),
			'created' => $this->getCreated(),
			'access' => $accessType,
			'expiration' => $expiration,
			'expired' => $expired,
			'expirationDate' => $this->getExpire(),
			'isAnonymous' => $this->getIsAnonymous(),
			'unique' => $this->getUnique()
		];
	}
}
