<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20250101 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createServicesTable($output, $schema, $options);
		$this->createServiceTemplatesTable($output, $schema);
		$this->createCollectionsTable($output, $schema);
		$this->createEntitiesContactTable($output, $schema);
		$this->createEntitiesEventTable($output, $schema);
		$this->createEntitiesTaskTable($output, $schema);
		$this->createChronicleTable($output, $schema);

		return $schema;
	}

	private function createServicesTable(IOutput $output, ISchemaWrapper $schema, array $options) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_services')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_services');
		// id
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true
		]);
		// user identifier
		$table->addColumn('uid', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// universally unique identifier
		$table->addColumn('uuid', Types::STRING, [
			'length' => 36,
			'notnull' => true
		]);
		// service label
		$table->addColumn('label', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service location protocol
		$table->addColumn('location_protocol', Types::STRING, [
			'length' => 8,
			'notnull' => true
		]);
		// service location host
		$table->addColumn('location_host', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// service location port
		$table->addColumn('location_port', Types::INTEGER, [
			'notnull' => true
		]);
		// service location path
		$table->addColumn('location_path', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service location security
		$table->addColumn('location_security', Types::BOOLEAN, [
			'notnull' => true
		]);
		// service authentication
		$table->addColumn('auth', Types::STRING, [
			'length' => 8,
			'notnull' => true
		]);
		// service authentication basic id
		$table->addColumn('bauth_id', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service authentication basic secret
		$table->addColumn('bauth_secret', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service authentication basic location
		$table->addColumn('bauth_location', Types::TEXT, [
			'notnull' => false
		]);
		// service authentication bearer id
		$table->addColumn('oauth_id', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service authentication bearer access token
		$table->addColumn('oauth_access_token', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service authentication bearer access location
		$table->addColumn('oauth_access_location', Types::TEXT, [
			'notnull' => false
		]);
		// service authentication bearer access expiry
		$table->addColumn('oauth_access_expiry', Types::INTEGER, [
			'notnull' => false
		]);
		// service authentication bearer refresh token
		$table->addColumn('oauth_refresh_token', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// service authentication bearer refresh location
		$table->addColumn('oauth_refresh_location', Types::TEXT, [
			'notnull' => false
		]);
		// service authentication custom token
		$table->addColumn('cauth_token', Types::TEXT, [
			'notnull' => false
		]);
		// service authentication custom location
		$table->addColumn('cauth_location', Types::TEXT, [
			'notnull' => false
		]);
		// primary address
		$table->addColumn('address_primary', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// alternate address
		$table->addColumn('address_alternate', Types::TEXT, [
			'notnull' => false
		]);
		// service enabled
		$table->addColumn('enabled', Types::BOOLEAN, [
			'notnull' => false
		]);
		// service connected
		$table->addColumn('connected', Types::BOOLEAN, [
			'notnull' => false
		]);
		// debug mode
		$table->addColumn('debug', Types::BOOLEAN, [
			'notnull' => false
		]);
		// harmonization state
		$table->addColumn('harmonization_state', Types::INTEGER, [
			'notnull' => false
		]);
		// harmonization start
		$table->addColumn('harmonization_start', Types::INTEGER, [
			'notnull' => false
		]);
		// harmonization end
		$table->addColumn('harmonization_end', Types::INTEGER, [
			'notnull' => false
		]);
		// subscription code
		$table->addColumn('subscription_code', Types::STRING, [
			'length' => 64,
			'notnull' => false
		]);
		// mail mode
		$table->addColumn('mail_mode', Types::STRING, [
			'length' => 8,
			'notnull' => false
		]);
		// contacts mode
		$table->addColumn('contacts_mode', Types::STRING, [
			'length' => 8,
			'notnull' => false
		]);
		// calendars mode
		$table->addColumn('events_mode', Types::STRING, [
			'length' => 8,
			'notnull' => false
		]);
		// tasks mode
		$table->addColumn('tasks_mode', Types::STRING, [
			'length' => 8,
			'notnull' => false
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'jmapc_service_index_1'); // by user id
	}

	private function createServiceTemplatesTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_service_templates')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_service_templates');
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
		$table->addIndex(['domain'], 'jmapc_service_templates_index_1'); // by domain
	}

	private function createCollectionsTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_collections')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_collections');
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
		// type
		$table->addColumn('type', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// collection id
		$table->addColumn('ccid', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// uuid
		$table->addColumn('uuid', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// label
		$table->addColumn('label', Types::STRING, [
			'length' => 255,
			'notnull' => true
		]);
		// color
		$table->addColumn('color', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// visible
		$table->addColumn('visible', Types::INTEGER, [
			'notnull' => true
		]);
		// hisn
		$table->addColumn('hisn', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// hesn
		$table->addColumn('hesn', Types::STRING, [
			'length' => 255,
			'notnull' => false
		]);
		// hlock
		$table->addColumn('hlock', Types::INTEGER, [
			'notnull' => false
		]);
		// hlockhb
		$table->addColumn('hlockhb', Types::INTEGER, [
			'notnull' => false
		]);
		// hlockhd
		$table->addColumn('hlockhd', Types::INTEGER, [
			'notnull' => false
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'jmapc_collections_index_1'); // by user id
		$table->addIndex(['uid', 'type'], 'jmapc_collections_index_1b'); // by user id and type
		$table->addIndex(['sid'], 'jmapc_collections_index_2'); // by service id
		$table->addIndex(['sid', 'type'], 'jmapc_collections_index_2b'); // by service id and type

	}

	private function createEntitiesContactTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_entities_contact')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_entities_contact');
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
			'length' => 50,
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

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'jmapc_entities_contact_index_1'); // by user id
		$table->addIndex(['sid'], 'jmapc_entities_contact_index_2'); // by service id
		$table->addIndex(['cid'], 'jmapc_entities_contact_index_3'); // by collection id
		$table->addIndex(['cid', 'uuid'], 'jmapc_entities_contact_index_3b'); // by collection id and entity uuid
		
	}

	private function createEntitiesEventTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_entities_event')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_entities_event');
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
		$table->addIndex(['uid'], 'jmapc_entities_event_index_1'); // by user id
		$table->addIndex(['sid'], 'jmapc_entities_event_index_2'); // by service id
		$table->addIndex(['cid'], 'jmapc_entities_event_index_3'); // by collection id
		$table->addIndex(['cid', 'uuid'], 'jmapc_entities_event_index_3b'); // by collection id and entity uuid
		$table->addIndex(['cid', 'startson', 'endson'], 'jmapc_entities_event_index_3d'); // by collection id and time range

	}

	private function createEntitiesTaskTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_entities_task')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_entities_task');
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
		$table->addColumn('dueson', Types::INTEGER, [
			'notnull' => false
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['uid'], 'jmapc_entities_task_index_1'); // by user id
		$table->addIndex(['sid'], 'jmapc_entities_task_index_2'); // by service id
		$table->addIndex(['cid'], 'jmapc_entities_task_index_3'); // by collection id
		$table->addIndex(['cid', 'uuid'], 'jmapc_entities_task_index_3b'); // by collection id and entity uuid
	
	}

	private function createChronicleTable(IOutput $output, ISchemaWrapper $schema) {
		// check if the table already exists
		if ($schema->hasTable('jmapc_chronicle')) {
			return;
		}
		// create the table
		$table = $schema->createTable('jmapc_chronicle');
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
		$table->addIndex(['uid'], 'jmapc_chronicle_index_1'); // by user id
		$table->addIndex(['sid'], 'jmapc_chronicle_index_2'); // by service id
		$table->addIndex(['cid'], 'jmapc_chronicle_index_3'); // by collection id

	}

}
