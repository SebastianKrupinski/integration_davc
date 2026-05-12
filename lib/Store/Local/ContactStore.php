<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local;

use OCP\IDBConnection;

class ContactStore extends BaseStore {

	public function __construct(IDBConnection $store) {
		
		$this->_Store = $store;
		$this->_CollectionTable = 'davc_collections';
		$this->_CollectionIdentifier = 'CC';
		$this->_CollectionClass = CollectionEntity::class;
		$this->_EntityTable = 'davc_entities_contacts';
		$this->_EntityIdentifier = 'CE';
		$this->_EntityClass = ContactEntity::class;
		$this->_ChronicleTable = 'davc_chronicle';

	}

}
