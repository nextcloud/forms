<?php
/**
 * @copyright Copyright (c) 2017 René Gieling <github@dartcafe.de>
 *
 * @author René Gieling <github@dartcafe.de>
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

namespace OCA\Forms\Migration;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Installation class for the forms app.
 * Initial db creation
 */
class Version0009Date20190000000006 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('forms_events')) {
			$table = $schema->createTable('forms_events');
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('hash', Type::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('title', Type::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('description', Type::STRING, [
				'notnull' => true,
				'length' => 1024,
			]);
			$table->addColumn('owner', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created', Type::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('access', Type::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->addColumn('expire', Type::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('is_anonymous', Type::INTEGER, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('full_anonymous', Type::INTEGER, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		} else {
		}

		if (!$schema->hasTable('forms_questions')) {
			$table = $schema->createTable('forms_questions');
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Type::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('form_question_type', Type::STRING, [
				'notnull' => false, // maybe true?
				'length' => 256,
			]);
			$table->addColumn('form_question_text', Type::STRING, [
				'notnull' => false, // maybe true?
				'length' => 4096,
			]);
			$table->addColumn('timestamp', Type::INTEGER, [
				'notnull' => false,
				'default' => 0
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_answers')) {
			$table = $schema->createTable('forms_answers');
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Type::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('question_id', Type::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('text', Type::STRING, [
				'notnull' => false, // maybe true?
				'length' => 4096,
			]);
			$table->addColumn('timestamp', Type::INTEGER, [
				'notnull' => false,
				'default' => 0
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_votes')) {
			$table = $schema->createTable('forms_votes');
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Type::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('user_id', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('vote_option_id', Type::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'length' => 64,
			]);
			$table->addColumn('vote_option_text', Type::STRING, [
				'notnull' => false, // maybe true?
				'length' => 4096,
			]);
			$table->addColumn('vote_answer', Type::STRING, [
				'notnull' => false,
				'length' => 4096,
			]);
			$table->addColumn('vote_option_type', Type::STRING, [
				'notnull' => false,
				'length' => 256,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_notif')) {
			$table = $schema->createTable('forms_notif');
			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Type::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('user_id', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
