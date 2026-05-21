<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Contacts\Hybrid;

use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\ContactStore;
use OCA\DAVC\Store\Local\ServicesStore;

class Provider implements IAddressBookProvider {
	protected array $_CollectionCache = [];

	public function __construct(
		private readonly ServicesStore $servicesStore,
		private readonly ContactStore $localStore,
		private readonly RemoteFactory $remoteFactory,
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
		$storeFilter = $this->localStore->collectionListFilter();
		$storeFilter->condition('uid', $this->extractUserId($principalUri));
		// retrieve collection(s)
		$collections = $this->localStore->collectionList($storeFilter);
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
		$storeFilter = $this->localStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in store
		$collections = $this->localStore->collectionList($storeFilter);
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
		$storeFilter = $this->localStore->collectionListFilter();
		$storeFilter->condition('uid', $userId);
		$storeFilter->condition('uuid', $calendarUri);
		// check if collection exists in store
		$collections = $this->localStore->collectionList($storeFilter);
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
		return new ContactCollection($this->servicesStore, $this->remoteFactory, $this->localStore, $entity);
	}

}
