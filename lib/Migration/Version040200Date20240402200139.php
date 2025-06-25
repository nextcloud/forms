<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCA\Forms\Constants;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add new column access_enum and update with data from access_json
 */
class Version040200Date20240402200139 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('forms_v2_forms');

		// Abort if already existing.
		if ($table->hasColumn('access_enum')) {
			return null;
		}

		// Create new column
		$table->addColumn('access_enum', Types::SMALLINT, [
			'notnull' => false,
			'default' => null,
			'unsigned' => true,
		]);

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbFetch = $this->db->getQueryBuilder();
		$qbUpdate = $this->db->getQueryBuilder();

		// Prepare Queries
		$qbFetch->select('id', 'access_json')
			->from('forms_v2_forms');

		$qbUpdate->update('forms_v2_forms')
			->set('access_enum', $qbUpdate->createParameter('access_enum'))
			->where($qbUpdate->expr()->eq('id', $qbUpdate->createParameter('id')));

		// Fetch Forms...
		$cursor = $qbFetch->executeQuery();

		// ... then handle each existing form and translate its sharing settings.
		while ($row = $cursor->fetch()) {
			// Decode access to array (param assoc=true)
			$access = json_decode($row['access_json'], true);

			$value = Constants::FORM_ACCESS_NOPUBLICSHARE;

			// No further permissions -> 0
			// Permit all users, but don't show in navigation -> 1
			// Permit all users and show in navigation -> 2
			if (!$access['permitAllUsers'] && !$access['showToAllUsers']) {
				$value = Constants::FORM_ACCESS_NOPUBLICSHARE;
			} elseif ($access['permitAllUsers'] && !$access['showToAllUsers']) {
				$value = Constants::FORM_ACCESS_PERMITALLUSERS;
			} else {
				$value = Constants::FORM_ACCESS_SHOWTOALLUSERS;
			}

			// If legacyLink add 3
			if (isset($access['legacyLink']) && $access['legacyLink']) {
				$value += Constants::FORM_ACCESS_LEGACYLINK;
			}

			$qbUpdate->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
				->setParameter('access_enum', $value, IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$cursor->closeCursor();
	}
}
