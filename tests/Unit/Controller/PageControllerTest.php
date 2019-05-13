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

namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Controller\PageController;
use OCA\Forms\Tests\Unit\UnitTestCase;
use OCP\AppFramework\Http\TemplateResponse;

class PageControllerTest extends UnitTestCase {

	/** @var PageController */
	private $controller;

	private $userId = 'john';

	/**
	 * {@inheritDoc}
	 */
	public function setUp() {
		$avatarManager = $this->getMockBuilder('OCP\IAvatarManager')
			->disableOriginalConstructor()
			->getMock();
		$config = $this->getMockBuilder('OCP\IConfig')
			->disableOriginalConstructor()
			->getMock();
		$groupManager = $this->getMockBuilder('OCP\IGroupManager')
			->disableOriginalConstructor()
			->getMock();
		$l10n = $this->getMockBuilder('OCP\IL10N')
			->disableOriginalConstructor()
			->getMock();
		$logger = $this->getMockBuilder('OCP\ILogger')
			->disableOriginalConstructor()
			->getMock();
		$request = $this->getMockBuilder('OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$urlGenerator = $this->getMockBuilder('OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();
		$userManager = $this->getMockBuilder('OCP\IUserManager')
			->disableOriginalConstructor()
			->getMock();
		$transFactory = $this->getMockBuilder('OCP\L10N\IFactory')
			->disableOriginalConstructor()
			->getMock();
		$mailer = $this->getMockBuilder('OCP\Mail\IMailer')
			->disableOriginalConstructor()
			->getMock();
		$commentMapper = $this->getMockBuilder('OCA\Forms\Db\CommentMapper')
			->disableOriginalConstructor()
			->getMock();
		$optionMapper = $this->getMockBuilder('OCA\Forms\Db\OptionMapper')
			->disableOriginalConstructor()
			->getMock();
		$eventMapper = $this->getMockBuilder('OCA\Forms\Db\EventMapper')
			->disableOriginalConstructor()
			->getMock();
		$notificationMapper = $this->getMockBuilder('OCA\Forms\Db\NotificationMapper')
			->disableOriginalConstructor()
			->getMock();
		$voteMapper = $this->getMockBuilder('OCA\Forms\Db\VoteMapper')
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new PageController(
			'forms',
			$request,
			$config,
			$userManager,
			$groupManager,
			$avatarManager,
			$logger,
			$l10n,
			$transFactory,
			$urlGenerator,
			$this->userId,
			$commentMapper,
			$optionMapper,
			$eventMapper,
			$notificationMapper,
			$voteMapper,
			$mailer
		);
	}

	/**
	 * Basic controller index route test.
	 */
	public function testIndex() {
		$result = $this->controller->index();

		$this->assertEquals('forms.tmpl', $result->getTemplateName());
		$this->assertInstanceOf(TemplateResponse::class, $result);
	}
}
