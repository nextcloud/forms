<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Search;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\Db\Form;
use OCP\IURLGenerator;
use OCP\Search\SearchResultEntry;

class FormsSearchResultEntry extends SearchResultEntry {
	public function __construct(Form $form, IURLGenerator $urlGenerator) {
		$formURL = $urlGenerator->linkToRoute('forms.page.views', ['hash' => $form->getHash(), 'view' => 'submit']);
		$iconURL = $urlGenerator->getAbsoluteURL(($urlGenerator->imagePath(Application::APP_ID, 'forms-dark.svg')));
		parent::__construct($iconURL, $form->getTitle(), $form->getDescription(), $formURL, 'icon-forms');
	}
}
