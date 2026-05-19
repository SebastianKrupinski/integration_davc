<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Local;

use OCA\DAVC\Store\Local\ContactStore;
use OCA\DAVC\Store\Local\EventStore;
use OCP\Server;

class LocalFactory {
	/**
	 * instance of the local contact service
	 *
	 * @since Release 1.0.0
	 */
	public function contactsService(string $userId): LocalContactsService {
		$service = new LocalContactsService();
		$service->initialize($this->contactsStore());
		return $service;
	}

	/**
	 * instance of the local event service
	 *
	 * @since Release 1.0.0
	 */
	public function eventsService(string $userId): LocalEventsService {
		$service = new LocalEventsService();
		$service->initialize($this->eventsStore());
		return $service;
	}

	/**
	 * instance of the local contact store
	 *
	 * @since Release 1.0.0
	 *
	 * @return ContactStore
	 */
	public function contactsStore(): ContactStore {
		return Server::get(ContactStore::class);
	}

	/**
	 * instance of the local event store
	 *
	 * @since Release 1.0.0
	 *
	 * @return EventStore
	 */
	public function eventsStore(): EventStore {
		return Server::get(EventStore::class);
	}

}
