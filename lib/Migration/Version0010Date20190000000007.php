<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Installation class for the forms app.
 * Initial db creation
 */
class Version0010Date20190000000007 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/**
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forms_events')) {
			$schema->getTable('forms_events')->addColumn('unique', 'integer', [
				'notnull' => false,
				'default' => 0,
			]);
		} else {
		}
		return $schema;
	}
}
