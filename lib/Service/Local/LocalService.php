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

namespace OCA\JMAPC\Service\Local;

use OCA\JMAPC\Store\Local\ContactStore;
use OCA\JMAPC\Store\Local\EventStore;
use OCA\JMAPC\Store\Local\TaskStore;
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
