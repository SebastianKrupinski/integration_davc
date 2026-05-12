<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Calendar\Live;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider as ICalendarProvider2;
use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\Remote\RemoteService;
use OCA\DAVC\Service\ServicesService;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCP\Calendar\ICalendarProvider as ICalendarProvider1;

class Provider implements ICalendarProvider1, ICalendarProvider2 {

	protected array $_ServicesCache = [];
	protected array $_CollectionCache = [];

	public function __construct(
		private ServicesService $_ServicesService
	) {}

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
		$services = $this->listServices($userId);
		$list = [];
		foreach ($services as $service) {
			$list = array_merge($list, $this->listCollections($userId, $service));
		}
		return $list;
	}

	/**
	 * @inheritDoc
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $collectionUri): bool {
		$userId = $this->extractUserId($principalUri);
		$services = $this->listServices($userId);
		foreach ($services as $service) {
			$collections = $this->listCollections($userId, $service);
			if (isset($collections[$collectionUri])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $collectionUri): ?ExternalCalendar {
		$userId = $this->extractUserId($principalUri);
		$services = $this->listServices($userId);
		foreach ($services as $service) {
			$collections = $this->listCollections($userId, $service);
			if (isset($collections[$collectionUri])) {
				return $collections[$collectionUri];
			}
		}
		return null;
	}
	
	protected function extractUserId(string $principalUri): string {
		return substr($principalUri, 17);
	}

	protected function listServices(string $uid): array {
		// check if services are already cached
		if (isset($this->_ServicesCache[$uid])) {
			return $this->_ServicesCache[$uid];
		}
		// construct filter
		$filter = $this->_ServicesService->listFilter();
		$filter->condition('uid', $uid);
		$filter->condition('enabled', 1);
		$filter->condition('events_mode', 'live');
		// retrieve services from store
		$services = $this->_ServicesService->list($filter);
		// cache services
		$this->_ServicesCache[$uid] = $services;
		return $this->_ServicesCache[$uid];
	}

	protected function listCollections(string $uid, ServiceEntity $service): array {
		// check if collections are already cached
		if (isset($this->_CollectionCache[$uid]) && isset($this->_CollectionCache[$uid][$service->getId()])) {
			return $this->_CollectionCache[$uid][$service->getId()];
		}
		$client = RemoteService::freshClient($service);
		$remoteService = RemoteService::eventsService($client);
		// retrieve collections
		try {
			$collections = $remoteService->collectionList();
		} catch (\Exception $e) {
			return [];
		}
		// convert collections
		foreach ($collections as $collection) {
			if (!isset($this->_CollectionCache[$uid][$service->getId()][$collection->Id])) {
				$this->_CollectionCache[$uid][$service->getId()][$collection->Id] = new EventCollection($uid, $remoteService, $collection);
			}
		}
		
		return $this->_CollectionCache[$uid][$service->getId()];
	}

}
