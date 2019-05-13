<?php
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

use OCA\Forms\Controller\PageController;
use OCA\Forms\Controller\ApiController;
use OCA\Forms\Db\CommentMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\EventMapper;
use OCA\Forms\Db\NotificationMapper;
use OCA\Forms\Db\VoteMapper;
use OCP\AppFramework\App;
use OCP\IContainer;

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
	public function registerNavigationEntry() {
		$container = $this->getContainer();
		$container->query('OCP\INavigationManager')->add(function() use ($container) {
			$urlGenerator = $container->query('OCP\IURLGenerator');
			$l10n = $container->query('OCP\IL10N');
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
