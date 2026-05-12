<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Local;

use DateInterval;
use Datetime;
use DateTimeZone;
use OC\Files\Node\LazyUserFolder;
use OCA\DAV\CalDAV\EventReader;
use OCA\DAVC\Objects\Event\EventAvailabilityTypes;
use OCA\DAVC\Objects\Event\EventCollectionObject;
use OCA\DAVC\Objects\Event\EventLocationPhysicalObject;
use OCA\DAVC\Objects\Event\EventMutationObject;
use OCA\DAVC\Objects\Event\EventNotificationAnchorTypes;
use OCA\DAVC\Objects\Event\EventNotificationObject;
use OCA\DAVC\Objects\Event\EventNotificationPatterns;
use OCA\DAVC\Objects\Event\EventNotificationTypes;
use OCA\DAVC\Objects\Event\EventObject;
use OCA\DAVC\Objects\Event\EventOccurrenceObject;
use OCA\DAVC\Objects\Event\EventOccurrencePrecisionTypes;
use OCA\DAVC\Objects\Event\EventParticipantObject;
use OCA\DAVC\Objects\Event\EventParticipantRoleTypes;
use OCA\DAVC\Objects\Event\EventParticipantStatusTypes;
use OCA\DAVC\Objects\Event\EventParticipantTypes;
use OCA\DAVC\Objects\Event\EventSensitivityTypes;
use OCA\DAVC\Objects\Event\EventTagCollection;
use OCA\DAVC\Objects\OriginTypes;
use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\EventEntity;
use OCA\DAVC\Store\Local\EventStore;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class LocalEventsService {
	private const PRODUCTION_ID = '-//IDN nextcloud.com//JMAP Client//EN';
	private const DATE_FORMAT_UTC = 'Ymd\THis\Z';
	private const DATE_FORMAT_DATE_TIME = 'Ymd\THis';
	private const DATE_FORMAT_DATE_ONLY = 'Ymd';
	protected EventStore $_Store;
	protected ?DateTimeZone $SystemTimeZone = null;
	protected ?DateTimeZone $UserTimeZone = null;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function __construct() {
	}

	public function initialize(EventStore $Store) {

		$this->_Store = $Store;

	}

	/**
	 * retrieve collection from local storage
	 *
	 * @param int $cid Collection ID
	 *
	 * @return EventCollectionObject|null
	 */
	public function collectionFetch(int $cid): ?EventCollectionObject {

		// retrieve collection properties
		$co = $this->_Store->collectionFetch($cid);
		// evaluate if properties where retrieve
		if ($co instanceof CollectionEntity) {
			// construct object and return
			return new EventCollectionObject(
				(string)$co->getId(),
				$co->getLabel(),
				null,
				null,
				$co->getVisible(),
				$co->getColor()
			);
		} else {
			// return nothing
			return null;
		}

	}

	/**
	 * delete collection from local storage
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 *
	 * @return void
	 */
	public function collectionDeleteById(int $cid): void {

		// delete entities from data store
		$this->_Store->entityDeleteByCollection($cid);
		$this->_Store->collectionDeleteById($cid);

	}

	/**
	 * retrieve list of entities from local storage
	 *
	 * @param int $cid collection id
	 *
	 * @return array collection of entities
	 */
	public function entityList(int $cid, string $particulars): array {

		return $this->_Store->entityListByCollection($cid);

	}

	/**
	 * retrieve the differences for specific collection from a specific point from local storage
	 *
	 * @param string $uid user id
	 * @param int $cid collection id
	 * @param string $signature collection signature
	 *
	 * @return array collection of differences
	 */
	public function entityDelta(int $cid, string $signature): array {

		// retrieve collection differences
		$lcc = $this->_Store->chronicleReminisce($cid, $signature);
		// return collection differences
		return $lcc;

	}

	/**
	 * retrieve entity object from local storage
	 *
	 * @param int $id entity id
	 *
	 * @return EventObject|null
	 */
	public function entityFetch(int $id): ?EventObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetch($id);
		// evaluate if entity was retrieved
		if ($eo instanceof EventEntity) {
			return $this->fromEventEntity($eo);
		} else {
			return null;
		}

	}

	/**
	 * retrieve entity by correlation id from local storage
	 *
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
	 *
	 * @return EventObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?EventObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
		if ($eo instanceof EventEntity) {
			return $this->fromEventEntity($eo);
		} else {
			return null;
		}

	}

	/**
	 * create entity in local storage
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param int $cid collection id
	 * @param EventObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, EventObject $so): ?object {

		// convert event object to data store entity
		$eo = $this->toEventEntity(
			$so,
			[
				'Uid' => $uid,
				'Sid' => $sid,
				'Cid' => $cid,
			]
		);
		// create entry in data store
		$eo = $this->_Store->entityCreate($eo);
		// return result
		if ($eo) {
			return (object)['ID' => $eo->getId(), 'Signature' => $eo->getSignature()];
		} else {
			return null;
		}

	}

	/**
	 * modify entity in local storage
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param int $cid collection id
	 * @param int $eid entity id
	 * @param EventObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, EventObject $so): ?object {

		// convert event object to data store entity
		$eo = $this->toEventEntity(
			$so,
			[
				'Id' => $eid,
				'Uid' => $uid,
				'Sid' => $sid,
				'Cid' => $cid,
			]
		);
		// modify entry in data store
		$eo = $this->_Store->entityModify($eo);
		// return result
		if ($eo) {
			return (object)['ID' => $eo->getId(), 'Signature' => $eo->getSignature()];
		} else {
			return null;
		}

	}

	/**
	 * delete entity from local storage
	 *
	 * @param int $eid entity id
	 *
	 * @return bool
	 */
	public function entityDeleteById(int $eid): bool {

		// delete entry from data store
		$rs = $this->_Store->entityDeleteById($eid);
		// return result
		if ($rs) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * delete entity from local storage by remote id
	 *
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
	 *
	 * @return bool
	 */
	public function entityDeleteByCorrelation(int $cid, string $ccid, string $ceid): bool {
		// retrieve entity
		$eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
		if ($eo instanceof EventEntity) {
			// delete entry from data store
			$eo = $this->_Store->entityDelete($eo);
			return true;
		} else {
			return false;
		}

	}

	/**
	 * convert store entity to event object
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventEntity $so
	 * @param array<string,mixed>
	 *
	 * @return EventObject
	 */
	public function fromEventEntity(EventEntity $so, array $additional = []): EventObject {

		// prase vData
		$vObject = Reader::read($so->getData());
		// convert entity
		$to = $this->toEventObject($vObject);
		$to->ID = (string)$so->getId();
		$to->CID = (string)$so->getCid();
		$to->Signature = $so->getSignature();
		$to->CCID = $so->getCcid();
		$to->CEID = $so->getCeid();
		$to->CESN = $so->getCesn();
		$to->UUID = $so->getUuid();
		// override / assign additional values
		foreach ($additional as $label => $value) {
			if (isset($to->$label)) {
				$to->$label = $value;
			}
		}

		return $to;
	}

	/**
	 * convert event object to store entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject $so
	 * @param array<string,mixed>
	 *
	 * @return EventEntity
	 */
	public function toEventEntity(EventObject $so, array $additional = []): EventEntity {

		// construct entity
		$to = new EventEntity();
		$vo = $this->fromEventObject($so);
		// convert source object to entity
		$to->setData($vo->serialize());
		$to->setUuid($so->UUID);
		$to->setSignature($this->generateSignature($so));
		$to->setCcid($so->CCID);
		$to->setCeid($so->CEID);
		$to->setCesn($so->CESN);
		$to->setLabel($so->Label);
		$to->setDescription($so->Description);
		$to->setStartson($so->StartsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
		if ($so->OccurrencePattern !== null) {
			$eventReader = new EventReader($vo, $so->UUID);
			if ($eventReader->recurringConcludes()) {
				$to->setEndson($eventReader->recurringConcludesOn()->setTimezone(new DateTimeZone('UTC'))->format('U'));
			} else {
				$to->setEndson(2147483647);
			}
		} else {
			$to->setEndson($so->EndsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
		}

		// override / assign additional values
		foreach ($additional as $key => $value) {
			$method = 'set' . ucfirst($key);
			$to->$method($value);
		}

		return $to;
	}

	/**
	 * convert vevent object to event object
	 *
	 * @since Release 1.0.0
	 *
	 * @param VCalendar $so
	 *
	 * @return EventObject
	 */
	public function toEventObject(VCalendar $so): EventObject {

		// construct target object
		$do = new EventObject();
		// Origin
		$do->Origin = OriginTypes::Internal;
		// universal id
		if (isset($so->UID)) {
			$do->UUID = trim($so->UID->getValue());
		}

		foreach ($so->getComponents() as $vComponent) {
			if ($vComponent->name !== 'VEVENT') {
				continue;
			}
			if ($vComponent->{'RECURRENCE-ID'}) {
				$id = $vComponent->{'RECURRENCE-ID'}->getDateTime();
				$instance = new EventMutationObject();
				$instance->mutationId = $id;
				$this->toEventInstanceObject($vComponent, $instance, $so->VEVENT);
				$do->OccurrenceMutations[$id->format('Y-m-d\TH:i:s')] = $instance;
			} else {
				$do = $this->toEventInstanceObject($vComponent, $do);
			}
		}

		// return event object
		return $do;

	}

	/**
	 * convert vevent base/mutation object to event base/mutation object
	 *
	 * @since Release 1.0.0
	 *
	 * @param VEvent $so source object (base or mutation)
	 * @param EventObject|EventMutationObject $do destination object (base or mutation)
	 * @param VEvent|null $bo source base object (for mutations)
	 *
	 * @return EventObject|EventMutationObject
	 */
	public function toEventInstanceObject(VEvent $so, EventObject|EventMutationObject $do, VEvent|null $bo = null): EventObject|EventMutationObject {

		// creation date time
		if (isset($so->CREATED)) {
			$do->CreatedOn = $so->CREATED->getDateTime();
		} elseif (isset($so->DTSTAMP)) {
			$do->CreatedOn = $so->DTSTAMP->getDateTime();
		}
		// modification date time
		if (isset($so->{'LAST-MODIFIED'})) {
			$do->ModifiedOn = $so->{'LAST-MODIFIED'}->getDateTime();
		}
		// sequence
		if (isset($so->SEQUENCE)) {
			$do->Sequence = (int)$so->SEQUENCE->getValue();
		}
		// time zone
		if (isset($so->{'X-TIMEZONE'})) {
			$do->TimeZone = new DateTimeZone($so->{'X-TIMEZONE'}->getValue());
		}
		// Starts Date/Time
		// Starts Time Zone
		if (isset($so->DTSTART)) {
			$do->StartsOn = $so->DTSTART->getDateTime();
			$do->StartsTZ = $do->StartsOn->getTimezone();
		}
		// Ends Date/Time
		// Ends Time Zone
		if (isset($so->DTEND)) {
			$do->EndsOn = $so->DTEND->getDateTime();
			$do->EndsTZ = $do->EndsOn->getTimezone();
		}
		// duration
		if (isset($so->DURATION)) {
			$do->Duration = $so->DURATION->getDateInterval();
		}
		// label
		if (isset($so->SUMMARY)) {
			$do->Label = trim($so->SUMMARY->getValue());
		}
		// description
		if (isset($so->DESCRIPTION)) {
			if (!empty(trim($so->DESCRIPTION->getValue()))) {
				$do->Description = trim($so->DESCRIPTION->getValue());
			}
		}
		// location
		if (isset($so->LOCATION)) {
			foreach ($so->LOCATION as $id => $entry) {
				$parameters = $entry->parameters();
				$entity = new EventLocationPhysicalObject();
				$entity->Id = $parameters['X-ID']?->getValue();
				$entity->Name = trim($entry->getValue());
				//$location->Description = $entry->description();
				$do->LocationsPhysical[$entity->Id] = $entity;
			}
		}
		// availability
		if (isset($so->TRANSP)) {
			$do->Availability = match (strtoupper($so->TRANSP?->getValue() ?? 'default')) {
				'FREE' => EventAvailabilityTypes::Free,
				default => EventAvailabilityTypes::Busy,
			};
		}
		// priority
		if (isset($so->PRIORITY)) {
			$do->Priority = (int)$so->PRIORITY->getValue();
		}
		// sensitivity
		if (isset($so->CLASS)) {
			$do->Sensitivity = match (strtoupper($so->CLASS?->getValue() ?? 'default')) {
				'PRIVATE' => EventSensitivityTypes::Private,
				'CONFIDENTIAL' => EventSensitivityTypes::Secret,
				default => EventSensitivityTypes::Public,
			};
		}
		// color
		if (isset($so->COLOR)) {
			$do->Color = trim($so->COLOR->getValue());
		}
		// tag(s)
		if (isset($so->CATEGORIES)) {
			$do->Tags = new EventTagCollection($so->CATEGORIES->getParts());
		}
		// participant(s)
		foreach (['ORGANIZER', 'ATTENDEE'] as $name) {
			if (isset($so->$name)) {
				foreach ($so->$name as $entry) {
					$parameters = $entry->parameters();
					$entity = new EventParticipantObject();
					$entity->Address = !empty($entry->getValue()) ? trim(str_replace('mailto:', '', $entry->getValue())) : null;
					$entity->Id = $parameters['X-ID']?->getValue();
					$entity->Name = $parameters['CN']?->getValue() ?? null;
					$entity->Type = match (strtoupper($parameters['CUTYPE']?->getValue() ?? 'default')) {
						'GROUP' => EventParticipantTypes::Group,
						'RESOURCE' => EventParticipantTypes::Resource,
						'ROOM' => EventParticipantTypes::Location,
						default => EventParticipantTypes::Individual,
					};
					$entity->Status = match (strtoupper($parameters['PARTSTAT']?->getValue() ?? 'default')) {
						'ACCEPTED' => EventParticipantStatusTypes::Accepted,
						'DECLINED' => EventParticipantStatusTypes::Declined,
						'TENTATIVE' => EventParticipantStatusTypes::Tentative,
						default => ($name === 'ORGANIZER') ? EventParticipantStatusTypes::Accepted : EventParticipantStatusTypes::None,
					};
					$entity->Roles[] = match (strtoupper($parameters['ROLE']?->getValue() ?? 'default')) {
						'CHAIR' => EventParticipantRoleTypes::Chair,
						'OPT-PARTICIPANT' => EventParticipantRoleTypes::Optional,
						'NON-PARTICIPANT' => EventParticipantRoleTypes::Informational,
						default => ($name === 'ORGANIZER') ? EventParticipantRoleTypes::Owner : EventParticipantRoleTypes::Attendee,
					};
					$do->Participants[$entity->Id] = $entity;
				}
			}
		}
		// notifications
		if (isset($so->VALARM)) {
			foreach ($so->VALARM as $entry) {
				$entity = new EventNotificationObject();
				$entity->Id = $entry->{'X-ID'}->getValue();
				$entity->Type = match (strtoupper($entry->ACTION?->getValue() ?? 'default')) {
					'EMAIL' => EventNotificationTypes::Email,
					'AUDIO' => EventNotificationTypes::Audible,
					default => EventNotificationTypes::Visual,
				};
				if (isset($entry->TRIGGER?->parameters()['VALUE'])) {
					$entity->Pattern = EventNotificationPatterns::Absolute;
					$entity->When = new DateTime($entry->TRIGGER->parameters()['VALUE']->getValue());
				}
				if (isset($entry->TRIGGER?->parameters()['RELATED'])) {
					$entity->Pattern = EventNotificationPatterns::Relative;
					$entity->Anchor = match (strtoupper($entry->TRIGGER->parameters()['RELATED']->getValue())) {
						'END' => EventNotificationAnchorTypes::End,
						default => EventNotificationAnchorTypes::Start,
					};
					$entity->Offset = $entry->TRIGGER->getDateInterval();
				}
				$do->Notifications[$entity->Id] = $entity;
			}
		}
		// occurrence
		if (isset($so->RRULE)) {
			$entity = new EventOccurrenceObject();
			$parts = $so->RRULE->getParts();
			if (isset($parts['FREQ'])) {
				$entity->Precision = match (strtoupper($parts['FREQ'])) {
					'YEARLY' => EventOccurrencePrecisionTypes::Yearly,
					'MONTHLY' => EventOccurrencePrecisionTypes::Monthly,
					'WEEKLY' => EventOccurrencePrecisionTypes::Weekly,
					'DAILY' => EventOccurrencePrecisionTypes::Daily,
					'HOURLY' => EventOccurrencePrecisionTypes::Hourly,
					'MINUTELY' => EventOccurrencePrecisionTypes::Minutely,
					'SECONDLY' => EventOccurrencePrecisionTypes::Secondly,
				};
			}
			if (isset($parts['INTERVAL'])) {
				$entity->Interval = (int)$parts['INTERVAL'];
			}
			if (isset($parts['COUNT'])) {
				$entity->Iterations = (int)$parts['COUNT'];
			}
			if (isset($parts['UNTIL'])) {
				$entity->Concludes = new DateTime($parts['UNTIL']);
			}
			if (isset($parts['BYDAY'])) {
				if (is_array($parts['BYDAY'])) {
					$entity->OnDayOfWeek = $parts['BYDAY'];
				} else {
					$entity->OnDayOfWeek = [$parts['BYDAY']];
				}
			}
			if (isset($parts['BYMONTH'])) {
				if (is_array($parts['BYMONTH'])) {
					$entity->OnMonthOfYear = $this->convertToInt($parts['BYMONTH']);
				} else {
					$entity->OnMonthOfYear = $this->convertToInt([$parts['BYMONTH']]);
				}
			}
			if (isset($parts['BYMONTHDAY'])) {
				if (is_array($parts['BYMONTHDAY'])) {
					$entity->OnDayOfMonth = $this->convertToInt($parts['BYMONTHDAY']);
				} else {
					$entity->OnDayOfMonth = $this->convertToInt([$parts['BYMONTHDAY']]);
				}
			}
			if (isset($parts['BYYEARDAY'])) {
				if (is_array($parts['BYYEARDAY'])) {
					$entity->OnDayOfYear = $this->convertToInt($parts['BYYEARDAY']);
				} else {
					$entity->OnDayOfYear = $this->convertToInt([$parts['BYYEARDAY']]);
				}
			}
			if (isset($parts['BYSETPOS'])) {
				if (is_array($parts['BYSETPOS'])) {
					$entity->OnPosition = $this->convertToInt($parts['BYSETPOS']);
				} else {
					$entity->OnPosition = $this->convertToInt([$parts['BYSETPOS']]);
				}
			}
			$do->OccurrencePattern = $entity;
		}
		// Attachment(s)
		/*
		if (isset($so->ATTACH)) {
			foreach($so->ATTACH as $entry) {
				if (isset($parameters['X-NC-FILE-ID'])) {
					$fs = 'D';
					$fi = $parameters['X-NC-FILE-ID']->getValue();
					$fn = $parameters['FILENAME']->getValue();
					$ft = $parameters['FMTTYPE']->getValue();
					$fd = $parameters['FILENAME']->getValue();

					$do->addAttachment(
						$fs,
						$fi,
						$fn,
						$ft,
						'B',
						null,
						$fd
					);
				}
			}
		}
		*/

		return $do;

	}

	/**
	 * convert event object to vevent object
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject $so
	 *
	 * @return VCalendar
	 */
	public function fromEventObject(EventObject $so): VCalendar {
		// construct target object
		$do = new VCalendar();
		// base instance
		/** @var VEvent $vComponent */
		$vComponent = $do->add('VEVENT');
		// universal id
		if ($vComponent->UID) {
			$vComponent->UID->setValue($so->UUID);
		} else {
			$vComponent->add('UID', $so->UUID);
		}
		// product id
		if ($vComponent->PRODID) {
			$vComponent->PRODID->setValue(self::PRODUCTION_ID);
		} else {
			$vComponent->add('PRODID', self::PRODUCTION_ID);
		}
		// common properties
		$this->fromEventInstanceObject($so, $vComponent);
		// mutated instances
		foreach ($so->OccurrenceMutations as $id => $mutation) {
			// Exclusion Mutations
			if ($mutation->mutationExclusion === true) {
				/** @var VEvent $vComponent */
				$vComponent = $do->add('VEVENT');
				$vComponent->add('EXDATE', $mutation->mutationId);
				if ($mutation->mutationTz) {
					$vComponent->{'EXDATE'}->add('TZID', $mutation->mutationTz);
				}
				continue;
			}

			// Standard Mutations
			/** @var VEvent $vComponent */
			$vComponent = $do->add('VEVENT');
			// universal id
			if ($vComponent->UID) {
				$vComponent->UID->setValue($so->UUID);
			} else {
				$vComponent->add('UID', $so->UUID);
			}
			// recurrence id
			if (isset($mutation->mutationTz)) {
				$mutationTz = $mutation->mutationTz;
			} elseif ($mutation->mutationId->getTimezone()->getName() !== 'UTC') {
				$mutationTz = $mutation->mutationId->getTimezone()->getName();
			} elseif ($so->StartsTZ) {
				$mutationTz = $so->StartsTZ->getName();
			} else {
				$mutationTz = null;
			}
			if ($mutationTz) {
				$mutationId = $mutation->mutationId->format(self::DATE_FORMAT_DATE_TIME);
			} else {
				$mutationId = $mutation->mutationId->format(self::DATE_FORMAT_UTC);
			}
			if ($vComponent->{'RECURRENCE-ID'}) {
				$vComponent->{'RECURRENCE-ID'}->setValue($mutationId);
			} else {
				$vComponent->add('RECURRENCE-ID', $mutationId);
			}
			if ($mutationTz) {
				$vComponent->{'RECURRENCE-ID'}->add('TZID', $mutationTz);
			}
			// common properties
			$this->fromEventInstanceObject($mutation, $vComponent, $so);
		}

		return $do;
	}

	/**
	 * convert event base/mutation object to vevent base/mutation object
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject|EventMutationObject $so source object (base or mutation)
	 * @param VEvent $do destination object (base or mutation)
	 * @param EventObject|null $bo source base object (for mutations)
	 *
	 * @return VEvent
	 */
	public function fromEventInstanceObject(EventObject|EventMutationObject $so, VEvent $do, EventObject|null $bo = null): VEvent {
		// creation date
		if ($so->CreatedOn !== null) {
			$do->add('CREATED', $so->CreatedOn);
			if ($do->DTSTAMP) {
				$do->DTSTAMP->setValue($so->CreatedOn);
			} else {
				$do->add('DTSTAMP', $so->CreatedOn);
			}
		}
		// modification date
		if ($so->ModifiedOn !== null) {
			$do->add('LAST-MODIFIED', $so->ModifiedOn);
		}
		// sequence
		if ($so->Sequence !== null) {
			$do->add('SEQUENCE', $so->Sequence);
		} else {
			$do->add('SEQUENCE', 0);
		}
		// time zone
		if ($so->TimeZone !== null) {
			$do->add('X-TIMEZONE', $so->TimeZone->getName());
		}
		// Starts Date, Time and Zone
		if ($so->StartsOn !== null) {
			if (isset($so->Timeless)) {
				$do->add('DTSTART', $so->StartsOn);
			} else {
				$do->add('DTSTART', $so->StartsOn);
			}
			if ($so->StartsTZ !== null) {
				$do->DTSTART->add('TZID', $so->StartsTZ->getName());
			} elseif ($so->TimeZone !== null) {
				$do->DTSTART->add('TZID', $so->TimeZone->getName());
			} elseif ($bo !== null && $bo->StartsTZ !== null) {
				$do->DTSTART->add('TZID', $bo->StartsTZ->getName());
			}
		} elseif ($so->mutationId !== null) {
			$do->add('DTSTART', $so->mutationId);
			if ($so->mutationTz !== null) {
				$do->DTSTART->add('TZID', $so->mutationTz);
			} elseif ($bo !== null && $bo->StartsTZ !== null) {
				$do->DTSTART->add('TZID', $bo->StartsTZ->getName());
			}
		}
		// End Date, Time and Zone
		if ($so->EndsOn !== null) {
			if (isset($so->Timeless)) {
				$do->add('DTEND', $so->EndsOn);
			} else {
				$do->add('DTEND', $so->EndsOn);
			}
			if ($so->EndsTZ !== null) {
				$do->DTEND->add('TZID', $so->EndsTZ->getName());
			} elseif ($so->TimeZone !== null) {
				$do->DTEND->add('TZID', $so->TimeZone->getName());
			}
		}
		// Duration
		if ($so->Duration !== null && $so->EndsOn === null) {
			$do->add('DURATION',$so->Duration);
		}
		// Label
		if ($so->Label !== null) {
			$do->add('SUMMARY', $so->Label);
		}
		// Notes
		if ($so->Description !== null) {
			$do->add('DESCRIPTION', $so->Description);
		}
		// Physical Location(s)
		foreach ($so->LocationsPhysical as $entry) {
			/** @var Property $entity */
			$entity = $do->add('LOCATION', trim($entry->Name));
			$entity->add('X-ID', $entry->Id);
		}
		// Availability
		if ($so->Availability !== null) {
			$do->add('TRANSP', match ($so->Availability) {
				EventAvailabilityTypes::Free => 'TRANSPARENT',
				default => 'OPAQUE',
			});
		}
		// Priority
		if ($so->Priority !== null) {
			$do->add('PRIORITY', $so->Priority);
		}
		// Sensitivity
		if ($so->Sensitivity !== null) {
			$do->add('CLASS', match ($so->Sensitivity) {
				EventSensitivityTypes::Private => 'PRIVATE',
				EventSensitivityTypes::Secret => 'CONFIDENTIAL',
				default => 'PUBLIC',
			});
		}
		// Color
		if ($so->Color !== null) {
			$do->add('COLOR', trim($so->Color));
		}
		// Tag(s)
		if ($so->Tags->count() > 0) {
			$do->add('CATEGORIES', implode(', ', (array)$so->Tags));
		}
		// Participant(s)
		foreach ($so->Participants as $entry) {
			if (in_array(EventParticipantRoleTypes::Owner, iterator_to_array($entry->Roles), true)) {
				$entity = $do->add('ORGANIZER', 'mailto:' . $entry->Address);
			} else {
				$entity = $do->add('ATTENDEE', 'mailto:' . $entry->address);
			}
			/** @var Property $entity */
			// Participant Type
			$entity->add('CUTYPE', match ($entry->Type ?? 'default') {
				EventParticipantTypes::Individual => 'INDIVIDUAL',
				EventParticipantTypes::Group => 'GROUP',
				EventParticipantTypes::Resource => 'RESOURCE',
				EventParticipantTypes::Location => 'ROOM',
				default => 'UNKNOWN',
			});
			// Participant Status
			$entity->add('PARTSTAT', match ($entry->Status ?? 'default') {
				EventParticipantStatusTypes::Accepted => 'ACCEPTED',
				EventParticipantStatusTypes::Declined => 'DECLINED',
				EventParticipantStatusTypes::Tentative => 'TENTATIVE',
				EventParticipantStatusTypes::Delegated => 'DELEGATED',
				default => 'NEEDS-ACTION',
			});
			// Participant Identifier
			if ($entry->Id !== null) {
				$entity->add('X-ID', $entry->Id);
			}
			// Participant Name
			if ($entry->Name !== null) {
				$entity->add('CN', $entry->Name);
			}
			// Participant Language
			if ($entry->Language !== null) {
				$entity->add('LANGUAGE', $entry->Language);
			}
			// Participant Roles
			if ($entry->Roles->count() > 0) {
				$entity->add('ROLE', match ($entry->Roles[0]) {
					EventParticipantRoleTypes::Chair => 'CHAIR',
					EventParticipantRoleTypes::Optional => 'OPT-PARTICIPANT',
					EventParticipantRoleTypes::Informational => 'NON-PARTICIPANT',
					default => 'REQ-PARTICIPANT',
				});
			}
		}
		// Notifications
		foreach ($so->Notifications as $entry) {
			$entity = $do->add('VALARM');
			/** @var Property $entity */
			$entity->add('X-ID', $entry->Id);
			// Notifications Type
			$entity->add('ACTION', match ($entry->Type ?? 'default') {
				EventNotificationTypes::Email => 'EMAIL',
				default => 'DISPLAY',
			});
			// Notifications Pattern
			switch ($entry->Pattern) {
				case EventNotificationPatterns::Absolute:
					$entity->add('VALUE', $entry->When, []);
					break;
				case EventNotificationPatterns::Relative:
					$entity->add(
						'TRIGGER',
						$this->toDurationPeriod($entry->Offset),
						['RELATED' => ($entry->Anchor === EventNotificationAnchorTypes::End) ? 'END' : 'START']
					);
					break;
			}
		}
		// Occurrence
		if ($so->OccurrencePattern !== null) {
			$soRule = $so->OccurrencePattern;
			$doRule = [];
			// Occurrence Precision
			$doRule['FREQ'] = match ($soRule->Precision) {
				EventOccurrencePrecisionTypes::Yearly => 'YEARLY',
				EventOccurrencePrecisionTypes::Monthly => 'MONTHLY',
				EventOccurrencePrecisionTypes::Weekly => 'WEEKLY',
				EventOccurrencePrecisionTypes::Daily => 'DAILY',
				EventOccurrencePrecisionTypes::Hourly => 'HOURLY',
				EventOccurrencePrecisionTypes::Minutely => 'MINUTELY',
				EventOccurrencePrecisionTypes::Secondly => 'SECONDLY',
			};
			// Occurrence Interval
			if ($soRule->Interval !== null) {
				$doRule['INTERVAL'] = $soRule->Interval;
			}
			// Occurrence Iterations
			if ($soRule->Iterations !== null) {
				$doRule['COUNT'] = $soRule->Iterations;
			}
			// Occurrence Conclusion
			if ($soRule->Concludes !== null) {
				$doRule['UNTIL'] = $soRule->Concludes->format(self::DATE_FORMAT_UTC);
			}
			// Occurrence Day Of Week
			if (count($soRule->OnDayOfWeek) > 0) {
				$doRule['BYDAY'] = implode(',', $soRule->OnDayOfWeek);
			}
			// Occurrence Day Of Month
			if (count($soRule->OnDayOfMonth) > 0) {
				$doRule['BYMONTHDAY'] = implode(',', $soRule->OnDayOfMonth);
			}
			// Occurrence Day Of Year
			if (count($soRule->OnDayOfYear) > 0) {
				$doRule['BYYEARDAY'] = implode(',', $soRule->OnDayOfYear);
			}
			// Occurrence Week Of Year
			if (count($soRule->OnWeekOfYear) > 0) {
				$doRule['BYWEEKNO'] = implode(',', $soRule->OnWeekOfYear);
			}
			// Occurrence Month Of Year
			if (count($soRule->OnMonthOfYear) > 0) {
				$doRule['BYMONTH'] = implode(',', $soRule->OnMonthOfYear);
			}
			// Occurrence Position
			if (count($soRule->OnPosition) > 0) {
				$doRule['BYSETPOS'] = implode(',', $soRule->OnPosition);
			}

			$do->add('RRULE', $doRule);
		}

		// Attachment(s)
		/*
		if (count($so->Attachments) > 0) {
			foreach($so->Attachments as $entry) {
				// Data Store
				if ($entry->Store == 'D' && !empty($entry->Id)) {
					$p = array();
					$p['X-NC-FILE-ID'] = $entry->Id;
					$p['FILENAME'] = $entry->Data;
					$p['FMTTYPE'] = $entry->Type;
					$do->add('ATTACH', "/f/" . $entry->Id, $p);
					unset($p);
				}
				// Referance
				elseif ($entry->Store == 'R' && !empty($entry->Data)) {
					$p = array();
					$p['FMTTYPE'] = $entry->Type;
					$do->add('ATTACH', $entry->Data, $p);
					unset($p);
				}
				// Enclosed
				elseif (!empty($entry->Data)) {
					$p = array();
					$p['FMTTYPE'] = $entry->Type;
					$p['ENCODING'] = 'BASE64';
					$p['VALUE'] = 'BINARY';
					unset($p);
					if ($entry->Encoding == 'B64') {
						$do->add(
							'ATTACH',
							'X-FILENAME="' . $entry->Name . '":' . $entry->Data,
							$p
						);
					}
					else {
						$do->add(
							'ATTACH',
							'X-FILENAME="' . $entry->Name . '":' .  base64_encode($entry->Data),
							$p
						);
					}
				}

			}
		}
		*/

		return $do;
	}

	/**
	 * generate event object signature
	 *
	 * @since Release 1.0.0
	 *
	 * @param EventObject $to target object
	 *
	 * @return string
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
	 * convert event object date interval to local duration period
	 *
	 * @since Release 1.0.0
	 *
	 * @param DateInterval $period
	 *
	 * @return string
	 */
	private function toDurationPeriod(DateInterval $period): string {

		return match (true) {
			($period->y > 0) => $period->format('%rP%yY%mM%dDT%hH%iM'),
			($period->m > 0) => $period->format('%rP%mM%dDT%hH%iM'),
			($period->d > 0) => $period->format('%rP%dDT%hH%iM'),
			($period->h > 0) => $period->format('%rPT%hH%iM'),
			default => $period->format('%rPT%iM')
		};

	}

	private function convertToInt(array $values): array {

		foreach ($values as $key => $value) {
			$values[$key] = (int)$value;
		}

		return $values;

	}

}
