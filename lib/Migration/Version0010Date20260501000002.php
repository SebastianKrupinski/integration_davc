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
		if ($schema->hasTable('davc_service_templates')) {
			return;
		}
		// create the table
		$table = $schema->createTable('davc_service_templates');
		// id
		$table->addColumn('id', Types::STRING, [
			'length' => 64,
			'notnull' => true
		]);
		// domain
		$table->addColumn('domain', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// connection
		$table->addColumn('connection', Types::BLOB, [
			'length' => 16777215, // 16MB
			'notnull' => true
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['domain'], 'davc_service_templates_index_1'); // by domain

		return $schema;
	}

}
