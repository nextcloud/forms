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
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method integer getVoteOptionId()
 * @method void setVoteOptionId(integer $value)
 * @method string getVoteOptionText()
 * @method void setVoteOptionText(string $value)
 * @method string getVoteAnswer()
 * @method void setVoteAnswer(string $value)
 * @method string getVoteOptionType()
 * @method void setVoteOptionType(string $value)
 */
class Vote extends Entity {
	protected $formId;
	protected $userId;
	protected $voteOptionId;
	protected $voteOptionText;
	protected $voteAnswer;
	protected $voteOptionType;

	/**
	 * Options constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('voteOptionId', 'integer');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'voteOptionId' => $this->getVoteOptionId(),
			'voteOptionText' => htmlspecialchars_decode($this->getVoteOptionText()),
			'voteAnswer' => $this->getVoteAnswer(),
			'voteOptionType' => $this->getVoteOptionType()
		];
	}

}
