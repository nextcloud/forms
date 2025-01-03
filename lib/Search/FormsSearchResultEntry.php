<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Search;

use OCA\Forms\Db\Form;
use OCP\Search\SearchResultEntry;

class FormsSearchResultEntry extends SearchResultEntry {
	public function __construct(Form $form, string $formUrl) {
		parent::__construct('', $form->getTitle(), $form->getDescription(), $formUrl, 'forms-dark');
	}
}
