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

namespace OCA\Forms\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Service\CirclesService;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

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
	) {
		parent::__construct($appName, $request);
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Add a new share
	 *
	 * @param int $formId The form to share
	 * @param int $shareType Nextcloud-ShareType
	 * @param string $shareWith ID of user/group/... to share with. For Empty shareWith and shareType Link, this will be set as RandomID.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newShare(int $formId, int $shareType, string $shareWith = '', array $permissions = [Constants::PERMISSION_SUBMIT]): DataResponse {
		$this->logger->debug('Adding new share: formId: {formId}, shareType: {shareType}, shareWith: {shareWith}, permissions: {permissions}', [
			'formId' => $formId,
			'shareType' => $shareType,
			'shareWith' => $shareWith,
			'permissions' => $permissions,
		]);

		// Only accept usable shareTypes
		if (array_search($shareType, Constants::SHARE_TYPES_USED) === false) {
			$this->logger->debug('Invalid shareType');
			throw new OCSBadRequestException('Invalid shareType');
		}

		// Block LinkShares if not allowed
		if ($shareType === IShare::TYPE_LINK && !$this->configService->getAllowPublicLink()) {
			$this->logger->debug('Link Share not allowed.');
			throw new OCSForbiddenException('Link Share not allowed.');
		}

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form', ['exception' => $e]);
			throw new OCSBadRequestException('Could not find form');
		}

		// Check for permission to share form
		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
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
					$nonex = $this->shareMapper->findPublicShareByHash($shareWith);

					// If we come here, a share has been found --> The share hash already exists, thus aborting.
					$this->logger->debug('Share Hash already exists.');
					throw new OCSException('Share Hash exists. Please retry.');
				} catch (DoesNotExistException $e) {
					// Just continue, this is what we expect to happen (share hash not existing yet).
				}
				break;

			case IShare::TYPE_CIRCLE:
				if (!$this->circlesService->isCirclesEnabled()) {
					$this->logger->debug('Teams app is disabled, sharing to teams not possible.');
					throw new OCSException('Teams app is disabled.');
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
				throw new OCSException('Unknown shareType.');
		}

		$share = new Share();
		$share->setFormId($formId);
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$share->setPermissions($permissions);

		/** @var Share */
		$share = $this->shareMapper->insert($share);

		// Create share-notifications (activity)
		$this->formsService->notifyNewShares($form, $share);
		
		$this->formsService->setLastUpdatedTimestamp($formId);

		// Append displayName for Frontend
		$shareData = $share->read();
		$shareData['displayName'] = $this->formsService->getShareDisplayName($shareData);

		return new DataResponse($shareData);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a share
	 *
	 * @param int $id of the share to delete
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteShare(int $id): DataResponse {
		$this->logger->debug('Deleting share: {id}', [
			'id' => $id
		]);

		try {
			$share = $this->shareMapper->findById($id);
			$form = $this->formMapper->findById($share->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find share', ['exception' => $e]);
			throw new OCSBadRequestException('Could not find share');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		$this->shareMapper->deleteById($id);

		$this->formsService->setLastUpdatedTimestamp($form->getId());

		return new DataResponse($id);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Update permissions of a share
	 *
	 * @param int $id of the share to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateShare(int $id, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating share: {id}, permissions: {permissions}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$share = $this->shareMapper->findById($id);
			$form = $this->formMapper->findById($share->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find share', ['exception' => $e]);
			throw new OCSBadRequestException('Could not find share');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		//Don't allow to change other properties than permissions
		if (count($keyValuePairs) > 1 || !key_exists('permissions', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update other properties than permissions');
			throw new OCSForbiddenException();
		}

		if (!$this->validatePermissions($keyValuePairs['permissions'], $share->getShareType())) {
			throw new OCSBadRequestException('Invalid permission given');
		}

		$share->setPermissions($keyValuePairs['permissions']);
		$share = $this->shareMapper->update($share);

		$this->formsService->setLastUpdatedTimestamp($form->getId());

		return new DataResponse($share->getId());
	}

	/**
	 * Validate user given permission array
	 *
	 * @param array $permissions User given permissions
	 * @return bool True if permissions are valid, False otherwise
	 * @throws OCSBadRequestException If invalid permission was given
	 */
	protected function validatePermissions(array $permissions, int $shareType): bool {
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
