<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
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

namespace OCA\Forms\AppInfo;

use OCP\AppFramework\App;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;

class Application extends App {

	/**
	 * Application constructor.
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct('forms', $urlParams);
	}

	/**
	 * Register navigation entry for main navigation.
	 */
	public function registerNavigationEntry(): void {
		$container = $this->getContainer();
		$container->query(INavigationManager::class)->add(function() use ($container) {
			$urlGenerator = $container->query(IURLGenerator::class);
			$l10n = $container->query(IL10N::class);
			return [
				'id' => 'forms',
				'order' => 77,
				'href' => $urlGenerator->linkToRoute('forms.page.index'),
				'icon' => $urlGenerator->imagePath('forms', 'app.svg'),
				'name' => $l10n->t('Forms')
			];
		});
	}
}
