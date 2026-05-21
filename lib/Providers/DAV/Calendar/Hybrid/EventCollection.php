<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Calendar\Hybrid;

use DateTimeInterface;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Models\Calendars\Entity;
use OCA\DAVC\Service\Remote\RemoteEventsService;
use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Store\Common\Filters\FilterComparisonOperator;
use OCA\DAVC\Store\Common\Range\RangeDate;
use OCA\DAVC\Store\Local\CollectionEntity as CollectionEntityData;
use OCA\DAVC\Store\Local\EventEntity as EventEntityData;
use OCA\DAVC\Store\Local\EventStore;
use OCA\DAVC\Store\Local\ServicesStore;
use Sabre\CalDAV\ICalendar;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\IMultiGet;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Sync\ISyncCollection;
use Sabre\VObject\Reader;

class EventCollection extends ExternalCalendar implements ICalendar, IProperties, IMultiGet, ISyncCollection {
	
	private const DAV_USER_PREFIX = 'principals/users/';
	private RemoteEventsService|null $remoteService = null;

	public function __construct(
		private readonly ServicesStore $servicesStore,
		private readonly RemoteFactory $remoteFactory,
		private readonly EventStore $localStore,
		private readonly CollectionEntityData $collection
	) {
		parent::__construct(Application::APP_ID, $collection->getUuid());
	}

	/** 
	 * lazy load remote service
	 */
	protected function remoteService(): RemoteEventsService {

		if ($this->remoteService !== null) {
			return $this->remoteService;
		}

		$service = $this->servicesStore->fetch($this->collection->getSid());
		if ($service === null) {
			throw new \Exception('Service not found');
		}
		
		$this->remoteService = $this->remoteFactory->eventsService($this->remoteFactory->freshClient($service));

		return $this->remoteService;
	}

	/**
	 * collection principal owner
	 *
	 * @return string|null
	 */
	public function getOwner(): ?string {
		return self::DAV_USER_PREFIX . $this->collection->getUid();
	}

	/**
	 * collection principal group
	 *
	 * @return string|null
	 */
	public function getGroup(): ?string {
		return null;
	}

	/**
	 * collection id
	 */
	/*
	public function getName(): string {
		return $this->_collection->getUuid();
	}
	*/

	/**
	 * collection id
	 *
	 * @param string $id
	 */
	/*
	public function setName($id): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');
	}
	*/

	/**
	 * collection permissions
	 *
	 * @return array
	 */
	public function getACL(): array {
		return [
			[
				'privilege' => '{DAV:}all',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
		];
	}

	/**
	 * collection permissions
	 *
	 * @return void
	 */
	public function setACL(array $acl): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * supported permissions
	 *
	 * @return array|null
	 */
	public function getSupportedPrivilegeSet(): ?array {
		return null;
	}

	/**
	 * collection modification timestamp
	 *
	 * @return int|null
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * collection mutation signature
	 *
	 * @return string|null
	 */
	public function getSyncToken(): ?string {
		return $this->localStore->chronicleApex($this->collection->getId(), true);
	}

	/**
	 * collection delta
	 *
	 * @param string $token
	 * @param int $level
	 * @param int $limit
	 *
	 * @return array|null
	 */
	public function getChanges($token, $level, $limit = null): array {
		// retrieve delta
		$delta = $this->localStore->chronicleReminisce($this->collection->getId(), (string)$token, $limit);
		// convert results
		$changes['added'] = array_column($delta['additions'], 'uuid');
		$changes['modified'] = array_column($delta['modifications'], 'uuid');
		$changes['deleted'] = array_column($delta['deletions'], 'uuid');
		$changes['syncToken'] = $delta['stamp'];
		return $changes;
	}

	/**
	 * determines if this collection is shared
	 *
	 * @return bool
	 */
	public function isShared(): bool {
		return false;
	}

	/**
	 * retrieves properties for this collection
	 *
	 * @param array $properties requested properties
	 *
	 * @return array
	 */
	public function getProperties($properties): array {
		// return collection properties
		return [
			'{DAV:}displayname' => $this->collection->getLabel(),
			'{http://apple.com/ns/ical/}calendar-color' => $this->collection->getColor(),
			'{http://owncloud.org/ns}calendar-enabled' => (string)$this->collection->getVisible(),
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
		];
	}

	/**
	 * modifies properties of this collection
	 *
	 * @param PropPatch $data
	 *
	 * @return void
	 */
	public function propPatch(PropPatch $propPatch): void {
		// retrieve mutations
		$mutations = $propPatch->getMutations();
		// evaluate if any mutations apply
		if (count($mutations) > 0) {
			// evaluate if name was changed
			if (isset($mutations['{DAV:}displayname'])) {
				$this->collection->setLabel($mutations['{DAV:}displayname']);
				$propPatch->setResultCode('{DAV:}displayname', 200);
			}
			// evaluate if color was changed
			if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
				$this->collection->setColor($mutations['{http://apple.com/ns/ical/}calendar-color']);
				$propPatch->setResultCode('{http://apple.com/ns/ical/}calendar-color', 200);
			}
			// evaluate if enabled was changed
			if (isset($mutations['{http://owncloud.org/ns}calendar-enabled'])) {
				$this->collection->setVisible((int)$mutations['{http://owncloud.org/ns}calendar-enabled']);
				$propPatch->setResultCode('{http://owncloud.org/ns}calendar-enabled', 200);
			}
			// update collection
			if (count($this->collection->getUpdatedFields()) > 0) {
				$this->localStore->collectionModify($this->collection);
			}
		}
	}

