<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
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

use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;

use Psr\Log\LoggerInterface;

class ApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IUser $currentUser,
		private LoggerInterface $logger,
		private FormMapper $formMapper,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get owned Forms for current user
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'GET', url: Constants::API_BASE . '/forms', requirements: ['apiVersion' => 'v3(\.\d+)?'])]
	public function getForms(int $limit = 25, int $offset = 0, array $filter = []): DataResponse {
		$formsFilter = [
			'name' => $filter['name'] ?? null,
		];

		$forms = $this->formMapper->findAllByOwnerId(
			$this->currentUser->getUID(),
			$formsFilter,
			$limit,
			$offset,
		);

		$data = array_values(
			array_map(fn (Form $form) => $form->readPartial(), $forms)
		);
		return new DataResponse($data);
	}
}
