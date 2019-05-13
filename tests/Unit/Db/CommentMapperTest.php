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

use OCA\Forms\Db\Comment;
use OCA\Forms\Db\CommentMapper;
use OCA\Forms\Db\Event;
use OCA\Forms\Db\EventMapper;
use OCA\Forms\Tests\Unit\UnitTestCase;
use OCP\IDBConnection;
use League\FactoryMuffin\Faker\Facade as Faker;

class CommentMapperTest extends UnitTestCase {

	/** @var IDBConnection */
	private $con;
	/** @var CommentMapper */
	private $commentMapper;
	/** @var EventMapper */
	private $eventMapper;

	/**
	 * {@inheritDoc}
	 */
	public function setUp() {
		parent::setUp();
		$this->con = \OC::$server->getDatabaseConnection();
		$this->commentMapper = new CommentMapper($this->con);
		$this->eventMapper = new EventMapper($this->con);
	}

	/**
	 * Create some fake data and persist them to the database.
	 *
	 * @return Comment
	 */
	public function testCreate() {
		/** @var Event $event */
		$event = $this->fm->instance('OCA\Forms\Db\Event');
		$this->assertInstanceOf(Event::class, $this->eventMapper->insert($event));

		/** @var Comment $comment */
		$comment = $this->fm->instance('OCA\Forms\Db\Comment');
		$comment->setFormId($event->getId());
		$this->assertInstanceOf(Comment::class, $this->commentMapper->insert($comment));

		return $comment;
	}

	/**
	 * Update the previously created entry and persist the changes.
	 *
	 * @depends testCreate
	 * @param Comment $comment
	 * @return Comment
	 */
	public function testUpdate(Comment $comment) {
		$newComment = Faker::paragraph();
		$comment->setComment($newComment());
		$this->commentMapper->update($comment);

		return $comment;
	}

	/**
	 * Delete the previously created entries from the database.
	 *
	 * @depends testUpdate
	 * @param Comment $comment
	 */
	public function testDelete(Comment $comment) {
		$event = $this->eventMapper->find($comment->getFormId());
		$this->commentMapper->delete($comment);
		$this->eventMapper->delete($event);
	}
}
