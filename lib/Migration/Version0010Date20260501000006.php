<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0010Date20260501000006 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// check if the table already exists
		if ($schema->hasTable('davc_chronicle')) {
			return;
		}
		// create the table
		$table = $schema->createTable('davc_chronicle');
		// id
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true
		]);
		// user id
		$table->addColumn('uid', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// service id
		$table->addColumn('sid', Types::INTEGER, [
			'notnull' => true
		]);
		// tag
		$table->addColumn('tag', Types::STRING, [
			'length' => 4,
			'notnull' => true
		]);
		// collection id
		$table->addColumn('cid', Types::BIGINT, [
			'notnull' => true,
			'default' => 0
		]);
		// entity id
		$table->addColumn('eid', Types::BIGINT, [
			'notnull' => true,
			'default' => 0
		]);
		// entity uuid
		$table->addColumn('euuid', Types::STRING, [
			'length' => 64,
			'notnull' => true,
			'default' => '0'
		]);
		// operation
		$table->addColumn('operation', Types::INTEGER, [
			'notnull' => true,
			'default' => 0
		]);
		// timestamp
		$table->addColumn('stamp', Types::FLOAT, [
			'notnull' => true,
			'default' => 0
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'davc_chronicle_index_1'); // by user id
		$table->addIndex(['sid'], 'davc_chronicle_index_2'); // by service id
		$table->addIndex(['cid'], 'davc_chronicle_index_3'); // by collection id

		return $schema;
	}

}
