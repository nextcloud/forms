<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\ResponseDefinitions;
use OCA\Forms\Service\CirclesService;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type FormsShare from ResponseDefinitions
 */
class ShareApiController extends OCSController {
	private IUser $currentUser;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private FormMapper $formMapper,
		private ShareMapper $shareMapper,
		private ConfigService $configService,
		private FormsService $formsService,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private ISecureRandom $secureRandom,
		private CirclesService $circlesService,
		private IRootFolder $rootFolder,
		private IManager $shareManager,
	) {
		parent::__construct($appName, $request);
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Add a new share
	 *
	 * @param int $formId The form to share
	 * @param int $shareType Nextcloud-ShareType
	 * @param string $shareWith ID of user/group/... to share with. For Empty shareWith and shareType Link, this will be set as RandomID.
	 * @param list<string> $permissions the permissions granted on the share, defaults to `submit`
	 *                                  Possible values:
	 *                                  - `submit` user can submit
	 *                                  - `results` user can see the results
	 *                                  - `results_delete` user can see and delete results
	 * @return DataResponse<Http::STATUS_CREATED, FormsShare, array{}>
	 * @throws OCSBadRequestException Invalid shareType
	 * @throws OCSBadRequestException Invalid permission given
	 * @throws OCSBadRequestException Invalid user to share with
	 * @throws OCSBadRequestException Invalid group to share with
	 * @throws OCSBadRequestException Invalid team to share with
	 * @throws OCSBadRequestException Unknown shareType
	 * @throws OCSBadRequestException Share hash exists, please try again
	 * @throws OCSBadRequestException Teams app is disabled
	 * @throws OCSForbiddenException Link share not allowed
	 * @throws OCSForbiddenException This form is not owned by the current user
	 * @throws OCSNotFoundException Could not find form
	 *
	 * 201: the created share
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/shares')]
	public function newShare(int $formId, int $shareType, string $shareWith = '', array $permissions = [Constants::PERMISSION_SUBMIT]): DataResponse {
		$this->logger->debug('Adding new share: formId: {formId}, shareType: {shareType}, shareWith: {shareWith}, permissions: {permissions}', [
			'formId' => $formId,
			'shareType' => $shareType,
			'shareWith' => $shareWith,
			'permissions' => $permissions,
		]);

		$form = $this->formsService->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		// Only accept usable shareTypes
		if (array_search($shareType, Constants::SHARE_TYPES_USED) === false) {
			$this->logger->debug('Invalid shareType');
			throw new OCSBadRequestException('Invalid shareType');
		}

		// Block LinkShares if not allowed
		if ($shareType === IShare::TYPE_LINK && !$this->configService->getAllowPublicLink()) {
			$this->logger->debug('Link Share not allowed.');
			throw new OCSForbiddenException('Link share not allowed.');
		}

		if (!$this->validatePermissions($permissions, $shareType)) {
			throw new OCSBadRequestException('Invalid permission given');
		}

		// Create public-share hash, if necessary.
		if ($shareType === IShare::TYPE_LINK) {
			$shareWith = $this->secureRandom->generate(
				24,
				ISecureRandom::CHAR_HUMAN_READABLE
			);
		}

		// Check for valid shareWith, needs to be done separately per shareType
		switch ($shareType) {
			case IShare::TYPE_USER:
				if (!($this->userManager->get($shareWith) instanceof IUser)) {
					$this->logger->debug('Invalid user to share with.');
					throw new OCSBadRequestException('Invalid user to share with.');
				}
				break;

			case IShare::TYPE_GROUP:
				if (!($this->groupManager->get($shareWith) instanceof IGroup)) {
					$this->logger->debug('Invalid group to share with.');
					throw new OCSBadRequestException('Invalid group to share with.');
				}
				break;

			case IShare::TYPE_LINK:
				// Check if hash already exists. (Unfortunately not possible here by unique index on db.)
				try {
					// Try loading a share to the hash.
					$this->shareMapper->findPublicShareByHash($shareWith);

					// If we come here, a share has been found --> The share hash already exists, thus aborting.
					$this->logger->debug('Share hash already exists.');
					throw new OCSBadRequestException('Share hash exists, please retry.');
				} catch (DoesNotExistException $e) {
					// Just continue, this is what we expect to happen (share hash not existing yet).
				}
				break;

			case IShare::TYPE_CIRCLE:
				if (!$this->circlesService->isCirclesEnabled()) {
					$this->logger->debug('Teams app is disabled, sharing to teams not possible.');
					throw new OCSBadRequestException('Teams app is disabled.');
				}
				$circle = $this->circlesService->getCircle($shareWith);
				if (is_null($circle)) {
					$this->logger->debug('Invalid team to share with.');
					throw new OCSBadRequestException('Invalid team to share with.');
				}
				break;

			default:
				// This passed the check for used shareTypes, but has not been found here.
				$this->logger->warning('Unknown, but used shareType: {shareType}. Please file an issue on GitHub.', [ 'shareType' => $shareType ]);
				throw new OCSBadRequestException('Unknown shareType.');
		}

		$this->formsService->obtainFormLock($form);

		$share = new Share();
		$share->setFormId($formId);
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$share->setPermissions($permissions);

		/** @var Share */
		$share = $this->shareMapper->insert($share);
		$this->formMapper->update($form);

		// Create share-notifications (activity)
		$this->formsService->notifyNewShares($form, $share);

		// Append displayName for Frontend
		$shareData = $share->read();
		$shareData['displayName'] = $this->formsService->getShareDisplayName($shareData);

		return new DataResponse($shareData, Http::STATUS_CREATED);
	}

	/**
	 * Update permissions of a share
	 *
	 * @param int $formId of the form
	 * @param int $shareId of the share to update
	 * @param array<string, mixed> $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Share doesn't belong to given Form
	 * @throws OCSBadRequestException Invalid permission given
	 * @throws OCSForbiddenException This form is not owned by the current user
	 * @throws OCSForbiddenException Empty keyValuePairs, will not update
	 * @throws OCSForbiddenException Not allowed to update other properties than permissions
	 * @throws OCSNotFoundException Could not find share
	 *
	 * 200: the id of the updated share
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}/shares/{shareId}')]
	public function updateShare(int $formId, int $shareId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating share: {shareId} of form {formId}, permissions: {permissions}', [
			'formId' => $formId,
			'shareId' => $shareId,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->formsService->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$formShare = $this->shareMapper->findById($shareId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find share', ['exception' => $e]);
			throw new OCSNotFoundException('Could not find share');
		}

		if ($formId !== $formShare->getFormId()) {
			$this->logger->debug('This share doesn\'t belong to the given Form');
			throw new OCSBadRequestException('Share doesn\'t belong to given Form');
		}

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException('Empty keyValuePairs, will not update');
		}

		//Don't allow to change other properties than permissions
		if (count($keyValuePairs) > 1 || !array_key_exists('permissions', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update other properties than permissions');
			throw new OCSForbiddenException('Not allowed to update other properties than permissions');
		}

		if (!$this->validatePermissions($keyValuePairs['permissions'], $formShare->getShareType())) {
			throw new OCSBadRequestException('Invalid permission given');
		}

		$this->formsService->obtainFormLock($form);

		$formShare->setPermissions($keyValuePairs['permissions']);
		$formShare = $this->shareMapper->update($formShare);

		if (in_array($formShare->getShareType(), [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_USERGROUP, IShare::TYPE_CIRCLE], true)) {
			$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());
			$uploadedFilesFolderPath = $this->formsService->getFormUploadedFilesFolderPath($form);
			if ($userFolder->nodeExists($uploadedFilesFolderPath)) {
				$folder = $userFolder->get($uploadedFilesFolderPath);
			} else {
				$folder = $userFolder->newFolder($uploadedFilesFolderPath);
			}
			/** @var \OCP\Files\Folder $folder */

			if (in_array(Constants::PERMISSION_RESULTS, $keyValuePairs['permissions'], true)) {
				$folderShare = $this->shareManager->newShare();
				$folderShare->setShareType($formShare->getShareType());
				$folderShare->setSharedWith($formShare->getShareWith());
				$folderShare->setSharedBy($form->getOwnerId());
				$folderShare->setPermissions(\OCP\Constants::PERMISSION_READ);
				$folderShare->setNode($folder);
				$folderShare->setShareOwner($form->getOwnerId());

				$this->shareManager->createShare($folderShare);
			} else {
				$folderShares = $this->shareManager->getSharesBy($form->getOwnerId(), $formShare->getShareType(), $folder);
				foreach ($folderShares as $folderShare) {
					if ($folderShare->getSharedWith() === $formShare->getShareWith()) {
						$this->shareManager->deleteShare($folderShare);
					}
				}
			}
		}

		$this->formMapper->update($form);

		return new DataResponse($formShare->getId());
	}

	/**
	 * Delete a share
	 *
	 * @param int $formId of the form
	 * @param int $shareId of the share to delete
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Share doesn't belong to given Form
	 * @throws OCSForbiddenException This form is not owned by the current user
	 * @throws OCSNotFoundException Could not find share
	 *
	 * 200: the id of the deleted share
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}/shares/{shareId}')]
	public function deleteShare(int $formId, int $shareId): DataResponse {
		$this->logger->debug('Deleting share: {shareId} of form {formId}', [
			'formId' => $formId,
			'shareId' => $shareId,
		]);

		$form = $this->formsService->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$share = $this->shareMapper->findById($shareId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find share', ['exception' => $e]);
			throw new OCSNotFoundException('Could not find share');
		}

		if ($formId !== $share->getFormId()) {
			$this->logger->debug('This share doesn\'t belong to the given Form');
			throw new OCSBadRequestException('Share doesn\'t belong to given Form');
		}

		$this->formsService->obtainFormLock($form);

		$this->shareMapper->delete($share);
		$this->formMapper->update($form);

		return new DataResponse($shareId);
	}

	/**
	 * Validate user given permission array
	 *
	 * @param array $permissions User given permissions
	 * @return bool True if permissions are valid, False otherwise
	 * @throws OCSBadRequestException If invalid permission was given
	 */
	private function validatePermissions(array $permissions, int $shareType): bool {
		if (count($permissions) === 0) {
			return false;
		}

		$sanitizedPermissions = array_intersect(Constants::PERMISSION_ALL, $permissions);
		if (count($sanitizedPermissions) < count($permissions)) {
			$this->logger->debug('Invalid permission given', ['invalid_permissions' => array_diff($permissions, $sanitizedPermissions)]);
			return false;
		}

		if (!in_array(Constants::PERMISSION_SUBMIT, $sanitizedPermissions)) {
			$this->logger->debug('Submit permission must always be granted');
			return false;
		}

		if (in_array(Constants::PERMISSION_RESULTS_DELETE, $sanitizedPermissions) && !in_array(Constants::PERMISSION_RESULTS, $sanitizedPermissions)) {
			$this->logger->debug('Permission results_delete is only allowed when permission results is also set');
			return false;
		}

		// Make sure only users can have special permissions
		if (count($sanitizedPermissions) > 1) {
			switch ($shareType) {
				case IShare::TYPE_USER:
				case IShare::TYPE_GROUP:
				case IShare::TYPE_CIRCLE:
					break;
				case IShare::TYPE_LINK:
					// For link shares we only allow the embedding permission
					if (count($sanitizedPermissions) > 2 || !in_array(Constants::PERMISSION_EMBED, $sanitizedPermissions)) {
						return false;
					}
					break;
				default:
					// e.g. link shares ...
					return false;
			}
		}
		return true;
	}
}
