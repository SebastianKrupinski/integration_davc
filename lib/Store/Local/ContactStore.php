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
		$this->_CollectionTable = 'jmapc_collections';
		$this->_CollectionIdentifier = 'CC';
		$this->_CollectionClass = 'OCA\DAVC\Store\Local\CollectionEntity';
		$this->_EntityTable = 'jmapc_entities_contact';
		$this->_EntityIdentifier = 'CE';
		$this->_EntityClass = 'OCA\DAVC\Store\Local\ContactEntity';
		$this->_ChronicleTable = 'jmapc_chronicle';

	}

}