	/**
	 * creates sub collection
	 *
	 * @param string $name
	 */
	/*
	public function createDirectory($name): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');
	}
	*/

	/**
	 * Deletes this collection and all entities
	 *
	 * @return void
	 */
	public function delete(): void {
		// delete local entities
		$this->localStore->entityDeleteByCollection($this->collection->getId());
		// delete local collection
		$this->localStore->collectionDelete($this->collection);
	}

	/**
	 * find entities in this collection
	 *
	 * @return array<int,string>
	 */
	public function calendarQuery(array $filters): array {
		// define defaults
		$storeFilter = $this->localStore->entityListFilter();
		$storeSort = null;
		$storeRange = null;
		// define default filter
		$storeFilter->condition('cid', $this->collection->getId());
		// translate other filters
		if (is_array($filters) && is_array($filters['comp-filters'])) {
			foreach ($filters['comp-filters'] as $filter) {
				if (is_array($filter['time-range']) && isset($filter['time-range']['start']) && isset($filter['time-range']['end'])) {
					if ($filter['time-range']['start'] instanceof DateTimeInterface && $filter['time-range']['end'] instanceof DateTimeInterface) {
						$storeRange = new RangeDate($filter['time-range']['start'], $filter['time-range']['end']);
					}
				}
			}
		}
		// retrieve entries
		$entries = $this->localStore->entityList($storeFilter, $storeSort, $storeRange, ['uuid']);
		// list entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = $entry->getUuid();
		}
		// return list
		return $list;
	}

	/**
	 * list all entities in this collection
	 *
	 * @return array<int,EventEntity>
	 */
	public function getChildren(): array {
		// construct collection filter
		$storeFilter = $this->localStore->entityListFilter();
		$storeFilter->condition('cid', $this->collection->getId());
		// retrieve entries
		$entries = $this->localStore->entityList($storeFilter);
		// transform entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new EventEntity($this, $entry);
		}
		return $list;
	}

	/**
	 * determine if a specific entity exists in this collection
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	public function childExists($id): bool {
		// remove extension
		$id = str_replace('.ics', '', $id);
		// confirm object exists
		return (bool)$this->localStore->entityConfirmByUUID($this->collection->getId(), $id);
	}

	/**
	 * retrieve specific entities in this collection
	 *
	 * @param array<int,string> $ids
	 *
	 * @return array<int,EventEntity>
	 */
	public function getMultipleChildren(array $ids): array {
		// construct filter
		$filter = $this->localStore->entityListFilter();
		$filter->condition('cid', $this->collection->getId());
		$filter->condition('uuid', $ids, FilterComparisonOperator::IN);
		// retrieve object properties
		$entities = $this->localStore->entityList($filter);
		// construct place holder
		$list = [];
		// convert entities
		foreach ($entities as $entry) {
			$list[] = new EventEntity($this, $entry);
		}
		return $list;
	}

	/**
	 * retrieve a specific entity in this collection
	 *
	 * @param string $id existing entity id
	 *
	 * @return EventEntity|false
	 */
	public function getChild($id): EventEntity|false {
		// remove extension
		$id = str_replace('.ics', '', $id);
		// construct filter
		$filter = $this->localStore->entityListFilter();
		$filter->condition('cid', $this->collection->getId());
		$filter->condition('uuid', $id);
		// retrieve object properties
		$entities = $this->localStore->entityList($filter);
		// evaluate if object properties where retrieved
		if (count($entities) > 0) {
			return new EventEntity($this, $entities[0]);
		} else {
			throw new \Sabre\DAV\Exception\NotFound('Entity not found');
		}
	}

	/**
	 * create a entity in this collection
	 *
	 * @param string $id fresh entity id
	 * @param string $data fresh entity contents
	 *
	 * @return string entity signature
	 */
	public function createFile($id, $data = null): string {

		$vo = Reader::read($data);
	
		$eo = new Entity();
		$eo->CCID = $this->collection->getCcid();
		$eo->CEID = $id;
		$eo->data = $vo;

		$service = $this->remoteService();

		$entity = $service->entityCreate($eo);
		
		// return state
		return $entity->Signature ?? '';
	}

	/**
	 * modify a entity in this collection
	 *
	 * @param EventEntityData $entity existing entity object
	 * @param string $data modified entity contents
	 *
	 * @return string entity signature
	 */
	public function modifyFile(EventEntityData $entity, string $data): string {

		$vo = Reader::read($data);
	
		$eo = new Entity();
		$eo->CCID = $entity->getCcid();
		$eo->CEID = $entity->getCeid();
		$eo->data = $vo;

		$service = $this->remoteService();

		$entity = $service->entityModify($eo);

		// return state
		return $entity->Signature ?? '';

	}

	/**
	 * delete a entity in this collection
	 *
	 * @param EventEntityData $entity existing entity object
	 *
	 * @return void
	 */
	public function deleteFile(EventEntityData $entity): void {
		$service = $this->remoteService();
		$service->entityDelete($this->collection->getCcid(), $entity->getCeid());
	}

}
