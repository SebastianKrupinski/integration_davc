<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

use JmapClient\Client;
use JmapClient\Requests\Contacts\AddressBookGet;
use JmapClient\Requests\Contacts\AddressBookParameters as AddressBookParametersRequest;
use JmapClient\Requests\Contacts\AddressBookSet;
use JmapClient\Requests\Contacts\ContactChanges;
use JmapClient\Requests\Contacts\ContactGet;
use JmapClient\Requests\Contacts\ContactParameters as ContactParametersRequest;
use JmapClient\Requests\Contacts\ContactQuery;
use JmapClient\Requests\Contacts\ContactQueryChanges;
use JmapClient\Requests\Contacts\ContactSet;
use JmapClient\Responses\Contacts\AddressBookParameters as AddressBookParametersResponse;
use JmapClient\Responses\Contacts\ContactParameters as ContactParametersResponse;
use JmapClient\Responses\ResponseException;
use JmapClient\Session\Account;
use OCA\DAVC\Exceptions\JmapUnknownMethod;
use OCA\DAVC\Objects\BaseStringCollection;
use OCA\DAVC\Objects\Contact\ContactAliasObject;
use OCA\DAVC\Objects\Contact\ContactAnniversaryObject;
use OCA\DAVC\Objects\Contact\ContactCollectionObject;
use OCA\DAVC\Objects\Contact\ContactCryptoObject;
use OCA\DAVC\Objects\Contact\ContactEmailObject;
use OCA\DAVC\Objects\Contact\ContactNoteObject;
use OCA\DAVC\Objects\Contact\ContactObject as ContactObject;
use OCA\DAVC\Objects\Contact\ContactOrganizationObject;
use OCA\DAVC\Objects\Contact\ContactPhoneObject;
use OCA\DAVC\Objects\Contact\ContactPhysicalLocationObject;
use OCA\DAVC\Objects\Contact\ContactTitleObject;
use OCA\DAVC\Objects\Contact\ContactTitleTypes;
use OCA\DAVC\Objects\DeltaObject;
use OCA\DAVC\Objects\OriginTypes;
use OCA\DAVC\Store\Common\Filters\IFilter;
use OCA\DAVC\Store\Common\Range\IRangeTally;
use OCA\DAVC\Store\Common\Range\RangeAnchorType;
use OCA\DAVC\Store\Common\Sort\ISort;
use OCA\DAVC\Store\Remote\Filters\ContactFilter;
use OCA\DAVC\Store\Remote\Sort\ContactSort;

