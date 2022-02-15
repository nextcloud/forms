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
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

class ShareApiController extends OCSController {
	protected $appName;

	/** @var FormMapper */
	private $formMapper;

	/** @var ShareMapper */
	private $shareMapper;

	/** @var FormsService */
	private $formsService;

	/** @var ILogger */
	private $logger;
	
	/** @var IUser */
	private $currentUser;

	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(string $appName,
								FormMapper $formMapper,
								ShareMapper $shareMapper,
								FormsService $formsService,
								ILogger $logger,
								IRequest $request,
								IUserSession $userSession,
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->formMapper = $formMapper;
		$this->shareMapper = $shareMapper;
		$this->formsService = $formsService;
		$this->logger = $logger;
		$this->secureRandom = $secureRandom;

		$this->currentUser = $userSession->getUser();
	}

	/**
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
	public function newShare(int $formId, int $shareType, string $shareWith = ''): DataResponse {
		$this->logger->debug('Adding new share: formId: {formId}, shareType: {shareType}, shareWith: {shareWith}', [
			'formId' => $formId,
			'shareType' => $shareType,
			'shareWith' => $shareWith,
		]);

		// Only accept usable shareTypes
		if (array_search($shareType, Constants::SHARE_TYPES_USED) === false) {
			$this->logger->debug('Invalid shareType');
			throw new OCSBadRequestException('Invalid shareType');
		}

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException('Could not find form');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		$share = new Share();

		$share->setFormId($formId);
		$share->setShareType($shareType);

		// Create public-share hash, if necessary.
		if ($shareType === IShare::TYPE_LINK && $shareWith === '') {
			$share->setShareWith($this->secureRandom->generate(
				24,
				ISecureRandom::CHAR_HUMAN_READABLE
			));
		} else {
			$share->setShareWith($shareWith);
		}

		// For public-shares, check if hash already exists. (Unfortunately not possible here by unique index on db.)
		if ($shareType === IShare::TYPE_LINK) {
			try {
				// Try loading a share to the hash.
				$nonex = $this->shareMapper->findPublicShareByHash($share->getShareWith());

				// If we come here, a share has been found --> The share hash already exists, thus aborting.
				$this->logger->debug('Share Hash already exists.');
				throw new OCSException('Share Hash exists. Please retry.');
			} catch (DoesNotExistException $e) {
				// Just continue, this is what we expect to happen (share hash not existing yet).
			}
		}

		$share = $this->shareMapper->insert($share);

		// Append displayName for Frontend
		$shareData = $share->read();
		$shareData['displayName'] = $this->formsService->getShareDisplayName($shareData);

		return new DataResponse($shareData);
	}

	/**
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
			$this->logger->debug('Could not find share');
			throw new OCSBadRequestException('Could not find share');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		$this->shareMapper->deleteById($id);

		return new DataResponse($id);
	}
}
