<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use Exception;
use RuntimeException;

use OCA\DAVC\Models\Calendars\Collection;
use OCA\DAVC\Models\Calendars\Entity;
use OCA\DAVC\Models\DeltaObject;
use OCA\DAVC\Models\OriginTypes;
use OCA\DAVC\Store\Common\Filters\IFilter;
use OCA\DAVC\Store\Common\Sort\ISort;
use OCA\DAVC\Store\Remote\Filters\EventFilter;
use OCA\DAVC\Store\Remote\Sort\EventSort;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class RemoteEventsService {

	protected RemoteClient $dataStore;

	protected array $collectionPropertiesDefault = [
		RemoteClient::DAV_RESOURCE_TYPE,
		RemoteClient::DAV_DISPLAYNAME,
		RemoteClient::CALDAV_CALENDAR_DESCRIPTION,
		RemoteClient::CALDAV_SUPPORTED_CALENDAR_COMPONENT_SET,
		RemoteClient::APPLE_ICAL_CALENDAR_COLOR,
		RemoteClient::APPLE_ICAL_CALENDAR_ORDER,
		RemoteClient::DAV_OWNER,
		RemoteClient::DAV_ACL,
		RemoteClient::DAV_SYNC_TOKEN,
		RemoteClient::CALENDARSERVER_GETCTAG,
		RemoteClient::SABREDAV_SYNC_TOKEN,
	];
	protected array $collectionPropertiesBasic = [
		RemoteClient::DAV_RESOURCE_TYPE,
		RemoteClient::DAV_DISPLAYNAME,
		RemoteClient::DAV_SYNC_TOKEN,
		RemoteClient::CALENDARSERVER_GETCTAG,
		RemoteClient::SABREDAV_SYNC_TOKEN,
	];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		RemoteClient::DAV_RESOURCE_TYPE,
		RemoteClient::DAV_ETAG,
	];

	public function __construct() {
	}

	public function initialize(RemoteClient $dataStore) {
		if ($dataStore->getCalendarHome() === null) {
			throw new RuntimeException('Remote calendar home set is not configured.');
		}

		$this->dataStore = $dataStore;

		// authenticate calendar home
		$this->dataStore->options($this->dataStore->getCalendarHome());
	}

	/**
	 * list of collections in remote storage
	 *
	 * @return array<string,Collection>
	 */
	public function collectionList(string $granularity = 'basic'): array {
		// transceive
		$data = $this->dataStore->propFind(
			$this->dataStore->getCalendarHome(),
			1,
			$granularity === 'basic' ? $this->collectionPropertiesBasic : $this->collectionPropertiesDefault
		);		

		// convert dav properties to collection objects
		$list = [];
		foreach ($data as $id => $so) {
			// extract only successful properties
			$properties = $so[200] ?? [];
			// validate calendar collection
			if (!isset($properties[RemoteClient::DAV_RESOURCE_TYPE])) {
				continue;
			}
			if (!in_array(RemoteClient::CALDAV_CALENDAR_TYPE, $properties[RemoteClient::DAV_RESOURCE_TYPE]->getValue(), true)) {
				continue;
			}

			$list[] = $this->toCollection($id, $properties);
			
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFetch(string $identifier): ?Collection {
		$data = $this->dataStore->propFind($identifier, 0, $this->collectionPropertiesDefault);

		foreach ($data as $id => $so) {
			$properties = $so[200] ?? [];
			if (!isset($properties[RemoteClient::DAV_RESOURCE_TYPE])) {
				continue;
			}
			if (!in_array(RemoteClient::CALDAV_CALENDAR_TYPE, $properties[RemoteClient::DAV_RESOURCE_TYPE]->getValue(), true)) {
				continue;
			}

			return $this->toCollection($id, $properties);
		}

		return null;
	}

	/**
	 * retrieve entities from remote storage
	 *
	 * @param string|null $location Id of parent collection
	 * @param string|null $granularity Amount of detail to return
	 * @param IFilter|null $filter Properties to filter by
	 * @param ISort|null $sort Properties to sort by
	 */
	public function entityList(?string $location = null, ?string $granularity = null, ?IFilter $filter = null, ?ISort $sort = null): array {

		$properties = $granularity === 'basic' ? $this->entityPropertiesBasic : $this->entityPropertiesDefault;

		$resources = $this->dataStore->propFind($location, 1, $properties);

		$list = [];
		foreach ($resources as $identifier => $resource) {
			// Some server implementations include all collection members in the response of a sync-collection report on the collection itself
			if (isset($resource[200][RemoteClient::DAV_RESOURCE_TYPE]) && $resource[200][RemoteClient::DAV_RESOURCE_TYPE] !== null) {
				continue;
			}

			if (!isset($resource[200][RemoteClient::DAV_ETAG])) {
				continue;
			}

			$entity = $this->toCalendarEntity($resource[200], ['ID' => $identifier, 'CID' => $location]);
			$list[$identifier] = $entity;
		}
		return $list;
	}

	public function entityListFilter(): EventFilter {
		return new EventFilter();
	}

	public function entityListSort(): EventSort {
		return new EventSort();
	}

	/**
	 * delta for entities in remote storage
	 *
	 * @return DeltaObject
	 */
	public function entityDelta(string $location, string $state): DeltaObject {
		$davAbilities = $this->dataStore->capabilities('dav') ?? [];
		$davMethods = $this->dataStore->capabilities('allow') ?? [];
		
		if (in_array('sync-collection', $davAbilities, true) === false && in_array('REPORT', $davMethods, true) === false) {
			throw new RuntimeException('Remote server does not support DAV sync-collection reports.');
			
		}

		$delta = new DeltaObject();

		try {
			$responses = $this->dataStore->report($location, RemoteClient::DAV_SYNC_COLLECTION, 0, [
				[
					'name' => RemoteClient::DAV_SYNC_TOKEN,
					'value' => $state,
				],
				[
					'name' => RemoteClient::DAV_SYNC_LEVEL	,
					'value' => '1',
				],
				[
					'name' => RemoteClient::DAV_PROPERTY,
					'value' => [
						RemoteClient::DAV_RESOURCE_TYPE => null,
						RemoteClient::DAV_ETAG => null,
					],
				],
			]);

			if (isset($responses['token'])) {
				$delta->signature = $responses['token'];
				unset($responses['token']);
			}

			foreach ($responses as $href => $response) {
				// Some server implementations return no status for deleted items
				if (!isset($response[200]) && !isset($response[404])) {
					$delta->deletions->append((string)$href);
					continue;
				}
				// Some server implementations return 404 for deleted items
				if (isset($response[404])) {
					$delta->deletions->append((string)$href);
					continue;
				}

				// Some server implementations include all collection members in the response of a sync-collection report on the collection itself
				if (isset($response[200][RemoteClient::DAV_RESOURCE_TYPE]) && $response[200][RemoteClient::DAV_RESOURCE_TYPE] !== null) {
					continue;
				}

				if (!isset($response[200][RemoteClient::DAV_ETAG])) {
					continue;
				}

				// DAV sync reports do not distinguish created from modified items.
				$delta->modifications->append((string)$href);
			}

			return $delta;
		} catch (\Throwable) {
			throw new RuntimeException('Failed to retrieve delta from remote server.');
		}
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @param string $identifier Id of entity
	 *
	 * @return Entity|null
	 */
	public function entityFetch(string $location, string $identifier): ?Entity {
		$responses = $this->dataStore->multiGet(
			$location,
			[$identifier],
			RemoteClient::CALDAV_CALENDAR_MULTIGET,
			RemoteClient::CALDAV_CALENDAR_DATA,
		);

		if (isset($responses[$identifier])) {
			$response = $responses[$identifier];
			if ($response['status'] === 200) {
				return $this->toCalendarEntity($response);
			}
		}

		return null;
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @param array<string> $identifiers Id of entity
	 *
	 * @return array<string,Entity> list of entities indexed by id
	 */
	public function entityFetchMultiple(string $location, array $identifiers): array {
		return [];
	}

	/**
	 * create entity in remote storage
	 */
	public function entityCreate(Entity $so): ?Entity {

		if (str_starts_with($so->CEID, $so->CCID)) {
			$path = $so->CEID;
		} else {
			$path = $so->CCID . $so->CEID;
		}

		$data = $so->data ? $so->data->serialize() : '';
		$signature = $this->dataStore->create($path, $data, "application/vcalendar");

		$ro = clone $so;
		//$ro->Signature = $signature;

		return $ro;
	}

	/**
	 * update entity in remote storage
	 */
	public function entityModify(Entity $so): ?Entity {

		if (str_starts_with($so->CEID, $so->CCID)) {
			$path = $so->CEID;
		} else {
			$path = $so->CCID . $so->CEID;
		}

		$data = $so->data ? $so->data->serialize() : '';
		$signature = $this->dataStore->update($path, $data, "application/vcalendar");

		$ro = clone $so;
		//$ro->Signature = $signature;
		
		return $ro;
	}

	/**
	 * delete entity from remote storage
	 */
	public function entityDelete(string $location, string $identifier): ?string {
		$response = $this->dataStore->delete($identifier);
		return $identifier;
	}

	/**
	 * move entity in remote storage
	 */
	public function entityMove(string $sourceLocation, string $identifier, string $destinationLocation): string {
		return '';
	}

	/**
	 * convert dav collection to event collection
	 */
	private function toCollection(string $id, array $so): Collection {
		$to = new Collection();
		$to->Id = $id;
		$to->Signature = $so[RemoteClient::DAV_SYNC_TOKEN] ?? $so[RemoteClient::SABREDAV_SYNC_TOKEN] ?? $so[RemoteClient::CALENDARSERVER_GETCTAG] ?? null;
		$to->Label = $so[RemoteClient::DAV_DISPLAYNAME] ?? null;
		$to->Description = $so[RemoteClient::CALDAV_CALENDAR_DESCRIPTION] ?? null;
		$to->Priority = isset($so[RemoteClient::APPLE_ICAL_CALENDAR_ORDER]) ? (int)$so[RemoteClient::APPLE_ICAL_CALENDAR_ORDER] : null;
		$to->Color = $so[RemoteClient::APPLE_ICAL_CALENDAR_COLOR] ?? null;
		return $to;
	}

	/**
	 * convert remote calendar payload to entity
	 *
	 * @param array<string, mixed> $additional
	 */
	public function toCalendarEntity(array $data, array $additional = []): Entity {
		// convert payload to vobject
		if (isset($data['payload']) && is_string($data['payload'])) {
			/** @var VCalendar $vo */
			$vo = Reader::read($data['payload']);
		}
		// construct entity
		$to = new Entity();
		$to->Origin = OriginTypes::External;
		$to->ID = $data['href'] ?? null;
		$to->data = $vo ?? null;
		$to->Signature = $data['etag'] ?? $data[RemoteClient::DAV_ETAG] ?? null;

		foreach ($additional as $label => $value) {
			if (property_exists($to, $label)) {
				$to->$label = $value;
			}
		}

		return $to;
	}

	/**
	 * generate entity signature
	 *
	 * @param Entity $to - entity object
	 *
	 * @return string entity signature
	 */
	public function generateSignature(Entity $to): string {

		// clone self
		$o = clone $to;
		// remove non needed values
		unset(
			$o->Origin,
			$o->ID,
			$o->CID,
			$o->Signature,
			$o->CCID,
			$o->CEID,
			$o->CESN,
		);

		// generate signature
		return md5(json_encode($o, JSON_PARTIAL_OUTPUT_ON_ERROR));
	}

}
