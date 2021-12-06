<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
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
namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Controller\ShareApiController;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ShareApiControllerTest extends TestCase {

	/** @var ShareApiController */
	private $shareApiController;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var ShareMapper|MockObject */
	private $shareMapper;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var IRequest|MockObject */
	private $request;

	public function setUp(): void {
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->request = $this->createMock(IRequest::class);
		$userSession = $this->createMock(IUserSession::class);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->shareApiController = new ShareApiController(
			'forms',
			$this->formMapper,
			$this->shareMapper,
			$this->formsService,
			$this->logger,
			$this->request,
			$userSession
		);
	}

	public function dataValidNewShare() {
		return [
			'newUserShare' => [
				'shareType' => 0,
				'shareWith' => 'user1',
			],
			'newGroupShare' => [
				'shareType' => 1,
				'shareWith' => 'group1',
			]
		];
	}
	/**
	 * Test valid shares
	 * @dataProvider dataValidNewShare
	 *
	 * @param int $shareType
	 * @param string $shareWith
	 */
	public function testValidNewShare(int $shareType, string $shareWith) {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$share = new Share();
		$share->setformId('5');
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$shareWithId = clone $share;
		$shareWithId->setId(13);
		$this->shareMapper->expects($this->once())
			->method('insert')
			->with($share)
			->willReturn($shareWithId);

		$this->formsService->expects($this->once())
			->method('getShareDisplayName')
			->with($shareWithId->read())
			->willReturn($shareWith . ' DisplayName');

		// Share Form '5' to 'user1' of share-type 'user=0'
		$this->shareApiController->newShare(5, $shareType, $shareWith);
	}

	/**
	 * Test an unused (but existing) Share-Type
	 */
	public function testBadShareType() {
		$this->expectException(OCSBadRequestException::class);

		// Share Form '5' to 'user1' of share-type 'deck_user=13'
		$this->shareApiController->newShare(5, 13, 'user1');
	}

	/**
	 * Sharing a non-existing form.
	 */
	public function testShareUnknownForm() {
		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->will($this->throwException(new DoesNotExistException('Form not found')));
		;

		$this->expectException(OCSBadRequestException::class);
		$this->shareApiController->newShare(5, 0, 'user1');
	}

	/**
	 * Share form of other owner
	 */
	public function testShareForeignForm() {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('someOtherUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->expectException(OCSForbiddenException::class);
		$this->shareApiController->newShare(5, 0, 'user1');
	}

	/**
	 * Delete a share.
	 */
	public function testDeleteShare() {
		$share = new Share();
		$share->setId(8);
		$share->setFormId(5);
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with('8')
			->willReturn($share);

		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');
		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->shareMapper->expects($this->once())
			->method('deleteById')
			->with('8');
		
		$response = new DataResponse(8);
		$this->assertEquals($response, $this->shareApiController->deleteShare(8));
	}

	/**
	 * Delete Non-existing share.
	 */
	public function testDeleteUnknownShare() {
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with('8')
			->will($this->throwException(new DoesNotExistException('Share not found')));
		;

		$this->expectException(OCSBadRequestException::class);
		$this->shareApiController->deleteShare(8);
	}

	/**
	 * Delete share from form of other owner.
	 */
	public function testDeleteForeignShare() {
		$share = new Share();
		$share->setId(8);
		$share->setFormId(5);
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with('8')
			->willReturn($share);

		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('someOtherUser');
		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->expectException(OCSForbiddenException::class);
		$this->shareApiController->deleteShare(8);
	}
}
