<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Activity;

use OCA\Circles\Model\Circle;
use OCA\Forms\Activity\Provider;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\CirclesService;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\RichObjectStrings\IValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ProviderTest extends TestCase {

	/** @var Provider */
	private $provider;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var IEventMerger|MockObject */
	private $eventMerger;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IFactory|MockObject */
	private $l10nFactory;

	/** @var IValidator|MockObject */
	private $validator;

	/** @var CirclesService|MockObject */
	private $circlesService;

	public function setUp(): void {
		parent::setUp();
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->eventMerger = $this->createMock(IEventMerger::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->validator = $this->createMock(IValidator::class);
		$this->circlesService = $this->createMock(CirclesService::class);

		$this->urlGenerator->expects($this->any())
			->method('linkToRouteAbsolute')
			->with('forms.page.index')
			->willReturn('http://localhost/apps/forms/');
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteUrl')
			->will($this->returnCallback(function (string $path) {
				return 'http://localhost' . $path;
			}));
		$this->urlGenerator->expects($this->any())
			->method('imagePath')
			->willReturnMap([
				['core', 'actions/shared.svg', '/core/img/actions/shared.svg'],
				['core', 'actions/add.svg', '/core/img/actions/add.svg'],
				['forms', 'forms-dark.svg', '/apps/forms/img/forms-dark.svg']
			]);

		$this->provider = new Provider('forms',
			$this->formMapper,
			$this->eventMerger,
			$this->groupManager,
			$this->logger,
			$this->urlGenerator,
			$this->userManager,
			$this->l10nFactory,
			$this->validator,
			$this->circlesService);

		// Only for the test, Provider creates it from Factory
		$this->l10n = $this->createMock(IL10N::class);
	}

	// Wrong app-name should be blocked
	public function testWrongApp() {
		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('someOtherApp');

		$this->expectException(\InvalidArgumentException::class);
		$this->provider->parse('de_DE', $event);
	}

	// Testing the parse function needs a full parse.
	public function testFullParse() {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function (string $identity) {
				return $identity;
			}));
		$this->l10nFactory->expects($this->once())
			->method('get')
			->willReturn($l10n);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('The affected User');
		$this->userManager->expects($this->once())
			->method('get')
			->with('affectedUser')
			->willReturn($user);

		$form = new Form;
		$form->setTitle('SomeChangedNiceFormTitle');
		$this->formMapper->expects($this->once())
			->method('findbyHash')
			->with('54321gfedcba')
			->willReturn($form);

		$event = $this->createMock(IEvent::class);
		$event->expects($this->once())
			->method('getApp')
			->willReturn('forms');
		$event->expects($this->atLeastOnce())
			->method('getSubject')
			->willReturn('newsubmission');
		$event->expects($this->atLeastOnce())
			->method('getSubjectParameters')
			->willReturn([
				'userId' => 'affectedUser',
				'formTitle' => 'SomeNiceFormTitle',
				'formHash' => '54321gfedcba'
			]);
		$event->expects($this->once())
			->method('setParsedSubject')
			->with('Your form SomeChangedNiceFormTitle was answered by The affected User');
		$event->expects($this->once())
			->method('setRichSubject')
			->with('Your form {formTitle} was answered by {user}', [
				'user' => [
					'type' => 'user',
					'id' => 'affectedUser',
					'name' => 'The affected User'
				],
				'formTitle' => [
					'type' => 'forms-form',
					'id' => '54321gfedcba',
					'name' => 'SomeChangedNiceFormTitle',
					'link' => 'http://localhost/apps/forms/54321gfedcba/results'
				]]);
		$event->expects($this->once())
			->method('setIcon')
			->with('http://localhost/core/img/actions/add.svg');

		$this->eventMerger->expects($this->once())
			->method('mergeEvents')
			->with('user', $event, $event)
			->willReturn($event);

		$this->assertEquals($event, $this->provider->parse('de_DE', $event, $event));
	}

	// Expected data for Subject-Strings
	public function dataGetSubjectString() {
		return [
			['newshare', '{user} has shared the form {formTitle} with you'],
			['newgroupshare', '{user} has shared the form {formTitle} with group {group}'],
			['newcircleshare', '{user} has shared the form {formTitle} with team {circle}'],
			['newsubmission', 'Your form {formTitle} was answered by {user}']
		];
	}
	/**
	 * @dataProvider dataGetSubjectString
	 *
	 * @param string $subject
	 * @param string $expected
	 */
	public function testGetSubjectString(string $subject, string $expected) {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function (string $identity) {
				return $identity;
			}));

		$this->assertEquals($expected, $this->provider->getSubjectString($l10n, $subject));
	}

	// Unknown Subject throws exception
	public function testGetUnknownSubjectString() {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->never())
			->method('t');

		$this->expectException(\InvalidArgumentException::class);
		$this->provider->getSubjectString($l10n, 'someUnknownSubject');
	}

	// Test insertions of parameters name
	public function testParseSubjectString() {
		$this->assertEquals('Heinz and Ernst in a Text with Erna',
			$this->provider->parseSubjectString('{param1} and {param2} in a Text with {paramz}', [
				'param1' => [
					'type' => 'xy',
					'name' => 'Heinz'
				],
				'param2' => [
					'type' => 'ab',
					'name' => 'Ernst',
					'link' => 'http://somehost'
				],
				'paramz' => [
					'type' => 'highlight',
					'name' => 'Erna',
					'unknownprop' => 'prop'
				],
			]));
	}

	/**
	 * Typical cases of RichParams for subjects
	 */
	public function dataGetRichParams() {
		$newShareResult = [
			'user' => [
				'type' => 'user',
				'id' => 'affectedUser',
				'name' => 'The affected User'
			],
			'formTitle' => [
				'type' => 'forms-form',
				'id' => 'abcdefg',
				'name' => 'Some changed Form Title',
				'link' => 'http://localhost/apps/forms/abcdefg'
			]
		];
		// Difference only in additional Group-Param
		$newGroupShareResult = $newShareResult;
		$newGroupShareResult['group'] = [
			'type' => 'user-group',
			'id' => 'someGroup',
			'name' => 'The Group'
		];
		// Difference only in additional Circle-Param
		$newCircleShareResult = $newShareResult;
		$newCircleShareResult['circle'] = [
			'type' => 'circle',
			'id' => 'someCircle',
			'name' => 'The Circle',
			'link' => 'circle/link'
		];
		// Difference only in different Link on formTitle
		$newSubmissionResult = $newShareResult;
		$newSubmissionResult['formTitle']['link'] .= '/results';

		return [
			// [subject, [subjectParams], [expectedResult]],
			['unknownSubject', [], []],
			['newshare', [
				'userId' => 'affectedUser',
				'formTitle' => 'Some FormTitle',
				'formHash' => 'abcdefg'],
				$newShareResult
			],
			['newgroupshare', [
				'userId' => 'affectedUser',
				'formTitle' => 'Some FormTitle',
				'formHash' => 'abcdefg',
				'groupId' => 'someGroup'],
				$newGroupShareResult
			],
			['newcircleshare', [
				'userId' => 'affectedUser',
				'formTitle' => 'Some FormTitle',
				'formHash' => 'abcdefg',
				'circleId' => 'someCircle'],
				$newCircleShareResult
			],
			['newsubmission', [
				'userId' => 'affectedUser',
				'formTitle' => 'Some FormTitle',
				'formHash' => 'abcdefg'],
				$newSubmissionResult
			]
		];
	}
	/**
	 * @dataProvider dataGetRichParams
	 *
	 * @param string $subject
	 * @param array $subjectParams
	 * @param array $expected
	 */
	public function testGetRichParams(string $subject, array $subjectParams, array $expected) {
		$l10n = $this->createMock(IL10N::class);
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('The affected User');
		$this->userManager->expects($this->any())
			->method('get')
			->with('affectedUser')
			->willReturn($user);
		$group = $this->createMock(IGroup::class);
		$group->expects($this->any())
			->method('getDisplayName')
			->willReturn('The Group');
		$this->groupManager->expects($this->any())
			->method('get')
			->with('someGroup')
			->willReturn($group);
		$circle = $this->createMock(Circle::class);
		$circle->expects($this->any())
			->method('getDisplayName')
			->willReturn('The Circle');
		$circle->expects($this->any())
			->method('getUrl')
			->willReturn('circle/link');
		$this->circlesService->expects($this->any())
			->method('getCircle')
			->with('someCircle')
			->willReturn($circle);

		$form = new Form();
		$form->setHash('abcdefg');
		$form->setTitle('Some changed Form Title');
		$this->formMapper->expects($this->any())
			->method('findByHash')
			->with('abcdefg')
			->willReturn($form);

		$this->validator->expects($this->any())
			->method('validate');

		$this->assertEquals($expected, $this->provider->getRichParams($l10n, $subject, $subjectParams));
	}

	/**
	 * Basic ideal functionality tested already in testGetRichParams
	 * Only testing special cases here
	 * - Anonymous User
	 */
	public function testGetAnonymousRichUser() {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->any())
			->method('t')
			->will($this->returnCallback(function (string $identity) {
				return $identity;
			}));

		$this->assertEquals([
			'type' => 'highlight',
			'id' => 'anon-user-xyz',
			'name' => 'Anonymous user'
		], $this->provider->getRichUser($l10n, 'anon-user-xyz'));
	}

	/**
	 * Basic ideal functionality tested already in testGetRichParams
	 * Only testing special cases here
	 * - Deleted User
	 */
	public function testGetDeletedRichUser() {
		$l10n = $this->createMock(IL10N::class);
		$this->userManager->expects($this->any())
			->method('get')
			->with('someDeletedUser')
			->willReturn(null);

		$this->assertEquals([
			'type' => 'user',
			'id' => 'someDeletedUser',
			'name' => 'someDeletedUser'
		], $this->provider->getRichUser($l10n, 'someDeletedUser'));
	}

	/*
	 * Basic ideal functionality tested already in testGetRichParams
	 * Only testing special cases here
	 * - Deleted Group
	 */
	public function testGetRichGroup() {
		// Group not found
		$this->groupManager->expects($this->once())
			->method('get')
			->with('someDeletedGroup')
			->willReturn(null);
		$this->assertEquals([
			'type' => 'user-group',
			'id' => 'someDeletedGroup',
			'name' => 'someDeletedGroup'
		], $this->provider->getRichGroup('someDeletedGroup'));
	}

	/*
	 * Basic ideal functionality tested already in testGetRichParams
	 * Only testing special cases here
	 * - Deleted Circle
	 * - Circles disabled
	 * In both cases `circlesService` will return `null`:
	 */
	public function testGetRichCircle_disabled() {
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->with('someCircle')
			->willReturn(null);

		$this->assertEquals([
			'type' => 'circle',
			'id' => 'someCircle',
			'name' => 'someCircle',
			'link' => ''
		], $this->provider->getRichCircle('someCircle'));
	}

	/*
	 * Basic ideal functionality tested already in testGetRichParams
	 * Only testing special cases here
	 * - Form not found
	 */
	public function testGetRichFormTitle() {
		$this->formMapper->expects($this->any())
			->method('findbyHash')
			->with('abcdefg')
			->will($this->throwException(new DoesNotExistException('Form not found')));

		$this->assertEquals([
			'type' => 'forms-form',
			'id' => 'abcdefg',
			'name' => 'Some Form Title',
			'link' => 'http://localhost/apps/forms/'
		], $this->provider->getRichFormTitle('Some Form Title', 'abcdefg'));
	}

	/**
	 * IconList for Subjects
	 */
	public function dataGetEventIcon() {
		return [
			['newshare', 'http://localhost/core/img/actions/shared.svg'],
			['newgroupshare', 'http://localhost/core/img/actions/shared.svg'],
			['newsubmission', 'http://localhost/core/img/actions/add.svg'],
			['unknownSubject', 'http://localhost/apps/forms/img/forms-dark.svg'],
		];
	}
	/**
	 * Get right event-icon
	 *
	 * @dataProvider dataGetEventIcon
	 * @param string $subject
	 * @param string $iconUrl
	 */
	public function testGetEventIcon(string $subject, string $iconUrl) {
		$this->assertEquals($iconUrl, $this->provider->getEventIcon($subject));
	}
}
