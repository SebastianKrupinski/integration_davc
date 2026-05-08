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

namespace OCA\JMAPC\Store\Local;

use OCP\IDBConnection;

class ServicesTemplateStore {

	protected IDBConnection $_Store;
	protected string $_EntityTable = 'jmapc_service_templates';

	public function __construct(IDBConnection $store) {
		$this->_Store = $store;
	}

	/**
	 * retrieve service templates
	 *
	 * @since Release 1.0.0
	 */
	public function fetchById(string $id): array {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$rs = $cmd->executeQuery()->fetchAll();
		$cmd->executeQuery()->closeCursor();
		// return result or null
		if (is_array($rs) && count($rs) > 0) {
			return $rs;
		} else {
			return [];
		}
	}

	/**
	 * retrieve service templates for specific domain from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $domain	configured service domain
	 *
	 * @return array
	 */
	public function fetchByDomain(string $domain): array {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('domain', $cmd->createNamedParameter($domain)));
		// execute command
		$rs = $cmd->executeQuery()->fetchAll();
		$cmd->executeQuery()->closeCursor();
		// return result or null
		if (is_array($rs) && count($rs) > 0) {
			return $rs;
		} else {
			return [];
		}
	}

	/**
	 * create service templates for a specific domain in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id		configured service template ID
	 * @param string $domain	configured service domain
	 * @param array $data	service template data
	 *
	 * @return bool
	 */
	public function create(string $id, string $domain, array $data): bool {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_EntityTable)
			->values([
				'id' => $cmd->createNamedParameter($id),
				'domain' => $cmd->createNamedParameter($domain),
				'connection' => $cmd->createNamedParameter(json_encode($data)),
			]);
		// execute command
		try {
			return $cmd->executeStatement() > 0;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * modify service templates for a specific domain in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id		configured service template ID
	 * @param string $domain	configured service domain
	 * @param array $data	service template data
	 *
	 * @return bool
	 */
	public function modify(string $id, string $domain, array $data): bool {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_EntityTable)
			->set('domain', $cmd->createNamedParameter($domain))
			->set('connection', $cmd->createNamedParameter(json_encode($data)))
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		try {
			return $cmd->executeStatement() > 0;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * delete service template for a specific ID from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id    configured service template ID
	 *
	 * @return bool
	 */
	public function delete(string $id): bool {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		try {
			return $cmd->executeStatement() > 0;
		} catch (\Exception $e) {
			return false;
		}
	}
	
}
