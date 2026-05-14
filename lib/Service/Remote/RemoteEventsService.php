<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use Datetime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use RuntimeException;

use JmapClient\Requests\Calendar\CalendarGet;
use JmapClient\Requests\Calendar\CalendarParameters as CalendarParametersRequest;
use JmapClient\Requests\Calendar\CalendarSet;
use JmapClient\Requests\Calendar\EventChanges;
use JmapClient\Requests\Calendar\EventGet;
use JmapClient\Requests\Calendar\EventMutationParameters as EventMutationParametersRequest;
use JmapClient\Requests\Calendar\EventParameters as EventParametersRequest;
use JmapClient\Requests\Calendar\EventQuery;
use JmapClient\Requests\Calendar\EventQueryChanges;
use JmapClient\Requests\Calendar\EventSet;
use JmapClient\Responses\Calendar\CalendarParameters as CalendarParametersResponse;
use JmapClient\Responses\Calendar\EventMutationParameters as EventMutationParametersResponse;
use JmapClient\Responses\Calendar\EventParameters as EventParametersResponse;
use JmapClient\Responses\ResponseException;
use OCA\DAVC\Exceptions\JmapUnknownMethod;
use OCA\DAVC\Models\Calendars\Collection;
use OCA\DAVC\Objects\BaseStringCollection;
use OCA\DAVC\Objects\DeltaObject;
use OCA\DAVC\Objects\Event\EventAvailabilityTypes;
use OCA\DAVC\Objects\Event\EventCollectionObject;
use OCA\DAVC\Objects\Event\EventLocationPhysicalObject;
use OCA\DAVC\Objects\Event\EventLocationVirtualObject;
use OCA\DAVC\Objects\Event\EventMutationObject;
use OCA\DAVC\Objects\Event\EventNotificationAnchorTypes;
use OCA\DAVC\Objects\Event\EventNotificationObject;
use OCA\DAVC\Objects\Event\EventNotificationPatterns;
use OCA\DAVC\Objects\Event\EventNotificationTypes;
use OCA\DAVC\Objects\Event\EventObject;
use OCA\DAVC\Objects\Event\EventOccurrenceObject;
use OCA\DAVC\Objects\Event\EventOccurrencePatternTypes;
use OCA\DAVC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\DAVC\Objects\Event\EventParticipantObject;
use OCA\DAVC\Objects\Event\EventParticipantRoleTypes;
use OCA\DAVC\Objects\Event\EventParticipantStatusTypes;
use OCA\DAVC\Objects\Event\EventParticipantTypes;
use OCA\DAVC\Objects\Event\EventSensitivityTypes;
use OCA\DAVC\Objects\OriginTypes;
use OCA\DAVC\Store\Common\Filters\IFilter;
use OCA\DAVC\Store\Common\Range\IRangeTally;
use OCA\DAVC\Store\Common\Range\RangeAnchorType;
use OCA\DAVC\Store\Common\Sort\ISort;
use OCA\DAVC\Store\Remote\Filters\EventFilter;
use OCA\DAVC\Store\Remote\Sort\EventSort;

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
		RemoteClient::CALENDARSERVER_GETCTAG,
		RemoteClient::SABREDAV_SYNC_TOKEN,
	];
	protected array $collectionPropertiesBasic = [
		RemoteClient::DAV_RESOURCE_TYPE,
		RemoteClient::DAV_DISPLAYNAME,
		RemoteClient::CALENDARSERVER_GETCTAG,
		RemoteClient::SABREDAV_SYNC_TOKEN,
	];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'calendarIds', 'uid', 'created', 'updated'
	];

	public function __construct() {
	}

	public function initialize(RemoteClient $dataStore) {
		if ($dataStore->getCalendarHome() === null) {
			throw new RuntimeException('Remote calendar home set is not configured.');
		}

		$this->dataStore = $dataStore;

	}

	/**
	 * list of collections in remote storage
	 *
	 * @since Release 1.0.0
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
	 * @since Release 1.0.0
	 *
	 * @param string|null $location Id of parent collection
	 * @param string|null $granularity Amount of detail to return
	 * @param IRange|null $range Range of collections to return
	 * @param IFilter|null $filter Properties to filter by
	 * @param ISort|null $sort Properties to sort by
	 */
	public function entityList(?string $location = null, ?string $granularity = null, ?IRangeTally $range = null, ?IFilter $filter = null, ?ISort $sort = null, ?int $depth = null): array {
		// construct request
		$r0 = new EventQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				$value = $condition['value'];
				match($condition['attribute']) {
					'before' => $r0->filter()->before($value),
					'after' => $r0->filter()->after($value),
					'uid' => $r0->filter()->uid($value),
					'text' => $r0->filter()->text($value),
					'title' => $r0->filter()->title($value),
					'description' => $r0->filter()->description($value),
					'location' => $r0->filter()->location($value),
					'owner' => $r0->filter()->owner($value),
					'attendee' => $r0->filter()->attendee($value),
					default => null
				};
			}
		}
		// define order
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				$direction = $condition['direction'];
				match($condition['attribute']) {
					'created' => $r0->sort()->created($direction),
					'modified' => $r0->sort()->updated($direction),
					'start' => $r0->sort()->start($direction),
					'uid' => $r0->sort()->uid($direction),
					'recurrence' => $r0->sort()->recurrence($direction),
					default => null
				};
			}
		}
		// define range
		if ($range !== null) {
			if ($range->anchor() === RangeAnchorType::ABSOLUTE) {
				$r0->limitAbsolute($range->getPosition(), $range->getCount());
			}
			if ($range->anchor() === RangeAnchorType::RELATIVE) {
				$r0->limitRelative($range->getPosition(), $range->getCount());
			}
		}
		// construct request
		$r1 = new EventGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set target to query request
		$r1->targetFromRequest($r0, '/ids');
		// select properties to return
		if ($granularity === 'B') {
			$r1->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0, $r1]);
		// extract response
		$response = $bundle->response(1);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert json objects to message objects
		$state = $response->state();
		$list = $response->objects();
		foreach ($list as $id => $entry) {
			$eo = $this->toEventObject($entry);
			$eo->Signature = $this->generateSignature($eo);
			$list[$id] = $eo;
		}
		// return message collection
		return ['list' => $list, 'state' => $state];

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
	 * @since Release 1.0.0
	 *
	 * @return DeltaObject
	 */
	public function entityDelta(?string $location, string $state, string $granularity = 'D'): DeltaObject {

		if (empty($state)) {
			$results = $this->entityList($location, 'B');
			$delta = new DeltaObject();
			$delta->signature = $results['state'];
			foreach ($results['list'] as $entry) {
				$delta->additions[] = $entry->ID;
			}
			return $delta;
		}
		if (empty($location)) {
			return $this->entityDeltaDefault($state, $granularity);
		} else {
			return $this->entityDeltaSpecific($location, $state, $granularity);
		}
	}

	/**
	 * delta of changes for specific collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaSpecific(?string $location, string $state, string $granularity = 'D'): DeltaObject {
		// construct set request
		$r0 = new EventQueryChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set location constraint
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('0');
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object to delta object
		$delta = new DeltaObject();
		$delta->signature = $response->stateNew();
		$delta->additions = new BaseStringCollection($response->added());
		$delta->modifications = new BaseStringCollection($response->updated());
		$delta->deletions = new BaseStringCollection($response->removed());

		return $delta;
	}

	/**
	 * delta of changes in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaDefault(string $state, string $granularity = 'D'): DeltaObject {
		// construct set request
		$r0 = new EventChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// set state constraint
		if (!empty($state)) {
			$r0->state($state);
		} else {
			$r0->state('');
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object to delta object
		$delta = new DeltaObject();
		$delta->signature = $response->stateNew();
		$delta->additions = new BaseStringCollection($response->created());
		$delta->modifications = new BaseStringCollection($response->updated());
		$delta->deletions = new BaseStringCollection($response->deleted());

		return $delta;
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $identifier Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return EventObject|null
	 */
	public function entityFetch(string $location, string $identifier, string $granularity = 'D'): ?EventObject {
		// construct request
		$r0 = new EventGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target($identifier);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object to event object
		$so = $response->object(0);
		if ($so instanceof EventParametersResponse) {
			$to = $this->toEventObject($so);
			$to->Signature = $this->generateSignature($to);
		}
		return $to ?? null;
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param array<string> $identifiers Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return array<string,EventObject>
	 */
	public function entityFetchMultiple(string $location, array $identifiers, string $granularity = 'D'): array {
		// construct request
		$r0 = new EventGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->target(...$identifiers);
		// select properties to return
		if ($granularity === 'B') {
			$r0->property(...$this->entityPropertiesBasic);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap object(s) to event object
		$list = $response->objects();
		foreach ($list as $id => $so) {
			if ($so instanceof EventParametersResponse) {
				continue;
			}
			$to = $this->toEventObject($so);
			$to->Signature = $this->generateSignature($to);
			$list[$id] = $so;
		}
		// return object(s)
		return $list;
	}

	/**
	 * create entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCreate(string $location, EventObject $so): ?EventObject {
		// convert entity
		$entity = $this->fromEventObject($so);
		$id = uniqid();
		// construct set request
		$r0 = new EventSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->create($id, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// check for success
		$result = $response->createSuccess($id);
		if ($result !== null) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $result['id'];
			$ro->CreatedOn = isset($result['updated']) ? new DateTimeImmutable($result['updated']) : null;
			$ro->ModifiedOn = $ro->CreatedOn;
			$ro->Signature = $this->generateSignature($ro);
			return $ro;
		}
		// check for failure
		$result = $response->createFailure($id);
		if ($result !== null) {
			$type = $result['type'] ?? 'unknownError';
			$description = $result['description'] ?? 'An unknown error occurred during collection creation.';
			throw new Exception("$type: $description", 1);
		}
		// return null if creation failed without failure reason
		return null;
	}

	/**
	 * update entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityModify(string $location, string $identifier, EventObject $so): ?EventObject {
		// convert entity
		$entity = $this->fromEventObject($so);
		// construct set request
		$r0 = new EventSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		$r0->update($identifier, $entity)->in($location);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// check for success
		$result = $response->updateSuccess($identifier);
		if ($result !== null) {
			$ro = clone $so;
			$ro->Origin = OriginTypes::External;
			$ro->ID = $identifier;
			$ro->ModifiedOn = isset($result['updated']) ? new DateTimeImmutable($result['updated']) : null;
			$ro->Signature = $this->generateSignature($ro);
			return $ro;
		} 
		// check for failure
		$result = $response->updateFailure($identifier);
		if ($result !== null) {
			$type = $result['type'] ?? 'unknownError';
			$description = $result['description'] ?? 'An unknown error occurred during collection modification.';
			throw new Exception("$type: $description", 1);
		}
		// return null if modification failed without failure reason
		return null;
	}

	/**
	 * delete entity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDelete(string $location, string $identifier): ?string {
		// construct set request
		$r0 = new EventSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($identifier);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// check for command error
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// check for success
		$result = $response->deleteSuccess($identifier);
		if ($result !== null) {
			return (string)$result['id'];
		}
		// check for failure
		$result = $response->deleteFailure($identifier);
		if ($result !== null) {
			$type = $result['type'] ?? 'unknownError';
			$description = $result['description'] ?? 'An unknown error occurred during collection deletion.';
			throw new Exception("$type: $description", 1);
		}
		// return null if deletion failed without failure reason
		return null;
	}

	/**
	 * copy entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityCopy(string $sourceLocation, string $identifier, string $destinationLocation): string {
		return '';
	}

	/**
	 * move entity in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityMove(string $sourceLocation, string $identifier, string $destinationLocation): string {
		// construct set request
		$r0 = new EventSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($identifier);
		$m0->in($destinationLocation);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($identifier, $response->updated()) ? (string)$identifier : '';
	}

	/**
	 * convert dav collection to event collection
	 *
	 * @since Release 1.0.0
	 */
	private function toCollection(string $id, array $so): Collection {
		$to = new Collection();
		$to->Id = $id;
		$to->Label = $so[RemoteClient::DAV_DISPLAYNAME] ?? null;
		$to->Description = $so[RemoteClient::CALDAV_CALENDAR_DESCRIPTION] ?? null;
		$to->Priority = $so[RemoteClient::APPLE_ICAL_CALENDAR_ORDER] ?? null;
		$to->Color = $so[RemoteClient::APPLE_ICAL_CALENDAR_COLOR] ?? null;
		return $to;
	}

	/**
	 * generate entity signature
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject $to - event object
	 *
	 * @return string entity signature
	 */
	public function generateSignature(EventObject $to): string {

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
			$o->UUID,
			$o->CreatedOn,
			$o->ModifiedOn
		);

		// generate signature
		return md5(json_encode($o, JSON_PARTIAL_OUTPUT_ON_ERROR));

	}

}
