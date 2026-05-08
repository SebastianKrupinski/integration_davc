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

namespace OCA\JMAPC\Service\Remote;

use Datetime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

use JmapClient\Client;
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
use OCA\JMAPC\Exceptions\JmapUnknownMethod;
use OCA\JMAPC\Objects\BaseStringCollection;
use OCA\JMAPC\Objects\DeltaObject;
use OCA\JMAPC\Objects\Event\EventAvailabilityTypes;
use OCA\JMAPC\Objects\Event\EventCollectionObject;
use OCA\JMAPC\Objects\Event\EventLocationPhysicalObject;
use OCA\JMAPC\Objects\Event\EventLocationVirtualObject;
use OCA\JMAPC\Objects\Event\EventMutationObject;
use OCA\JMAPC\Objects\Event\EventNotificationAnchorTypes;
use OCA\JMAPC\Objects\Event\EventNotificationObject;
use OCA\JMAPC\Objects\Event\EventNotificationPatterns;
use OCA\JMAPC\Objects\Event\EventNotificationTypes;
use OCA\JMAPC\Objects\Event\EventObject;
use OCA\JMAPC\Objects\Event\EventOccurrenceObject;
use OCA\JMAPC\Objects\Event\EventOccurrencePatternTypes;
use OCA\JMAPC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\JMAPC\Objects\Event\EventParticipantObject;
use OCA\JMAPC\Objects\Event\EventParticipantRoleTypes;
use OCA\JMAPC\Objects\Event\EventParticipantStatusTypes;
use OCA\JMAPC\Objects\Event\EventParticipantTypes;
use OCA\JMAPC\Objects\Event\EventSensitivityTypes;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Range\IRangeTally;
use OCA\JMAPC\Store\Common\Range\RangeAnchorType;
use OCA\JMAPC\Store\Common\Sort\ISort;
use OCA\JMAPC\Store\Remote\Filters\EventFilter;
use OCA\JMAPC\Store\Remote\Sort\EventSort;

