<?php
/**
 * @copyright Copyright (c) 2017 Kai Schröer <git@schroeer.co>
 *
 * @author Kai Schröer <git@schroeer.co>
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

namespace OCA\Forms\Tests\Unit\Db;

use OCA\Forms\Db\Event;
use OCA\Forms\Db\EventMapper;
use OCA\Forms\Db\Vote;
use OCA\Forms\Db\VoteMapper;
use OCA\Forms\Tests\Unit\UnitTestCase;
use OCP\IDBConnection;
use League\FactoryMuffin\Faker\Facade as Faker;

class VoteMapperTest extends UnitTestCase {

	/** @var IDBConnection */
	private $con;
	/** @var VoteMapper */
	private $voteMapper;
	/** @var EventMapper */
	private $eventMapper;

	/**
	 * {@inheritDoc}
	 */
	public function setUp() {
		parent::setUp();
		$this->con = \OC::$server->getDatabaseConnection();
		$this->voteMapper = new VoteMapper($this->con);
		$this->eventMapper = new EventMapper($this->con);
	}

	/**
	 * Create some fake data and persist them to the database.
	 *
	 * @return Vote
	 */
	public function testCreate() {
		/** @var Event $event */
		$event = $this->fm->instance('OCA\Forms\Db\Event');
		$this->assertInstanceOf(Event::class, $this->eventMapper->insert($event));


		/** @var Vote $vote */
		$vote = $this->fm->instance('OCA\Forms\Db\Vote');
		$vote->setFormId($event->getId());
		$vote->setVoteOptionId(1);
		$this->assertInstanceOf(Vote::class, $this->voteMapper->insert($vote));

		return $vote;
	}

	/**
	 * Update the previously created entry and persist the changes.
	 *
	 * @depends testCreate
	 * @param Vote $vote
	 * @return Vote
	 */
	public function testUpdate(Vote $vote) {
		$newVoteOptionText = Faker::date('Y-m-d H:i:s');
		$vote->setVoteOptionText($newVoteOptionText());
		$this->voteMapper->update($vote);

		return $vote;
	}

	/**
	 * Delete the previously created entries from the database.
	 *
	 * @depends testUpdate
	 * @param Vote $vote
	 */
	public function testDelete(Vote $vote) {
		$event = $this->eventMapper->find($vote->getFormId());
		$this->voteMapper->delete($vote);
		$this->eventMapper->delete($event);
	}
}
