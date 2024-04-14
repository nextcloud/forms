<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Christian Hartmann <chris-hartmann@gmx.de>
 *
 * @author Christian Hartmann <chris-hartmann@gmx.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
