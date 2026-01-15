<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Time_Archive\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create archive_rules table
 */
class Version010000Date20250101000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('archive_rules')) {
			$table = $schema->createTable('archive_rules');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('time_unit', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('time_amount', Types::SMALLINT, [
				'notnull' => true,
				'length' => 1,
			]);
			$table->addColumn('time_after', Types::INTEGER, [
				'length' => 4,
				'default' => 0,
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['tag_id'], 'archive_rules_tag');
		}

		return $schema;
	}
}
