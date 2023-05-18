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

use OCA\Forms\Constants;
use OCA\Forms\Controller\ShareApiController;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;
use Throwable;

interface MapperException extends Throwable, IMapperException {
};

class ShareApiControllerTest extends TestCase {

	/** @var ShareApiController */
	private $shareApiController;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var ShareMapper|MockObject */
	private $shareMapper;

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IRequest|MockObject */
	private $request;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var ISecureRandom|MockObject */
	private $secureRandom;

	public function setUp(): void {
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);

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
			$this->configService,
			$this->formsService,
			$this->groupManager,
			$this->logger,
			$this->request,
			$this->userManager,
			$userSession,
			$this->secureRandom
		);
	}

	public function dataValidNewShare() {
		return [
			'newUserShare' => [
				'shareType' => IShare::TYPE_USER,
				'shareWith' => 'user1',
				'permissions' => [Constants::PERMISSION_SUBMIT],
				'expected' => [
					'id' => 13,
					'formId' => 5,
					'shareType' => 0,
					'shareWith' => 'user1',
					'permissions' => [Constants::PERMISSION_SUBMIT],
					'displayName' => 'user1 DisplayName'
				]
			],
			'newGroupShare' => [
				'shareType' => IShare::TYPE_GROUP,
				'shareWith' => 'group1',
				'permissions' => [Constants::PERMISSION_RESULTS, Constants::PERMISSION_SUBMIT],
				'expected' => [
					'id' => 13,
					'formId' => 5,
					'shareType' => 1,
					'shareWith' => 'group1',
					'permissions' => [Constants::PERMISSION_RESULTS, Constants::PERMISSION_SUBMIT],
					'displayName' => 'group1 DisplayName'
				]
			],
		];
	}
	/**
	 * Test valid shares
	 * @dataProvider dataValidNewShare
	 *
	 * @param int $shareType
	 * @param string $shareWith
	 * @param array $expected
	 */
	public function testValidNewShare(int $shareType, string $shareWith, array $permissions, array $expected) {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->userManager->expects($this->any())
			->method('get')
			->with($shareWith)
			->willReturn($this->createMock(IUser::class));

		$this->groupManager->expects($this->any())
			->method('get')
			->with($shareWith)
			->willReturn($this->createMock(IGroup::class));

		$share = new Share();
		$share->setFormId(5);
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$share->setPermissions($permissions);
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
		$expectedResponse = new DataResponse($expected);
		$this->assertEquals($expectedResponse, $this->shareApiController->newShare(5, $shareType, $shareWith, $permissions));
	}

	public function dataNewLinkShare() {
		return [
			'newLinkShare' => [
				'shareType' => IShare::TYPE_LINK,
				'shareWith' => '',
				'permissions' => [Constants::PERMISSION_SUBMIT],
				'exception' => null,
				'expected' => [
					'id' => 13,
					'formId' => 5,
					'shareType' => 3,
					'shareWith' => 'abcdefgh',
					'permissions' => [Constants::PERMISSION_SUBMIT],
					'displayName' => ''
				]
			],
			'invalid-permissions' => [
				'shareType' => IShare::TYPE_LINK,
				'shareWith' => '',
				// Creating a new share must fail as PERMISSION_RESULTS is not allowed for link shares
				'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS],
				'exception' => '\OCP\AppFramework\OCS\OCSBadRequestException',
				'expected' => []
			]
		];
	}
	/**
	 * Test valid Link shares
	 * @dataProvider dataNewLinkShare
	 *
	 * @param int $shareType
	 * @param string $shareWith
	 * @param array $expected
	 */
	public function testNewLinkShare(int $shareType, string $shareWith, array $permissions, ?string $exception, array $expected) {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->secureRandom->expects($this->any())
			->method('generate')
			->willReturn('abcdefgh');

		$share = new Share();
		$share->setFormId(5);
		$share->setPermissions([Constants::PERMISSION_SUBMIT]);
		$share->setShareType($shareType);
		if ($shareWith === '') {
			$share->setShareWith('abcdefgh');
		} else {
			$share->setShareWith($shareWith);
		}
		$shareWithId = clone $share;
		$shareWithId->setId(13);
		$this->shareMapper->expects($exception === null ? $this->once() : $this->any())
			->method('insert')
			->with($share)
			->willReturn($shareWithId);

		$this->configService->expects($exception === null ? $this->once() : $this->any())
			->method('getAllowPublicLink')
			->willReturn(true);

		$this->formsService->expects($exception === null ? $this->once() : $this->any())
			->method('getShareDisplayName')
			->with($shareWithId->read())
			->willReturn('');

		$this->shareMapper->expects($exception === null ? $this->once() : $this->any())
			->method('findPublicShareByHash')
			->will($this->throwException(new DoesNotExistException('Not found.')));

		if ($exception === null) {
			// Share the form.
			$expectedResponse = new DataResponse($expected);
			$this->assertEquals($expectedResponse, $this->shareApiController->newShare(5, $shareType, $shareWith, $permissions));
		} else {
			$this->expectException($exception);
			$this->shareApiController->newShare(5, $shareType, $shareWith, $permissions);
		}
	}

	/**
	 * Test a random link hash, that is already existing.
	 */
	public function testNewLinkShare_ExistingHash() {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->configService->expects($this->once())
			->method('getAllowPublicLink')
			->willReturn(true);

		$this->secureRandom->expects($this->any())
			->method('generate')
			->willReturn('abcdefgh');

		$this->shareMapper->expects($this->once())
			->method('findPublicShareByHash')
			->with('abcdefgh')
			->willReturn(new Share());

		$this->shareMapper->expects($this->never())
			->method('insert');

		$this->expectException(OCSException::class);
		$this->shareApiController->newShare(5, IShare::TYPE_LINK, '');
	}

	/**
	 * Test a random link hash, that is already existing.
	 */
	public function testNewLinkShare_PublicLinkNotAllowed() {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->configService->expects($this->once())
			->method('getAllowPublicLink')
			->willReturn(false);

		$this->shareMapper->expects($this->never())
			->method('insert');

		$this->expectException(OCSException::class);
		$this->shareApiController->newShare(5, IShare::TYPE_LINK, '');
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
	 * Test unknown user
	 */
	public function testBadUserShare() {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->userManager->expects($this->once())
			->method('get')
			->with('noUser')
			->willReturn(null);

		$this->expectException(OCSBadRequestException::class);

		// Share Form '5' to 'noUser' of share-type 'user=0'
		$this->shareApiController->newShare(5, 0, 'noUser');
	}

	/**
	 * Test unknown group
	 */
	public function testBadGroupShare() {
		$form = new Form();
		$form->setId('5');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with('5')
			->willReturn($form);

		$this->groupManager->expects($this->once())
			->method('get')
			->with('noGroup')
			->willReturn(null);

		$this->expectException(OCSBadRequestException::class);

		// Share Form '5' to 'noUser' of share-type 'group=1'
		$this->shareApiController->newShare(5, 1, 'noGroup');
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

	public function dataUpdateShare() {
		return [
			'valid-permissions' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => 0,
					'shareWith' => 'user1',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'currentUser',
				'keyValuePairs' => [
					'permissions' => [Constants::PERMISSION_RESULTS, Constants::PERMISSION_SUBMIT]
				],
				'expected' => 1,
				'exception' => null
			],
			'no-permission' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => 0,
					'shareWith' => 'user1',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'currentUser',
				'keyValuePairs' => [
					'permissions' => []
				],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSBadRequestException'
			],
			'invalid-permission' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => 0,
					'shareWith' => 'user1',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'currentUser',
				'keyValuePairs' => [
					'permissions' => ['invalid']
				],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSBadRequestException'
			],
			'invalid-share-type-permission' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => IShare::TYPE_LINK,
					'shareWith' => 'somehash',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'currentUser',
				// PERMISSION_RESULTS is not allowed for TYPE_LINK
				'keyValuePairs' => [
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS]
				],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSBadRequestException'
			],
			'form-not-owned' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => IShare::TYPE_LINK,
					'shareWith' => 'somehash',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'otherUser',
				'keyValuePairs' => ['permissions' => [Constants::PERMISSION_SUBMIT]],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSForbiddenException'
			],
			'empty-key-value-pairs' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => IShare::TYPE_LINK,
					'shareWith' => 'somehash',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'otherUser',
				'keyValuePairs' => [],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSForbiddenException'
			],
			'invalid-key-value-pairs' => [
				'share' => [
					'id' => 1,
					'formId' => 5,
					'shareType' => IShare::TYPE_LINK,
					'shareWith' => 'somehash',
					'permissions' => [Constants::PERMISSION_SUBMIT]
				],
				'formOwner' => 'otherUser',
				'keyValuePairs' => ['formId' => 6],
				'expected' => null,
				'exception' => '\OCP\AppFramework\OCS\OCSForbiddenException'
			],
		];
	}
	/**
	 * Test update a share
	 * @dataProvider dataUpdateShare
	 */
	public function testUpdateShare(array $share, string $formOwner, array $keyValuePairs, ?int $expected, ?string $exception) {
		$form = new Form();
		$form->setId($share['formId']);
		$form->setOwnerId($formOwner);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with($share['formId'])
			->willReturn($form);

		$this->userManager->expects($this->any())
			->method('get')
			->with('otherUser')
			->willReturn($this->createMock(IUser::class));

		$shareEntity = new Share();
		$shareEntity->setId($share['id']);
		$shareEntity->setFormId($share['formId']);
		$shareEntity->setShareType($share['shareType']);
		$shareEntity->setShareWith($share['shareWith']);
		$shareEntity->setPermissions($share['permissions']);
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with($share['id'])
			->willReturn($shareEntity);

		$this->shareMapper->expects($exception === null ? $this->once() : $this->any())
			->method('update')
			->with($shareEntity)
			->willReturnCallback(function ($arg) {
				return $arg;
			});

		if ($exception === null) {
			$expectedResponse = new DataResponse($expected);
			$this->assertEquals($expectedResponse, $this->shareApiController->updateShare($share['id'], $keyValuePairs));
		} else {
			$this->expectException($exception);
			$this->shareApiController->updateShare($share['id'], $keyValuePairs);
		}
	}

	/**
	 * Test update a share
	 * @dataProvider dataUpdateShare
	 */
	public function testUpdateShare_NotExistingShare() {
		$exception = $this->createMock(MapperException::class);
		
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with(1337)
			->will($this->throwException($exception));

		$this->logger->expects($this->exactly(2))
			->method('debug');

		$this->expectException(OCSBadRequestException::class);
		$this->shareApiController->updateShare(1337, [Constants::PERMISSION_SUBMIT]);
	}

	/**
	 * Test update a share
	 * @dataProvider dataUpdateShare
	 */
	public function testUpdateShare_NotExistingForm() {
		$exception = $this->createMock(MapperException::class);
		
		$share = new Share();
		$share->setId(1337);
		$share->setFormId(7331);
		$share->setShareType(3);
		$share->setShareWith('hash');
		$share->setPermissions([]);

		$this->shareMapper->expects($this->once())
			->method('findById')
			->with(1337)
			->willReturn($share);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(7331)
			->willThrowException($exception);

		$this->logger->expects($this->exactly(2))
			->method('debug');

		$this->expectException(OCSBadRequestException::class);
		$this->shareApiController->updateShare(1337, [Constants::PERMISSION_SUBMIT]);
	}
}
