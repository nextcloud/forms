<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Helper\FilePathHelper;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class UploadedFilesShareService {
	public function __construct(
		private readonly FilePathHelper $filePathHelper,
		private readonly IManager $shareManager,
		private readonly ShareMapper $shareMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * remove all linked files shares (oc_share) for collaborators who have 'view responses' on the given form.
	 */
	public function removeAllForForm(Form $form): void {
		$formShares = $this->shareMapper->findByForm($form->getId());
		foreach ($formShares as $formShare) {
			if (in_array(Constants::PERMISSION_RESULTS, $formShare->getPermissions(), true)) {
				$this->removeForCollaborator($form, $formShare);
			}
		}
	}

	/**
	 * remove the linked files share (oc_share) for one collaborator (either user, group, or team) on the given form.
	 */
	public function removeForCollaborator(Form $form, Share $formShare): void {
		if (!in_array($formShare->getShareType(), [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_USERGROUP, IShare::TYPE_CIRCLE], true)) {
			return;
		}

		try {
			$folders = $this->filePathHelper->getAllFormFoldersById($form->getId(), $form->getOwnerId());
			foreach ($folders as $folder) {
				$folderShares = $this->shareManager->getSharesBy($form->getOwnerId(), $formShare->getShareType(), $folder, false, -1);
				foreach ($folderShares as $folderShare) {
					if ($folderShare->getSharedWith() === $formShare->getShareWith()) {
						$this->shareManager->deleteShare($folderShare);
					}
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to remove uploaded files share for form', ['exception' => $e]);
		}
	}
}
