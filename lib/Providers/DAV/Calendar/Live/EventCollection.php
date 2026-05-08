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

use DateTimeInterface;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Plugin;
use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Service\Local\LocalEventsService;
use OCA\JMAPC\Service\Remote\RemoteEventsService;
use Sabre\CalDAV\ICalendar;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\IMultiGet;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Sync\ISyncCollection;
use Sabre\VObject\Component\VCalendar;

class EventCollection extends ExternalCalendar implements ICalendar, IProperties, IMultiGet, ISyncCollection {

	private const DAV_USER_PREFIX = 'principals/users/';
	private string $_userId;
	private RemoteEventsService $_store;
	private ?LocalEventsService $_localEventService = null;
	private EventCollectionObject $_collection;
	protected array $_entitiesCache = [];

	/**
	 * Collection constructor.
	 */
	public function __construct(string $user, RemoteEventsService $store, EventCollectionObject $data) {
		parent::__construct(Application::APP_ID, $data->Id);
		$this->_userId = $user;
		$this->_store = $store;
		$this->_collection = $data;
	}

	/**
	 * collection principal owner
	 *
	 * @return string|null
	 */
	public function getOwner(): ?string {
		return self::DAV_USER_PREFIX . $this->_userId;
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
		return $this->_collection->Id;
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
		return $this->_collection->Signature;
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
		$delta = $this->_store->entityDelta($this->_collection->Id, $token);
		// convert results
		$changes['added'] = array_column($delta['additions'], 'id');
		$changes['modified'] = array_column($delta['modifications'], 'id');
		$changes['deleted'] = array_column($delta['deletions'], 'id');
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
			'{DAV:}displayname' => $this->_collection->Label,
			'{http://apple.com/ns/ical/}calendar-color' => $this->_collection->Color,
			'{http://owncloud.org/ns}calendar-enabled' => (string)$this->_collection->Visibility,
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
			$deposit = false;
			// evaluate if name was changed
			if (isset($mutations['{DAV:}displayname'])) {
				$this->_collection->Label = (string)$mutations['{DAV:}displayname'];
				$propPatch->setResultCode('{DAV:}displayname', 200);
				$deposit = true;
			}
			// evaluate if color was changed
			if (isset($mutations['{http://apple.com/ns/ical/}calendar-color'])) {
				$this->_collection->Color = (string)$mutations['{http://apple.com/ns/ical/}calendar-color'];
				$propPatch->setResultCode('{http://apple.com/ns/ical/}calendar-color', 200);
				$deposit = true;
			}
			// evaluate if visibility was changed
			if (isset($mutations['{http://owncloud.org/ns}calendar-enabled'])) {
				$this->_collection->Visibility = (bool)$mutations['{http://owncloud.org/ns}calendar-enabled'];
				$propPatch->setResultCode('{http://owncloud.org/ns}calendar-enabled', 200);
				$deposit = true;
			}
			// update collection
			if ($deposit === true) {
				$this->_store->collectionModify($this->_collection->Id, $this->_collection);
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
		$this->_store->collectionDelete($this->_collection->Id);
	}

	/**
	 * find entities in this collection
	 *
	 * @return array<int,string>
	 */
	public function calendarQuery(array $filters): array {
		// define defaults
		$storeFilter = $this->_store->entityListFilter();
		$storeSort = null;
		$storeRange = null;
		// translate other filters
		if (is_array($filters) && is_array($filters['comp-filters'])) {
			foreach ($filters['comp-filters'] as $filter) {
				if (is_array($filter['time-range']) && isset($filter['time-range']['start']) && isset($filter['time-range']['end'])) {
					if ($filter['time-range']['start'] instanceof DateTimeInterface && $filter['time-range']['end'] instanceof DateTimeInterface) {
						//$storeRange = new RangeDate($filter['time-range']['start'], $filter['time-range']['end']);
						$storeFilter->condition('after', $filter['time-range']['start']);
						$storeFilter->condition('before', $filter['time-range']['end']);
					}
				}
			}
		}
		// retrieve entries
		$entries = $this->_store->entityList(
			$this->_collection->Id,
			null,
			$storeRange,
			$storeFilter,
			$storeSort,
			null
		);
		// list entries
		$list = [];
		foreach ($entries['list'] as $entry) {
			$this->_entitiesCache[$entry->ID] = $entry;
			$list[] = $entry->ID;
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
		// retrieve entries
		$entries = $this->_store->entityList(
			$this->_collection->Id,
			null,
			null,
			null,
			null,
			null
		);
		// list entries
		$list = [];
		foreach ($entries['list'] as $entry) {
			$this->_entitiesCache[$entry->ID] = $entry;
			$list[] = new EventEntity($this, $entry);
		}
		// return list
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
		return $this->getChild($id) !== false;
	}

	/**
	 * retrieve specific entities in this collection
	 *
	 * @param array<int,string> $ids
	 *
	 * @return array<int,EventEntity>
	 */
	public function getMultipleChildren(array $ids): array {
		// remove extension
		$ids = array_map(
			fn($id) => str_replace('.ics', '', $id),
			$ids
		);
		// check if all entities are cached
		if (count($ids) === count(array_intersect_key($this->_entitiesCache, array_flip($ids)))) {
			// retrieve and cache entities
			foreach ($this->_store->entityFetchMultiple($ids) as $entity) {
				$this->_entitiesCache[$entity->ID] = $entity;
			}
		}
		return array_map(
			fn($id) => new EventEntity($this, $this->_entitiesCache[$id]),
			$ids
		);
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
		// check if entity is cached	
		if (isset($this->_entitiesCache[$id])) {
			$entity = $this->_entitiesCache[$id];
		} else {
			// retrieve entity from data store
			$entity = $this->_store->entityFetch($id);
			// cache retrieved entity
			if ($entity !== null) {
				$this->_entitiesCache[$id] = $entity;
			}
		}
		// evaluate if entity was found
		return $entity !== null ? new EventEntity($this, $entity) : false;
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
		// remove extension
		$id = str_replace('.ics', '', $id);
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vObject = \Sabre\VObject\Reader::read($data);
		// normalize properties
		$this->normalizeProperties($vObject);
		// convert to event object
		$to = $this->toEventObject($vObject);
		// deposit the entity in the data store
		$to = $this->_store->entityCreate($this->_collection->Id, $to);
		// return state
		return $to->Signature;
	}

	/**
	 * modify a entity in this collection
	 *
	 * @param string $id existing entity id
	 * @param string $data modified entity contents
	 *
	 * @return string entity signature
	 */
	public function modifyFile(string $id, string $data): string {
		// read the data
		$vObject = \Sabre\VObject\Reader::read($data);
		// normalize properties
		$this->normalizeProperties($vObject);
		// convert to event object
		$to = $this->toEventObject($vObject);
		// deposit the entity in the data store
		$to = $this->_store->entityModify($this->_collection->Id, $id, $to);
		// return state
		return $to->Signature;
	}

	/**
	 * delete a entity in this collection
	 *
	 * @param string $id existing entity id
	 *
	 * @return void
	 */
	public function deleteFile(string $id): void {
		$this->_store->entityDelete($this->_collection->Id, $id);
	}

	/**
	 * convert a event object to a string
	 * 
	 * @since 1.0.0
	 */
	public function fromEventObject(EventObject $so): string {
		if ($this->_localEventService === null) {
			$this->_localEventService = new LocalEventsService();
		}
		$do = $this->_localEventService->fromEventObject($so);
		return $do->serialize();
	}

	/**
	 * convert a VCalendar object or string to a event object
	 * 
	 * @since 1.0.0
	 */
	public function toEventObject(VCalendar|string $so): EventObject {
		if ($this->_localEventService === null) {
			$this->_localEventService = new LocalEventsService();
		}
		if (is_string($so)) {
			$so = \Sabre\VObject\Reader::read($so);
		}
		$do = $this->_localEventService->toEventObject($so);
		return $do;
	}

	/**
	 * normalizes properties of a VCalendar object
	 * 
	 * @since 1.0.0
	 */
	protected function normalizeProperties(VCalendar $vObject): void {
		foreach ($vObject->getComponents() as $component) {
			if ($component->name === 'VTIMEZONE') {
				continue;
			}
			// normalize ORGANIZER
			if ($component->ORGANIZER !== null && $component->ORGANIZER->{'X-ID'}?->getValue() === null) {
				$component->ORGANIZER->add('X-ID', uniqid());
			}
			// normalize ATTENDEE(s)
			if (isset($component->ATTENDEE)) {
				foreach ($component->ATTENDEE as $entry) {
					if (empty($entry->parameters()['X-ID']?->getValue())) {
						$entry->add('X-ID', uniqid());
					}
					$entry->setValue(mb_strtolower($entry->getValue()));
				}
			}
			// normalize LOCATION(s)
			if (isset($component->LOCATION)) {
				foreach ($component->LOCATION as $entry) {
					if (empty($entry->parameters()['X-ID']?->getValue())) {
						$entry->add('X-ID', uniqid());
					}
				}
			}
			// normalize VALARM(s)
			if (isset($component->VALARM)) {
				foreach ($component->VALARM as $entry) {
					if ($entry->{'X-ID'} === null) {
						$entry->add('X-ID', uniqid());
					} elseif (empty($entry->{'X-ID'}?->getValue())) {
						$entry->{'X-ID'}->setValue(uniqid());
					}
				}
			}
			// normalize ATTACH(s)
			if (isset($component->ATTACH)) {
				foreach ($component->ATTACH as $entry) {
					if (empty($entry->parameters()['X-ID']?->getValue())) {
						$entry->add('X-ID', uniqid());
					}
				}
			}
		}
	}

}
