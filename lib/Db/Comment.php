<?php
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
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getDt()
 * @method void setDt(string $value)
 * @method string getComment()
 * @method void setComment(string $value)
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 */
class Comment extends Model {
	protected $userId;
	protected $dt;
	protected $comment;
	protected $formId;

	/**
	 * Comment constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
	}

	public function read() {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'date' => $this->getDt() . ' UTC',
			'comment' => $this->getComment()
		];

	}
}
