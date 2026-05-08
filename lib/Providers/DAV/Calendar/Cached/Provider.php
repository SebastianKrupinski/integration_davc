<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Sebastian Krupinski <krupinski01@gmail.com>
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

namespace OCA\JMAPC\Providers\DAV\Calendar\Cached;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider as ICalendarProvider2;
use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\Local\CollectionEntity;
use OCA\JMAPC\Store\Local\EventStore;
use OCA\JMAPC\Store\Local\TaskStore;
use OCP\Calendar\ICalendarProvider as ICalendarProvider1;

class Provider implements ICalendarProvider1, ICalendarProvider2 {
	protected array $_CollectionCache = [];

	public function __construct(
		private EventStore $_EventStore,
		private TaskStore $_TaskStore,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForCalendarHome(string $principalUri): array {
		$userId = $this->extractUserId($principalUri);
		// construct collection objects list
		$list = [];
		// construct filter
		$storeFilter = $this->_EventStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		// retrieve collection(s)
		$collections = $this->_EventStore->collectionList($storeFilter);
		// add collections to list
		foreach ($collections as $entry) {
			$collection = $this->collectionFromDataEntity($entry);
			$this->cacheStoreCollection($userId, $entry->getUuid(), $collection);
			$list[] = $collection;
		}
		// construct filter
		$storeFilter = $this->_TaskStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		// retrieve collection(s)
		$collections = $this->_TaskStore->collectionList($storeFilter);
		// add collections to list
		foreach ($collections as $entry) {
			$collection = $this->collectionFromDataEntity($entry);
			$this->cacheStoreCollection($userId, $entry->getUuid(), $collection);
			$list[] = $collection;
		}
		return $list;
	}

	/**
	 * @inheritDoc
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool {
		$userId = $this->extractUserId($principalUri);
		// check if collection is already cached
		$collection = $this->cacheRetrieveCollection($userId, $calendarUri);
		if ($collection) {
			return true;
		}
		// construct filter
		$storeFilter = $this->_EventStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in events store
		$collections = $this->_EventStore->collectionList($storeFilter);
		if (count($collections) > 0) {
			$collection = $this->collectionFromDataEntity($collections[0]);
			$this->cacheStoreCollection($userId, $calendarUri, $collection);
			return true;
		}
		// construct filter
		$storeFilter = $this->_TaskStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in tasks store
		$collections = $this->_TaskStore->collectionList($storeFilter);
		if (count($collections) > 0) {
			$collection = $this->collectionFromDataEntity($collections[0]);
			$this->cacheStoreCollection($userId, $calendarUri, $collection);
			return true;
		}
		// collection not found
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar {
		$userId = $this->extractUserId($principalUri);
		// check if collection is already cached
		$collection = $this->cacheRetrieveCollection($userId, $calendarUri);
		if ($collection) {
			return $collection;
		}
		// construct filter
		$storeFilter = $this->_EventStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in events store
		$collections = $this->_EventStore->collectionList($storeFilter);
		if (count($collections) > 0) {
			$collection = $this->collectionFromDataEntity($collections[0]);
			$this->cacheStoreCollection($userId, $calendarUri, $collection);
			return $collection;
		}
		// construct filter
		$storeFilter = $this->_TaskStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in tasks store
		$collections = $this->_TaskStore->collectionList($storeFilter);
		if (count($collections) > 0) {
			$collection = $this->collectionFromDataEntity($collections[0]);
			$this->cacheStoreCollection($userId, $calendarUri, $collection);
			return $collection;
		}
		// collection not found
		return null;
	}
	
	protected function extractUserId(string $principalUri): string {
		return substr($principalUri, 17);
	}

	protected function cacheRetrieveCollection(string $uid, string $cid): EventCollection|TaskCollection|null {
		if (isset($this->_CollectionCache[$uid][$cid])) {
			return $this->_CollectionCache[$uid][$cid];
		}
		return null;
	}

	protected function cacheStoreCollection(string $uid, string $cid, EventCollection|TaskCollection $collection): void {
		if (!isset($this->_CollectionCache[$uid])) {
			$this->_CollectionCache[$uid] = [];
		}
		$this->_CollectionCache[$uid][$cid] = $collection;
	}

	protected function collectionFromDataEntity(CollectionEntity $entity): EventCollection|TaskCollection|null {
		if ($entity->getType() == 'EC') {
			return new EventCollection($this->_EventStore, $entity);
		} elseif ($entity->getType() == 'TC') {
			return new TaskCollection($this->_TaskStore, $entity);
		}
		return null;
	}

}