class RemoteEventsService {
	public ?DateTimeZone $SystemTimeZone = null;
	public ?DateTimeZone $UserTimeZone = null;

	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	protected array $collectionPropertiesDefault = [];
	protected array $collectionPropertiesBasic = [];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'calendarIds', 'uid', 'created', 'updated'
	];

	public function __construct() {

	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// determine account
		if ($dataAccount === null) {
			if ($this->resourceNamespace !== null) {
				$account = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
			} else {
				$account = $dataStore->sessionAccountDefault('contacts');
			}
			$this->dataAccount = $account !== null ? $account->id() : '';
		} else {
			$this->dataAccount = $dataAccount;
		}

	}

	/**
	 * list of collections in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string|null $location Id of parent collection
	 * @param string|null $granularity Amount of detail to return
	 * @param int|null $depth Depth of sub collections to return
	 *
	 * @return array<string,EventCollectionObject>
	 */
	public function collectionList(?string $location = null, ?string $granularity = null, ?int $depth = null): array {
		// construct request
		$r0 = new CalendarGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		// define location
		if ($location !== null) {
			$r0->target($location);
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
		// convert jmap objects to collection objects
		$list = [];
		foreach ($response->objects() as $id => $so) {
			if (!$so instanceof CalendarParametersResponse) {
				continue;
			}
			$to = $this->toEventCollection($so);
			$to->Signature = $response->state();
			$list[$id] = $to;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFetch(string $identifier): ?EventCollectionObject {
		// construct request
		$r0 = new CalendarGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->target($identifier);
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
		// convert jmap object to collection object
		$so = $response->object(0);
		$to = null;
		if ($so instanceof CalendarParametersResponse) {
			$to = $this->toEventCollection($so);
			$to->Signature = $response->state();
		}
		return $to;
	}

	/**
	 * create collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function collectionCreate(EventCollectionObject $so): ?string {
		// convert entity
		$to = $this->fromEventCollection($so);
		$id = uniqid();
		// construct request
		$r0 = new CalendarSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->create($id, $to);
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
			return (string)$result['id'];
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
	 * modify collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionModify(string $identifier, EventCollectionObject $so): ?string {
		// convert entity
		$to = $this->fromEventCollection($so);
		// construct request
		$r0 = new CalendarSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->update($identifier, $to);
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
			return (string)$result['id'];
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
	 * delete collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function collectionDelete(string $identifier): ?string {
		// construct request
		$r0 = new CalendarSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->delete($identifier);
		$r0->deleteContents(true);
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
	 * convert jmap collection to event collection
	 *
	 * @since Release 1.0.0
	 *
	 */
	private function toEventCollection(CalendarParametersResponse $so): EventCollectionObject {
		$to = new EventCollectionObject();
		$to->Id = $so->id();
		$to->Label = $so->label();
		$to->Description = $so->description();
		$to->Priority = $so->priority();
		$to->Visibility = $so->visible();
		$to->Color = $so->color();
		return $to;
	}

	/**
	 * convert event collection to jmap collection
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromEventCollection(EventCollectionObject $so): CalendarParametersRequest {
		// create object
		$to = new CalendarParametersRequest();

		if ($so->Label !== null) {
			$to->label($so->Label);
		}
		if ($so->Description !== null) {
			$to->description($so->Description);
		}
		if ($so->Priority !== null) {
			$to->priority($so->Priority);
		}
		if ($so->Visibility !== null) {
			$to->visible($so->Visibility);
		}
		if ($so->Color !== null) {
			$to->color($so->Color);
		}

		return $to;
	}

	/**
	 * convert jmap object to event object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function toEventObject(EventParametersResponse $so): EventObject {
		// create object
		$do = new EventObject();
		// source origin
		$do->Origin = OriginTypes::External;
		// collection id
		if ($so->in() !== []) {
			$do->CID = $so->in()[0];
		}
		// entity id
		if ($so->id() !== null) {
			$do->ID = $so->id();
		}
		// universal id
		if ($so->uid() !== null) {
			$do->UUID = $so->uid();
		}
		// creation date time
		if ($so->created() !== null) {
			$do->CreatedOn = $so->created();
		}
		// modification date time
		if ($so->updated() !== null) {
			$do->ModifiedOn = $so->updated();
		}
		// occurrence(s)
		if ($so->recurrenceRule() !== null) {
			$soRule = $so->recurrenceRule();
			$doRule = new EventOccurrenceObject();

			// Interval
			if ($soRule->interval() !== null) {
				$doRule->Interval = $soRule->interval();
			}
			// Iterations
			if ($soRule->count() !== null) {
				$doRule->Iterations = $soRule->count();
			}
			// Conclusion
			if ($soRule->until() !== null) {
				$doRule->Concludes = new DateTime($soRule->until());
			}
			// Daily
			if ($soRule->frequency() === 'daily') {
				$doRule->Pattern = EventOccurrencePatternTypes::Absolute;
				$doRule->Precision = EventOccurrencePrecisionTypes::Daily;
			}
			// Weekly
			if ($soRule->frequency() === 'weekly') {
				$doRule->Pattern = EventOccurrencePatternTypes::Absolute;
				$doRule->Precision = EventOccurrencePrecisionTypes::Weekly;
				$doRule->OnDayOfWeek = $this->fromDaysOfWeek($soRule->byDayOfWeek());
			}
			// Monthly
			if ($soRule->frequency() === 'monthly') {
				$doRule->Precision = EventOccurrencePrecisionTypes::Monthly;
				// Absolute
				if ($soRule->byDayOfMonth() !== []) {
					$doRule->Pattern = EventOccurrencePatternTypes::Absolute;
					$doRule->OnDayOfMonth = $soRule->byDayOfMonth();
				}
				// Relative
				else {
					$doRule->Pattern = EventOccurrencePatternTypes::Relative;
					$doRule->OnDayOfWeek = $this->fromDaysOfWeek($soRule->byDayOfWeek());
					$doRule->OnPosition = $soRule->byPosition();
				}
			}
			// Yearly
			if ($soRule->frequency() === 'yearly') {
				$doRule->Precision = EventOccurrencePrecisionTypes::Yearly;
				// nth day of year
				if ($soRule->byDayOfYear() !== []) {
					$doRule->Pattern = EventOccurrencePatternTypes::Absolute;
					$doRule->OnDayOfYear = $soRule->byDayOfYear();
					$doRule->OnDayOfWeek = $this->fromDaysOfWeek($soRule->byDayOfWeek());
				}
				// nth week of year
				elseif ($soRule->byWeekOfYear() !== []) {
					$doRule->Pattern = EventOccurrencePatternTypes::Relative;
					$doRule->OnWeekOfYear = $soRule->byWeekOfYear();
					$doRule->OnDayOfWeek = $this->fromDaysOfWeek($soRule->byDayOfWeek());
				}
				// nth month of year
				elseif ($soRule->byMonthOfYear() !== []) {
					if ($soRule->byDayOfMonth() !== []) {
						$doRule->Pattern = EventOccurrencePatternTypes::Absolute;
						$doRule->OnDayOfMonth = $soRule->byDayOfMonth();
					} else {
						$doRule->Pattern = EventOccurrencePatternTypes::Relative;
						$doRule->OnDayOfWeek = $this->fromDaysOfWeek($soRule->byDayOfWeek());
						$doRule->OnPosition = $soRule->byPosition();
					}
				}
			}
			// add to collection
			$do->OccurrencePattern = $doRule;
		}
		// other
		$this->toEventInstanceObject($so, $do);
		// mutations
		foreach ($so->recurrenceMutations() as $id => $entry) {
			/** @var EventMutationObject $mutation */
			$mutation = $this->toEventInstanceObject($entry, new EventMutationObject());
			$mutation->mutationId = $entry->mutationId() ?? new DateTimeImmutable($id);
			$mutation->mutationTz = $entry->mutationTimeZone() ?? $do->TimeZone->getName();
			$do->OccurrenceMutations[$id] = $mutation;
		}

		return $do;
	}

	/**
	 * convert jmap object to event object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function toEventInstanceObject(EventParametersResponse|EventMutationParametersResponse $so, EventObject|EventMutationObject $do): EventObject|EventMutationObject {
		// sequence
		if ($so->sequence() !== null) {
			$do->Sequence = $so->sequence();
		}
		// time zone
		if ($so->timezone() !== null) {
			$do->TimeZone = new DateTimeZone($so->timezone());
		}
		// start date/time
		if ($so->starts() !== null) {
			$do->StartsOn = $so->starts();
			$do->StartsTZ = $do->TimeZone;
		}
		// end date/time
		if ($so->ends() !== null) {
			$do->EndsOn = $so->ends();
			$do->EndsTZ = $do->TimeZone;
		}
		// duration
		if ($so->duration() !== null) {
			$do->Duration = $so->duration();
		}
		// all bay event
		if ($so->timeless()) {
			$do->Timeless = true;
		}
		// label
		if ($so->label() !== null) {
			$do->Label = $so->label();
		}
		// description
		if ($so->descriptionContents() !== null) {
			$do->Description = $so->descriptionContents();
		}
		// physical location(s)
		foreach ($so->physicalLocations() as $id => $entry) {
			$entity = new EventLocationPhysicalObject();
			$entity->Id = (string)$id;
			$entity->Name = $entry->label();
			$entity->Description = $entry->description();
			$do->LocationsPhysical[$id] = $entity;
		}
		// virtual location(s)
		foreach ($so->virtualLocations() as $id => $entry) {
			$entity = new EventLocationVirtualObject();
			$entity->Id = (string)$id;
			$entity->Name = $entry->label();
			$entity->Description = $entry->description();
			$do->LocationsVirtual[$id] = $entity;
		}
		// availability
		if ($so->availability() !== null) {
			$do->Availability = match (strtolower($so->availability() ?? 'busy')) {
				'free' => EventAvailabilityTypes::Free,
				default => EventAvailabilityTypes::Busy,
			};
		}
		// priority
		if ($so->priority() !== null) {
			$do->Priority = $so->priority();
		}
		// sensitivity
		if ($so->privacy() !== null) {
			$do->Sensitivity = match (strtolower($so->privacy() ?? 'public')) {
				'private' => EventSensitivityTypes::Private,
				'secret' => EventSensitivityTypes::Secret,
				default => EventSensitivityTypes::Public,
			};
		}
		// color
		if ($so->color() !== null) {
			$do->Color = $so->color();
		}
		// categories(s)
		foreach ($so->categories() as $id => $entry) {
			$do->Categories[] = $entry;
		}
		// tag(s)
		foreach ($so->tags() as $id => $entry) {
			$do->Tags[] = $entry;
		}
		// Organizer - Address and Name
		if ($so->sender() !== null) {
			$sender = $this->fromSender($so->sender());
			$do->Organizer->Address = $sender['address'];
			$do->Organizer->Name = $sender['name'];
		}
		// participant(s)
		foreach ($so->participants() as $id => $entry) {
			$entity = new EventParticipantObject();
			$entity->Id = (string)$id;
			$entity->Address = $entry->address();
			$entity->Name = $entry->name();
			$entity->Description = $entry->description();
			$entity->Comment = $entry->comment();
			$entity->Type = match (strtolower($entry->kind() ?? 'unknown')) {
				'individual' => EventParticipantTypes::Individual,
				'group' => EventParticipantTypes::Group,
				'resource' => EventParticipantTypes::Resource,
				'location' => EventParticipantTypes::Location,
				default => EventParticipantTypes::Unknown,
			};
			$entity->Status = match (strtolower($entry->status() ?? 'needs-action')) {
				'accepted' => EventParticipantStatusTypes::Accepted,
				'declined' => EventParticipantStatusTypes::Declined,
				'tentative' => EventParticipantStatusTypes::Tentative,
				'delegated' => EventParticipantStatusTypes::Delegated,
				default => EventParticipantStatusTypes::None,
			};

			foreach ($entry->roles() as $role => $value) {
				$entity->Roles[$role] = EventParticipantRoleTypes::from($role);
			}
			$do->Participants[$id] = $entity;
		}
		// notification(s)
		foreach ($so->notifications() as $id => $entry) {
			$trigger = $entry->trigger();
			$entity = new EventNotificationObject();
			$entity->Type = match (strtolower($entry->action() ?? 'display')) {
				'email' => EventNotificationTypes::Email,
				'audio' => EventNotificationTypes::Audible,
				default => EventNotificationTypes::Visual,
			};
			$entity->Pattern = match (strtolower($trigger->type() ?? 'unknown')) {
				'absolute' => EventNotificationPatterns::Absolute,
				'relative' => EventNotificationPatterns::Relative,
				default => EventNotificationPatterns::Unknown,
			};
			if ($entity->Pattern === EventNotificationPatterns::Absolute) {
				$entity->When = $trigger->when();
			} elseif ($entity->Pattern === EventNotificationPatterns::Relative) {
				$entity->Anchor = match (strtolower($trigger->anchor() ?? 'start')) {
					'end' => EventNotificationAnchorTypes::End,
					default => EventNotificationAnchorTypes::Start,
				};
				$entity->Offset = $trigger->offset();
			}
			$do->Notifications[$id] = $entity;
		}

		return $do;
	}

	/**
	 * convert event object to jmap object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromEventObject(EventObject $so): EventParametersRequest {
		// create object
		$do = new EventParametersRequest();
		// universal id
		if ($so->UUID !== null) {
			$do->uid($so->UUID);
		}
		// creation date time
		if ($so->CreatedOn !== null) {
			$do->created($so->CreatedOn);
		}
		// modification date time
		if ($so->ModifiedOn !== null) {
			$do->updated($so->ModifiedOn);
		}
		// occurrence(s)
		if ($so->OccurrencePattern !== null) {
			$soRule = $so->OccurrencePattern;
			$doRule = $do->recurrenceRule();
			$doRule->frequency(match ($soRule->Precision ?? EventOccurrencePrecisionTypes::Daily) {
				EventOccurrencePrecisionTypes::Yearly => 'yearly',
				EventOccurrencePrecisionTypes::Monthly => 'monthly',
				EventOccurrencePrecisionTypes::Weekly => 'weekly',
				EventOccurrencePrecisionTypes::Hourly => 'hourly',
				EventOccurrencePrecisionTypes::Minutely => 'minutely',
				EventOccurrencePrecisionTypes::Secondly => 'secondly',
				default => 'daily',
			});
			if ($soRule->Interval !== null) {
				$doRule->interval($soRule->Interval);
			}
			if ($soRule->Iterations !== null) {
				$doRule->count($soRule->Iterations);
			}
			if ($soRule->Concludes !== null) {
				$doRule->until($soRule->Concludes);
			}
			if ($soRule->OnDayOfWeek !== []) {
				foreach ($soRule->OnDayOfWeek as $id => $day) {
					$nDay = $doRule->byDayOfWeek($id);
					$nDay->day($day);
				}
			}
			if ($soRule->OnDayOfMonth !== []) {
				$doRule->byDayOfMonth(...$soRule->OnDayOfMonth);
			}
			if ($soRule->OnDayOfYear !== []) {
				$doRule->byDayOfYear(...$soRule->OnDayOfYear);
			}
			if ($soRule->OnWeekOfMonth !== []) {
				$doRule->byWeekOfYear(...$soRule->OnWeekOfMonth);
			}
			if ($soRule->OnWeekOfYear !== []) {
				$doRule->byWeekOfYear(...$soRule->OnWeekOfYear);
			}
			if ($soRule->OnMonthOfYear !== []) {
				$doRule->byMonthOfYear(...$soRule->OnMonthOfYear);
			}
			if ($soRule->OnHour !== []) {
				$doRule->byHour(...$soRule->OnHour);
			}
			if ($soRule->OnMinute !== []) {
				$doRule->byMinute(...$soRule->OnMinute);
			}
			if ($soRule->OnSecond !== []) {
				$doRule->bySecond(...$soRule->OnSecond);
			}
			if ($soRule->OnPosition !== []) {
				$doRule->byPosition(...$soRule->OnPosition);
			}
		}
		// common properties
		$this->fromEventInstanceObject($so, $do);

		foreach ($so->OccurrenceMutations as $id => $mutation) {
			$entity = new EventMutationParametersRequest();
			if ($mutation->mutationId) {
				$mutationId = clone $mutation->mutationId;
			} else {
				$mutationId = new DateTimeImmutable($id);
			}
			$entity->mutationId($mutationId);
			if ($mutation->mutationTz) {
				$entity->mutationTimeZone($mutation->mutationTz);
			}
			$this->fromEventInstanceObject($mutation, $entity);
			$do->recurrenceMutations($mutationId, $entity);
		}

		return $do;
	}

	/**
	 * convert event object to jmap object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromEventInstanceObject(EventObject|EventMutationObject $so, EventParametersRequest|EventMutationParametersRequest $do): EventParametersRequest|EventMutationParametersRequest {
		// sequence
		if ($so->Sequence !== null) {
			$do->sequence($so->Sequence);
		}
		// time zone
		if ($so->TimeZone !== null) {
			$do->timezone($so->TimeZone->getName());
		}
		// start date/time
		if ($so->StartsOn !== null) {
			$do->starts($so->StartsOn);
		}
		// duration
		if ($so->Duration !== null) {
			$do->duration($so->Duration);
		} elseif ($so->EndsOn instanceof DateTimeInterface) {
			$do->duration($so->StartsOn->diff($so->EndsOn));
		}
		// all day Event
		if ($so->Timeless !== null) {
			$do->timeless($so->Timeless);
		}
		// label
		if ($so->Label !== null) {
			$do->label($so->Label);
		}
		// description
		if ($so->Description !== null) {
			$do->descriptionContents($so->Description);
		}
		// physical location(s)
		foreach ($so->LocationsPhysical as $entry) {
			$entity = $do->physicalLocations($entry->Id);
			if ($entry->Name !== null) {
				$entity->label($entry->Name);
			}
			if ($entry->Description !== null) {
				$entity->description($entry->Description);
			}
		}
		// virtual location(s)
		foreach ($so->LocationsVirtual as $entry) {
			$entity = $do->virtualLocations($entry->Id);
			if ($entry->Name !== null) {
				$entity->label($entry->Name);
			}
			if ($entry->Description !== null) {
				$entity->description($entry->Description);
			}
		}
		// availability
		if ($so->Availability !== null) {
			$do->availability(match ($so->Availability ?? EventAvailabilityTypes::Busy) {
				EventAvailabilityTypes::Free => 'free',
				default => 'busy',
			});
		}
		// priority
		if ($so->Priority !== null) {
			$do->priority($so->Priority);
		}
		// sensitivity
		if ($so->Sensitivity !== null) {
			$do->privacy(match ($so->Sensitivity ?? EventSensitivityTypes::Public) {
				EventSensitivityTypes::Private => 'private',
				EventSensitivityTypes::Secret => 'secret',
				default => 'public',
			});
		}
		// color
		if ($so->Color !== null) {
			$do->color($so->Color);
		}
		// tag(s)
		if ($so->Tags->count() > 0) {
			$do->tags(...$so->Tags);
		}
		// participant(s)
		foreach ($so->Participants as $entry) {
			$entity = $do->participants($entry->Id);
			if ($entry->Address !== null) {
				$entity->address($entry->Address);
				$entity->send('imip', 'mailto:' . $entry->Address);
			}
			if ($entry->Name !== null) {
				$entity->name($entry->Name);
			}
			if ($entry->Description !== null) {
				$entity->description($entry->Description);
			}
			if ($entry->Comment !== null) {
				$entity->comment($entry->Comment);
			}
			if ($entry->Type !== null) {
				$entity->kind(match ($entry->Type ?? EventParticipantTypes::Individual) {
					EventParticipantTypes::Group => 'group',
					EventParticipantTypes::Resource => 'resource',
					EventParticipantTypes::Location => 'location',
					default => 'individual',
				});
			}
			if ($entry->Status !== null) {
				$entity->kind(match ($entry->Status ?? EventParticipantStatusTypes::None) {
					EventParticipantStatusTypes::Accepted => 'accepted',
					EventParticipantStatusTypes::Declined => 'declined',
					EventParticipantStatusTypes::Tentative => 'tentative',
					EventParticipantStatusTypes::Delegated => 'delegated',
					default => 'needs-action',
				});
			}
			if ($entry->Roles->count() > 0) {
				$roles = [];
				foreach ($entry->Roles as $role) {
					$roles[] = $role->value;
				}
				$entity->roles(...$roles);
			}
		}
		// notification(s)
		foreach ($so->Notifications as $entry) {
			$entity = $do->notifications($entry->Id);
			if ($entry->Type !== null) {
				$entity->action(match ($entry->Type ?? EventNotificationTypes::Visual) {
					EventNotificationTypes::Email => 'email',
					EventNotificationTypes::Audible => 'audio',
					default => 'display',
				});
			}
			if ($entry->Pattern === EventNotificationPatterns::Absolute) {
				$entity->trigger('absolute')->when($entry->When);
			} elseif ($entry->Pattern === EventNotificationPatterns::Relative) {
				if ($entry->Anchor === EventNotificationAnchorTypes::End) {
					$entity->trigger('offset')->anchor('end')->offset($entry->Offset);
				} else {
					$entity->trigger('offset')->anchor('start')->offset($entry->Offset);
				}
			} else {
				$entity->trigger('unknown');
			}
		}

		return $do;
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

	/**
	 * convert remote availability status to event object availability status
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $value remote availability status value
	 *
	 * @return string event object availability status value
	 */
	private function fromSender(?string $value): array {

		// Check if there are angle brackets
		$bracketStart = strpos($value, '<');
		$bracketEnd = strpos($value, '>');

		// If brackets are present
		if ($bracketStart !== false && $bracketEnd !== false) {
			// Extract the name and address
			$name = trim(substr($value, 0, $bracketStart));
			$address = trim(substr($value, $bracketStart + 1, $bracketEnd - $bracketStart - 1));
		} else {
			$name = null;
			$address = $value;
		}

		return ['address' => $address, 'name' => $name];

	}

	/**
	 * convert remote days of the week to event object days of the week
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $days - remote days of the week values(s)
	 *
	 * @return array event object days of the week values(s)
	 */
	private function fromDaysOfWeek(array $days): array {

		$dow = [];
		foreach ($days as $entry) {
			if (isset($entry['day'])) {
				$dow[] = $entry['day'];
			}
		}
		return $dow;

	}

	/**
	 * convert event object days of the week to remote days of the week
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $days - internal days of the week values(s)
	 *
	 * @return array event object days of the week values(s)
	 */
	private function toDaysOfWeek(array $days): array {

		$dow = [];
		foreach ($days as $key => $value) {
			# code...
		}

		return $dow;

	}

}
