<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Local;

use OCA\DAVC\Store\Local\ContactStore;
use OCA\DAVC\Store\Local\EventStore;
use OCA\DAVC\Store\Local\TaskStore;
use OCP\Server;

class LocalService {
	/**
	 * instance of the local contact service
	 *
	 * @since Release 1.0.0
	 */
	public static function contactsService(string $userId): LocalContactsService {
		$service = new LocalContactsService();
		$service->initialize(self::contactsStore());
		return $service;
	}

	/**
	 * instance of the local event service
	 *
	 * @since Release 1.0.0
	 */
	public static function eventsService(string $userId): LocalEventsService {
		$service = new LocalEventsService();
		$service->initialize(self::eventsStore());
		return $service;
	}

	/**
	 * instance of the local task service
	 *
	 * @since Release 1.0.0
	 */
	public static function tasksService(string $userId): LocalTasksService {
		$service = new LocalTasksService();
		$service->initialize(self::tasksStore());
		return $service;
	}

	/**
	 * instance of the local contact store
	 *
	 * @since Release 1.0.0
	 *
	 * @return ContactStore
	 */
	public static function contactsStore(): ContactStore {
		return Server::get(ContactStore::class);
	}

	/**
	 * instance of the local event store
	 *
	 * @since Release 1.0.0
	 *
	 * @return EventStore
	 */
	public static function eventsStore(): EventStore {
		return Server::get(EventStore::class);
	}

	/**
	 * instance of the local task store
	 *
	 * @since Release 1.0.0
	 *
	 * @return TaskStore
	 */
	public static function tasksStore(): TaskStore {
		return Server::get(TaskStore::class);
	}

}
