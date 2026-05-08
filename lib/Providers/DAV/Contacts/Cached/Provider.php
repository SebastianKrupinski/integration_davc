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

namespace OCA\JMAPC\Providers\DAV\Contacts\Cached;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Store\Local\CollectionEntity;
use OCA\JMAPC\Store\Local\ContactStore;

class Provider implements IAddressBookProvider {
	protected array $_CollectionCache = [];

	public function __construct(
		private ContactStore $_ContactStore,
	) {
	}

	protected function extractUserId(string $principalUri): string {
		return substr($principalUri, 17);
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
	public function fetchAllForAddressBookHome(string $principalUri): array {
		$userId = $this->extractUserId($principalUri);
		// construct filter
		$storeFilter = $this->_ContactStore->collectionListFilter();
		$storeFilter->condition('uid', $this->extractUserId($principalUri));
		// retrieve collection(s)
		$collections = $this->_ContactStore->collectionList($storeFilter);
		// construct collection objects list
		$list = [];
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
	public function hasAddressBookInAddressBookHome(string $principalUri, string $calendarUri): bool {
		$userId = $this->extractUserId($principalUri);
		// check if collection is already cached
		$collection = $this->cacheRetrieveCollection($userId, $calendarUri);
		if ($collection) {
			return true;
		}
		// construct filter
		$storeFilter = $this->_ContactStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in store
		$collections = $this->_ContactStore->collectionList($storeFilter);
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
	public function getAddressBookInAddressBookHome(string $principalUri, string $calendarUri): ?ExternalAddressBook {
		$userId = $this->extractUserId($principalUri);
		// check if collection is already cached
		$collection = $this->cacheRetrieveCollection($userId, $calendarUri);
		if ($collection) {
			return $collection;
		}
		// construct filter
		$storeFilter = $this->_ContactStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in store
		$collections = $this->_ContactStore->collectionList($storeFilter);
		if (count($collections) > 0) {
			$collection = $this->collectionFromDataEntity($collections[0]);
			$this->cacheStoreCollection($userId, $calendarUri, $collection);
			return $collection;
		}
		// collection not found
		return null;
	}

	protected function cacheRetrieveCollection(string $uid, string $cid): ?ContactCollection {
		if (isset($this->_CollectionCache[$uid][$cid])) {
			return $this->_CollectionCache[$uid][$cid];
		}
		return null;
	}

	protected function cacheStoreCollection(string $uid, string $cid, ContactCollection $collection): void {
		if (!isset($this->_CollectionCache[$uid])) {
			$this->_CollectionCache[$uid] = [];
		}
		$this->_CollectionCache[$uid][$cid] = $collection;
	}

	protected function collectionFromDataEntity(CollectionEntity $entity): ContactCollection {
		return new ContactCollection($this->_ContactStore, $entity);
	}

}
