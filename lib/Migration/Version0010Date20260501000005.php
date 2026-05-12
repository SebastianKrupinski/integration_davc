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

class Version0010Date20260501000002 extends SimpleMigrationStep {

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
		if ($schema->hasTable('davc_entities_calendar')) {
			return;
		}
		// create the table
		$table = $schema->createTable('davc_entities_calendar');
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
		// contact id
		$table->addColumn('cid', Types::INTEGER, [
			'unsigned' => true,
			'notnull' => true,
			'default' => 0
		]);
		// uuid
		$table->addColumn('uuid', Types::STRING, [
			'length' => 64,
			'notnull' => true
		]);
		// signature
		$table->addColumn('signature', Types::STRING, [
			'length' => 64,
			'notnull' => false
		]);
		// ccid
		$table->addColumn('ccid', Types::TEXT, [
			'notnull' => false
		]);
		// ceid
		$table->addColumn('ceid', Types::TEXT, [
			'notnull' => false
		]);
		// cesn
		$table->addColumn('cesn', Types::TEXT, [
			'notnull' => false
		]);
		// data
		$table->addColumn('data', Types::TEXT, [
			'notnull' => false
		]);
		// label
		$table->addColumn('label', Types::TEXT, [
			'notnull' => false
		]);
		// description
		$table->addColumn('description', Types::TEXT, [
			'notnull' => false
		]);
		// startson
		$table->addColumn('startson', Types::INTEGER, [
			'notnull' => false
		]);
		// endson
		$table->addColumn('endson', Types::INTEGER, [
			'notnull' => false
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'davc_entities_event_index_1'); // by user id
		$table->addIndex(['sid'], 'davc_entities_event_index_2'); // by service id
		$table->addIndex(['cid'], 'davc_entities_event_index_3'); // by collection id
		$table->addIndex(['cid', 'uuid'], 'davc_entities_event_index_3b'); // by collection id and entity uuid
		$table->addIndex(['cid', 'startson', 'endson'], 'davc_entities_event_index_3d'); // by collection id and time range

		return $schema;
	}

}