class RemoteContactsService {
	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	protected array $collectionPropertiesDefault = [];
	protected array $collectionPropertiesBasic = [];
	protected array $entityPropertiesDefault = [];
	protected array $entityPropertiesBasic = [
		'id', 'addressbookId', 'uid'
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
	 * @return array<string,ContactCollectionObject>
	 */
	public function collectionList(?string $location = null, ?string $granularity = null, ?int $depth = null): array {
		// construct request
		$r0 = new AddressBookGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		// set target to query request
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
		foreach ($response->objects() as $so) {
			if (!$so instanceof AddressBookParametersResponse) {
				continue;
			}
			$to = $this->toContactCollection($so);
			$to->Signature = $response->state();
			$list[] = $to;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve properties for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function collectionFetch(string $identifier): ?ContactCollectionObject {
		// construct request
		$r0 = new AddressBookGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
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
		if ($so instanceof AddressBookParametersResponse) {
			$to = $this->toContactCollection($so);
			$to->Signature = $response->state();
		}
		return $to;
	}

	/**
	 * create collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function collectionCreate(ContactCollectionObject $so): ?string {
		// convert entity
		$to = $this->fromContactCollection($so);
		$id = uniqid();
		// construct request
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
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
	 */
	public function collectionModify(string $identifier, ContactCollectionObject $so): ?string {
		// convert entity
		$to = $this->fromContactCollection($so);
		// construct request
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
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
		$r0 = new AddressBookSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
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
		$r0 = new ContactQuery($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// define location
		if (!empty($location)) {
			$r0->filter()->in($location);
		}
		// define filter
		if ($filter !== null) {
			foreach ($filter->conditions() as $condition) {
				$value = $condition['value'];
				match($condition['attribute']) {
					'createBefore' => $r0->filter()->createdBefore($value),
					'createAfter' => $r0->filter()->createdAfter($value),
					'modifiedBefore' => $r0->filter()->updatedBefore($value),
					'modifiedAfter' => $r0->filter()->updatedAfter($value),
					'uid' => $r0->filter()->uid($value),
					'kind' => $r0->filter()->kind($value),
					'member' => $r0->filter()->member($value),
					'text' => $r0->filter()->text($value),
					'name' => $r0->filter()->name($value),
					'nameGiven' => $r0->filter()->nameGiven($value),
					'nameSurname' => $r0->filter()->nameSurname($value),
					'nameAlias' => $r0->filter()->nameAlias($value),
					'organization' => $r0->filter()->organization($value),
					'email' => $r0->filter()->mail($value),
					'phone' => $r0->filter()->phone($value),
					'address' => $r0->filter()->address($value),
					'note' => $r0->filter()->note($value),
					default => null
				};
			}
		}
		// define sort
		if ($sort !== null) {
			foreach ($sort->conditions() as $condition) {
				$direction = $condition['direction'];
				match($condition['attribute']) {
					'created' => $r0->sort()->created($direction),
					'modified' => $r0->sort()->updated($direction),
					'nameGiven' => $r0->sort()->nameGiven($direction),
					'nameSurname' => $r0->sort()->nameSurname($direction),
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
		// construct get request
		$r1 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		// convert json objects to contact objects
		$state = $response->state();
		$list = $response->objects();
		foreach ($list as $id => $entry) {
			$eo = $this->toContactObject($entry);
			$eo->Signature = $this->generateSignature($eo);
			$list[$id] = $eo;
		}
		// return status object
		return ['list' => $list, 'state' => $state];
	}

	public function entityListFilter(): ContactFilter {
		return new ContactFilter();
	}

	public function entityListSort(): ContactSort {
		return new ContactSort();
	}

	/**
	 * delta for entities in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @return DeltaObject
	 */
	public function entityDelta(?string $location, string $state): DeltaObject {

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
			return $this->entityDeltaDefault($state);
		} else {
			return $this->entityDeltaSpecific($location, $state);
		}
	}

	/**
	 * delta of changes for specific collection in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function entityDeltaSpecific(?string $location, string $state): DeltaObject {
		// construct set request
		$r0 = new ContactQueryChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		$r0 = new ContactChanges($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
	 * retrieve entity from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $location Id of collection
	 * @param string $identifier Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return EventObject|null
	 */
	public function entityFetch(string $location, string $identifier, string $granularity = 'D'): ?ContactObject {
		// construct request
		$r0 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		if ($so instanceof ContactParametersResponse) {
			$to = $this->toContactObject($so);
			$to->Signature = $this->generateSignature($to);
		}

		return $to ?? null;
	}

	/**
	 * retrieve entity(ies) from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $location Id of collection
	 * @param array<string> $identifiers Id of entity
	 * @param string $granularity Amount of detail to return
	 *
	 * @return array<string,ContactObject>
	 */
	public function entityFetchMultiple(string $location, array $identifiers, string $granularity = 'D'): array {
		// construct request
		$r0 = new ContactGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
			if (!$so instanceof ContactParametersResponse) {
				continue;
			}
			$to = $this->toContactObject($so);
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
	public function entityCreate(string $location, ContactObject $so): ?ContactObject {
		// convert entity
		$entity = $this->fromContactObject($so);
		$id = uniqid();
		// construct set request
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
	public function entityModify(string $location, string $identifier, ContactObject $so): ?ContactObject {
		// convert entity
		$entity = $this->fromContactObject($so);
		// construct set request
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
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
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->delete($identifier);
		// transmit request and receive response
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
		$r0 = new ContactSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$m0 = $r0->update($identifier);
		$m0->in($destinationLocation);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection information
		return array_key_exists($identifier, $response->updated()) ? (string)$identifier : '';
	}

	private function toContactCollection(AddressBookParametersResponse $so): ContactCollectionObject {
		$to = new ContactCollectionObject();
		$to->Id = $so->id();
		$to->Label = $so->label();
		$to->Description = $so->description();
		$to->Priority = $so->priority();
		return $to;
	}

	public function fromContactCollection(ContactCollectionObject $so): AddressBookParametersRequest {
		// create object
		$to = new AddressBookParametersRequest();

		if ($so->Label !== null) {
			$to->label($so->Label);
		}
		if ($so->Description !== null) {
			$to->description($so->Description);
		}
		if ($so->Priority !== null) {
			$to->priority($so->Priority);
		}

		return $to;
	}

	/**
	 * convert jmap object to contact object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function toContactObject($so): ContactObject {

		// create object
		$do = new ContactObject();
		// source origin
		$do->Origin = OriginTypes::External;
		// collection id
		if ($so->in() !== null) {
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
		// kind
		if ($so->kind() !== null) {
			$do->Kind = $so->kind();
		}
		// name
		if ($so->name() !== null) {
			$nameParams = $so->name();
			foreach ($nameParams->components() as $component) {
				$kind = $component->kind();
				$value = $component->value();
				if ($kind === 'surname') {
					$do->Name->Last = $value;
				} elseif ($kind === 'given') {
					$do->Name->First = $value;
				} elseif ($kind === 'additional') {
					$do->Name->Other = $value;
				} elseif ($kind === 'prefix') {
					$do->Name->Prefix = $value;
				} elseif ($kind === 'suffix') {
					$do->Name->Suffix = $value;
				}
			}
		}
		// aliases
		if ($so->aliases() !== null) {
			foreach ($so->aliases() as $id => $entry) {
				$entity = new ContactAliasObject();
				$entity->Id = (string)$id;
				$entity->Label = $entry->name();
				$do->Name->Aliases[$id] = $entity;
			}
		}
		// anniversaries
		if ($so->anniversaries() !== null) {
			foreach ($so->anniversaries() as $id => $entry) {
				$entity = new ContactAnniversaryObject();
				if ($entry->date() !== null) {
					$dateParams = $entry->date();
					if (method_exists($dateParams, 'value')) {
						$entity->When = $dateParams->value();
					}
				}
				$do->Anniversaries[$id] = $entity;
			}
		}
		// emails
		if ($so->emails() !== null) {
			foreach ($so->emails() as $id => $entry) {
				$entity = new ContactEmailObject();
				$entity->Id = (string)$id;
				$entity->Address = $entry->address();
				$entity->Priority = $entry->priority();
				$entity->Context = !empty($entry->context()) ? $entry->context()[0] : null;
				$do->Email[$id] = $entity;
			}
		}
		// phones
		if ($so->phones() !== null) {
			foreach ($so->phones() as $id => $entry) {
				$entity = new ContactPhoneObject();
				$entity->Id = (string)$id;
				$entity->Number = $entry->number();
				$entity->Label = $entry->label();
				$entity->Priority = $entry->priority();
				$entity->Context = !empty($entry->context()) ? $entry->context()[0] : null;
				$do->Phone[$id] = $entity;
			}
		}
		// addresses
		if ($so->addresses() !== null) {
			foreach ($so->addresses() as $id => $entry) {
				$entity = new ContactPhysicalLocationObject();
				$entity->Id = (string)$id;
				$entity->Coordinates = $entry->coordinates();
				if ($entry->timeZone() !== null) {
					$entity->TimeZone = $entry->timeZone()->getName();
				}
				$entity->Country = $entry->country();
				// parse components
				foreach ($entry->components() as $component) {
					$kind = $component->kind();
					$value = $component->value();
					if ($kind === 'pobox') {
						$entity->Box = $value;
					} elseif ($kind === 'unit') {
						$entity->Unit = $value;
					} elseif ($kind === 'street') {
						$entity->Street = $value;
					} elseif ($kind === 'locality') {
						$entity->Locality = $value;
					} elseif ($kind === 'region') {
						$entity->Region = $value;
					} elseif ($kind === 'code') {
						$entity->Code = $value;
					} elseif ($kind === 'country') {
						$entity->Country = $value;
					}
				}
				$do->PhysicalLocations[$id] = $entity;
			}
		}
		// organizations
		if ($so->organizations() !== null) {
			foreach ($so->organizations() as $id => $entry) {
				$entity = new ContactOrganizationObject();
				$entity->Id = (string)$id;
				$entity->Label = $entry->name();
				$entity->SortName = $entry->sorting();
				if ($entry->units() !== null) {
					foreach ($entry->units() as $unit) {
						$entity->Units[] = $unit->name();
					}
				}
				$do->Organizations[$id] = $entity;
			}
		}
		// titles
		if ($so->titles() !== null) {
			foreach ($so->titles() as $id => $entry) {
				$entity = new ContactTitleObject();
				$entity->Id = (string)$id;
				$entity->Label = $entry->name();
				$entity->Relation = $entry->relation();
				$entity->Kind = match ($entry->kind() ?? 'title') {
					'role' => ContactTitleTypes::Role,
					default => ContactTitleTypes::Title,
				};
				$do->Titles[$id] = $entity;
			}
		}
		// tags
		if ($so->tags() !== null) {
			foreach ($so->tags() as $tag) {
				$do->Tags[] = $tag;
			}
		}
		// notes
		if ($so->notes() !== null) {
			foreach ($so->notes() as $id => $entry) {
				$entity = new ContactNoteObject();
				$entity->Id = (string)$id;
				$entity->Content = $entry->value();
				$entity->Date = $entry->created();
				$do->Notes[$id] = $entity;
			}
		}
		// crypto keys
		if ($so->crypto() !== null) {
			foreach ($so->crypto() as $id => $entry) {
				$entity = new ContactCryptoObject();
				$entity->Id = (string)$id;
				$entity->Type = $entry->kind();
				$entity->Data = $entry->uri();
				$do->Crypto[$id] = $entity;
			}
		}

		return $do;

	}

	/**
	 * convert contact object to jmap object
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function fromContactObject(ContactObject $so): mixed {

		// create object
		$to = new ContactParametersRequest();
		// universal id
		if ($so->UUID !== null) {
			$to->uid($so->UUID);
		}
		// creation date time
		if ($so->CreatedOn !== null) {
			$to->created($so->CreatedOn);
		}
		// modification date time
		if ($so->ModifiedOn !== null) {
			$to->updated($so->ModifiedOn);
		}
		// kind
		if ($so->Kind !== null) {
			$to->kind($so->Kind);
		}
		// name
		if ($so->Name !== null) {
			$nameParams = $to->name();
			if ($so->Name->First !== null || $so->Name->Last !== null || 
				$so->Name->Other !== null || $so->Name->Prefix !== null || 
				$so->Name->Suffix !== null) {
				// Build name components
				if ($so->Name->Prefix !== null) {
					$component = $nameParams->components();
					$component->kind('prefix');
					$component->value($so->Name->Prefix);
				}
				if ($so->Name->First !== null) {
					$component = $nameParams->components();
					$component->kind('given');
					$component->value($so->Name->First);
				}
				if ($so->Name->Other !== null) {
					$component = $nameParams->components();
					$component->kind('additional');
					$component->value($so->Name->Other);
				}
				if ($so->Name->Last !== null) {
					$component = $nameParams->components();
					$component->kind('surname');
					$component->value($so->Name->Last);
				}
				if ($so->Name->Suffix !== null) {
					$component = $nameParams->components();
					$component->kind('suffix');
					$component->value($so->Name->Suffix);
				}
			}
		}
		// aliases
		foreach ($so->Name->Aliases ?? [] as $id => $entry) {
			$aliasParams = $to->aliases((string)$id);
			if ($entry->Label !== null) {
				$aliasParams->name($entry->Label);
			}
		}
		// anniversaries
		foreach ($so->Anniversaries ?? [] as $id => $entry) {
			$annivParams = $to->anniversaries((string)$id);
			if ($entry->When !== null) {
				$annivParams->dateStamp()->value($entry->When);
			}
		}
		// emails
		foreach ($so->Email ?? [] as $id => $entry) {
			$emailParams = $to->emails((string)$id);
			if ($entry->Address !== null) {
				$emailParams->address($entry->Address);
			}
			if ($entry->Priority !== null) {
				$emailParams->priority($entry->Priority);
			}
			if ($entry->Context !== null) {
				$emailParams->context($entry->Context);
			}
		}
		// phones
		foreach ($so->Phone ?? [] as $id => $entry) {
			$phoneParams = $to->phones((string)$id);
			if ($entry->Number !== null) {
				$phoneParams->number($entry->Number);
			}
			if ($entry->Label !== null) {
				$phoneParams->label($entry->Label);
			}
			if ($entry->Priority !== null) {
				$phoneParams->priority($entry->Priority);
			}
			if ($entry->Context !== null) {
				$phoneParams->context($entry->Context);
			}
		}
		// addresses
		foreach ($so->PhysicalLocations ?? [] as $id => $entry) {
			$addressParams = $to->addresses((string)$id);
			if ($entry->Box !== null || $entry->Unit !== null || $entry->Street !== null ||
				$entry->Locality !== null || $entry->Region !== null || $entry->Code !== null ||
				$entry->Country !== null) {
				// Build address components
				if ($entry->Box !== null) {
					$component = $addressParams->components();
					$component->kind('pobox');
					$component->value($entry->Box);
				}
				if ($entry->Unit !== null) {
					$component = $addressParams->components();
					$component->kind('unit');
					$component->value($entry->Unit);
				}
				if ($entry->Street !== null) {
					$component = $addressParams->components();
					$component->kind('street');
					$component->value($entry->Street);
				}
				if ($entry->Locality !== null) {
					$component = $addressParams->components();
					$component->kind('locality');
					$component->value($entry->Locality);
				}
				if ($entry->Region !== null) {
					$component = $addressParams->components();
					$component->kind('region');
					$component->value($entry->Region);
				}
				if ($entry->Code !== null) {
					$component = $addressParams->components();
					$component->kind('code');
					$component->value($entry->Code);
				}
				if ($entry->Country !== null) {
					$component = $addressParams->components();
					$component->kind('country');
					$component->value($entry->Country);
				}
			}
			if ($entry->Country !== null) {
				$addressParams->country($entry->Country);
			}
			if ($entry->Coordinates !== null) {
				// Parse coordinates in format "geo:latitude,longitude"
				if (preg_match('/geo:([-\d.]+),([-\d.]+)/', $entry->Coordinates, $matches)) {
					$addressParams->coordinates((float)$matches[1], (float)$matches[2]);
				}
			}
			if ($entry->TimeZone !== null) {
				try {
					$addressParams->timeZone(new DateTimeZone($entry->TimeZone));
				} catch (\Exception $e) {
					// Invalid timezone, skip
				}
			}
		}
		// organizations
		foreach ($so->Organizations ?? [] as $id => $entry) {
			$orgParams = $to->organizations((string)$id);
			if ($entry->Label !== null) {
				$orgParams->name($entry->Label);
			}
			if ($entry->SortName !== null) {
				$orgParams->sorting($entry->SortName);
			}
			foreach ($entry->Units ?? [] as $unit) {
				$unitParams = $orgParams->units();
				$unitParams->name($unit);
			}
		}
		// titles
		foreach ($so->Titles ?? [] as $id => $entry) {
			$titleParams = $to->titles((string)$id);
			if ($entry->Label !== null) {
				$titleParams->name($entry->Label);
			}
			if ($entry->Kind !== null) {
				$titleParams->kind(match ($entry->Kind ?? ContactTitleTypes::Title) {
					ContactTitleTypes::Role => 'role',
					default => 'title',
				});
			}
			if ($entry->Relation !== null) {
				$titleParams->relation($entry->Relation);
			}
		}
		// tags
		if (!empty($so->Tags)) {
			$tags = [];
			foreach ($so->Tags as $tag) {
				if ($tag->Value !== null) {
					$tags[] = $tag->Value;
				}
			}
			if (!empty($tags)) {
				$to->tags(...$tags);
			}
		}
		// notes
		foreach ($so->Notes ?? [] as $id => $entry) {
			$noteParams = $to->notes((string)$id);
			if ($entry->Content !== null) {
				$noteParams->contents($entry->Content);
			}
			if ($entry->Date !== null) {
				$noteParams->created($entry->Date);
			}
		}
		// crypto keys
		foreach ($so->Crypto ?? [] as $id => $entry) {
			$cryptoParams = $to->crypto((string)$id);
			if ($entry->Type !== null) {
				$cryptoParams->kind($entry->Type);
			}
			if ($entry->Data !== null) {
				$cryptoParams->uri($entry->Data);
			}
		}

		return $to;

	}

	public function generateSignature(ContactObject $eo): string {

		// clone self
		$o = clone $eo;
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
