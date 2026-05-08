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

namespace OCA\JMAPC\Providers\DAV\Calendar\Live;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider as ICalendarProvider2;
use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Service\ServicesService;
use OCA\JMAPC\Store\Local\ServiceEntity;
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
