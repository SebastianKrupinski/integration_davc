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

namespace OCA\JMAPC\Service\Local;

use DateTimeImmutable;
use DateTimeZone;
use OC\Files\Node\LazyUserFolder;
use OCA\JMAPC\Objects\Contact\ContactAliasObject;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryObject;
use OCA\JMAPC\Objects\Contact\ContactAnniversaryTypes;
use OCA\JMAPC\Objects\Contact\ContactCollectionObject;
use OCA\JMAPC\Objects\Contact\ContactCryptoObject;
use OCA\JMAPC\Objects\Contact\ContactEmailObject;
use OCA\JMAPC\Objects\Contact\ContactNoteObject;
use OCA\JMAPC\Objects\Contact\ContactObject;
use OCA\JMAPC\Objects\Contact\ContactOrganizationObject;
use OCA\JMAPC\Objects\Contact\ContactPhoneObject;
use OCA\JMAPC\Objects\Contact\ContactPhysicalLocationObject;
use OCA\JMAPC\Objects\Contact\ContactPronounObject;
use OCA\JMAPC\Objects\Contact\ContactTagCollection;
use OCA\JMAPC\Objects\Contact\ContactTitleObject;
use OCA\JMAPC\Objects\Contact\ContactTitleTypes;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Store\Local\CollectionEntity;
use OCA\JMAPC\Store\Local\ContactEntity;
use OCA\JMAPC\Store\Local\ContactStore;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class LocalContactsService {
	private const DATE_FORMAT_UTC = 'Ymd\THis\Z';
	private const DATE_FORMAT_DATE_TIME = 'Ymd\THis';
	private const DATE_FORMAT_DATE_ONLY = 'Ymd';
	protected ContactStore $_Store;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $_BlobStore = null;

	public function __construct() {
	}

	public function initialize(ContactStore $Store) {

		$this->_Store = $Store;

	}

	/**
	 * retrieve collection from local storage
	 *
	 * @param int $cid Collection ID
	 *
	 * @return ContactCollection|null
	 */
	public function collectionFetch(int $cid): ?ContactCollectionObject {

		// retrieve collection properties
		$co = $this->_Store->collectionFetch($cid);
		// evaluate if properties where retrieve
		if ($co instanceof CollectionEntity) {
			// construct object and return
			return new ContactCollectionObject(
				(string)$co->getId(),
				$co->getLabel(),
				null,
				null
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
	 * @return ContactObject|null
	 */
	public function entityFetch(int $id): ?ContactObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetch($id);
		// evaluate if entity was retrieved
		if ($eo instanceof ContactEntity) {
			return $this->fromContactEntity($eo);
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
	 * @return ContactObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?ContactObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
		if ($eo instanceof ContactEntity) {
			return $this->fromContactEntity($eo);
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
	 * @param ContactObject $so source object
	 *
	 * @return ContactObject Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, ContactObject $so): ?ContactObject {

		// convert event object to data store entity
		$eo = $this->toContactEntity(
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
			$ro = clone $so;
			$ro->ID = (string)$eo->getId();
			$ro->CID = (string)$eo->getCid();
			$ro->Signature = $eo->getSignature();
			return $ro;
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
	 * @param ContactObject $so source object
	 *
	 * @return ContactObject Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, ContactObject $so): ?ContactObject {

		// convert event object to data store entity
		$eo = $this->toContactEntity(
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
			$ro = clone $so;
			$ro->ID = (string)$eo->getId();
			$ro->CID = (string)$eo->getCid();
			$ro->Signature = $eo->getSignature();
			return $ro;
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
		if ($eo instanceof ContactEntity) {
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
	 * @param ContactEntity $so
	 * @param array<string,mixed>
	 *
	 * @return ContactObject
	 */
	public function fromContactEntity(ContactEntity $so, array $additional = []): ContactObject {

		// prase vData
		$vObject = Reader::read($so->getData());
		// convert entity
		$to = $this->toContactObject($vObject);
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
	 * @param ContactObject $so
	 * @param array<string,mixed>
	 *
	 * @return ContactEntity
	 */
	public function toContactEntity(ContactObject $so, array $additional = []): ContactEntity {

		// construct entity
		$to = new ContactEntity();
		// convert source object to entity
		$to->setData($this->fromContactObject($so)->serialize());
		$to->setUuid($so->UUID);
		$to->setSignature($this->generateSignature($so));
		$to->setCcid($so->CCID);
		$to->setCeid($so->CEID);
		$to->setCesn($so->CESN);
		$to->setLabel($so->Label);
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
	 * @param VCard $so
	 *
	 * @return ContactObject
	 */
	public function toContactObject(VCard $so): ContactObject {

		// construct target object
		$do = new ContactObject();
		// Origin
		$do->Origin = OriginTypes::Internal;
		// universal id
		if (isset($so->UID)) {
			$do->UUID = trim($so->UID->getValue());
		}
		// creation date time
		if (isset($so->CREATED)) {
			$do->CreatedOn = $so->CREATED->getDateTime();
		}
		// modification date time
		if (isset($so->REV)) {
			$do->ModifiedOn = DateTimeImmutable::createFromFormat(self::DATE_FORMAT_UTC, $so->REV->getValue());
		}
		// language
		if (isset($so->LANGUAGE)) {
			$do->Language = $this->sanitizeString($so->LANGUAGE->getValue());
		}
		// time zone
		if (isset($so->TZ)) {
			$do->TimeZone = new DateTimeZone($so->TZ->getValue());
		}
		// label
		if (isset($so->FN)) {
			$do->Label = $this->sanitizeString($so->FN->getValue());
		}
		// name
		if (isset($so->N)) {
			$p = $so->N->getParts();
			$do->Name->Last = $this->sanitizeString($p[0]);
			$do->Name->First = $this->sanitizeString($p[1]);
			$do->Name->Other = $this->sanitizeString($p[2]);
			$do->Name->Prefix = $this->sanitizeString($p[3]);
			$do->Name->Suffix = $this->sanitizeString($p[4]);
			$do->Name->PhoneticLast = $this->sanitizeString($p[6]);
			$do->Name->PhoneticFirst = $this->sanitizeString($p[7]);
			unset($p);
		}
		// aliases
		if (isset($so->NICKNAME)) {
			foreach ($so->NICKNAME as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactAliasObject();
				$entity->Label = $this->sanitizeString($entry->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->Language = $this->sanitizeString($parameters['LANGUAGE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Name->Aliases[] = $entity;
			}
		}
		// birth day
		if (isset($so->BDAY)) {
			$entity = new ContactAnniversaryObject();
			$entity->Type = ContactAnniversaryTypes::Birth;
			$entity->When = $so->BDAY->getDatetime();
			$do->Anniversaries[ContactAnniversaryTypes::Birth->value] = $entity;
		}
		// death day
		if (isset($so->DEATHDAY)) {
			$entity = new ContactAnniversaryObject();
			$entity->Type = ContactAnniversaryTypes::Death;
			$entity->When = $so->DEATHDAY->getDatetime();
			$do->Anniversaries[ContactAnniversaryTypes::Death->value] = $entity;
		}
		// nuptial day
		if (isset($so->ANNIVERSARY)) {
			$entity = new ContactAnniversaryObject();
			$entity->Type = ContactAnniversaryTypes::Nuptial;
			$entity->When = $so->ANNIVERSARY->getDatetime();
			$do->Anniversaries[ContactAnniversaryTypes::Nuptial->value] = $entity;
		}
		// birth place
		if (isset($so->BIRTHPLACE)) {
			if (isset($do->Anniversaries[ContactAnniversaryTypes::Birth->value])) {
				$do->Anniversaries[ContactAnniversaryTypes::Birth->value] = new ContactAnniversaryObject();
			}
			$do->Anniversaries[ContactAnniversaryTypes::Birth->value]->Location = $this->sanitizeString($so->BIRTHPLACE->getValue());
		}
		// death place
		if (isset($so->DEATHPLACE)) {
			if (isset($do->Anniversaries[ContactAnniversaryTypes::Death->value])) {
				$do->Anniversaries[ContactAnniversaryTypes::Death->value] = new ContactAnniversaryObject();
			}
			$do->Anniversaries[ContactAnniversaryTypes::Death->value]->Location = $this->sanitizeString($so->DEATHPLACE->getValue());
		}
		// pronouns
		if (isset($so->PRONOUNS)) {
			foreach ($so->PRONOUNS as $index => $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactPronounObject();
				$entity->Pronoun = $this->sanitizeString($entry->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->Language = $this->sanitizeString($parameters['LANGUAGE']?->getValue());
				$do->Pronouns[] = $entity;
			}
		}
		// phone(s)
		if (isset($so->TEL)) {
			foreach ($so->TEL as $index => $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactPhoneObject();
				$entity->Number = $this->sanitizeString($entry->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Phone[] = $entity;
				unset($primary, $secondary);
			}
		}
		// email(s)
		if (isset($so->EMAIL)) {
			foreach ($so->EMAIL as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactEmailObject();
				$entity->Address = $this->sanitizeString($entry->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Email[] = $entity;
			}
		}
		// physical location(s)
		if (isset($so->ADR)) {
			foreach ($so->ADR as $index => $entry) {
				[$pob, $unit, $street, $locality, $region, $code, $country] = $entry->getParts();
				$parameters = $entry->parameters();
				$entity = new ContactPhysicalLocationObject();
				$entity->Box = $this->sanitizeString($pob);
				$entity->Unit = $this->sanitizeString($unit);
				$entity->Street = $this->sanitizeString($street);
				$entity->Locality = $this->sanitizeString($locality);
				$entity->Region = $this->sanitizeString($region);
				$entity->Code = $this->sanitizeString($code);
				$entity->Country = $this->sanitizeString($country);
				$entity->Label = $this->sanitizeString($parameters['LABEL']?->getValue());
				$entity->Coordinates = $this->sanitizeString($parameters['GEO']?->getValue());
				$entity->TimeZone = $this->sanitizeString($parameters['TZ']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->Language = $this->sanitizeString($parameters['LANGUAGE']?->getValue());
				$do->PhysicalLocations[] = $entity;
				unset($type, $pob, $unit, $street, $locality, $region, $code, $country);
			}
		}
		// organization(s)
		if (isset($so->ORG)) {
			foreach ($so->ORG as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactOrganizationObject();
				$entity->Label = $this->sanitizeString($entry->getValue());
				$parts = $entry->getParts();
				if (isset($parts[1])) {
					$entity->Units[1] = $this->sanitizeString($parts[1]);
				}
				if (isset($parts[2])) {
					$entity->Units[2] = $this->sanitizeString($parts[2]);
				}
				$entity->SortName = $this->sanitizeString($parameters['SORT-AS']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->Language = $this->sanitizeString($parameters['LANGUAGE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Organizations[] = $entity;
			}
		}
		// title(s)
		if (isset($so->TITLE)) {
			foreach ($so->TITLE as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactTitleObject();
				$entity->Kind = ContactTitleTypes::Title;
				$entity->Label = $this->sanitizeString($entry->getValue());
				$entity->Relation = $this->sanitizeString($parameters['X-ORG-ID']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Titles[] = $entity;
			}
		}
		// role(s)
		if (isset($so->ROLE)) {
			foreach ($so->ROLE as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactTitleObject();
				$entity->Kind = ContactTitleTypes::Role;
				$entity->Label = $this->sanitizeString($entry->getValue());
				$entity->Relation = $this->sanitizeString($parameters['X-ORG-ID']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Titles[] = $entity;
			}
		}
		// tag(s)
		if (isset($so->CATEGORIES)) {
			$do->Tags = new ContactTagCollection($so->CATEGORIES->getParts());
		}
		// note(s)
		if (isset($so->NOTE)) {
			foreach ($so->NOTE as $index => $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactNoteObject();
				$entity->Content = $this->sanitizeString($entry->getValue());
				$entity->Date = $parameters['CREATED'] ? new DateTimeImmutable($parameters['CREATED']) : null;
				$entity->AuthorUri = $this->sanitizeString($parameters['AUTHOR']?->getValue());
				$entity->AuthorName = $this->sanitizeString($parameters['AUTHOR-NAME']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$entity->Language = $this->sanitizeString($parameters['LANGUAGE']?->getValue());
				$entity->URI = $this->sanitizeString($parameters['VALUE']?->getValue());
				$do->Notes[] = $entity;
			}
		}
		// crypto
		if (isset($so->KEY)) {
			foreach ($so->KEY as $entry) {
				$parameters = $entry->parameters();
				$entity = new ContactCryptoObject();
				$entity->Data = $this->sanitizeString($entry->getValue());
				$entity->Type = $this->sanitizeString($parameters['MEDIATYPE']?->getValue());
				$entity->Id = $this->sanitizeString($parameters['X-ID']?->getValue());
				$entity->Index = (int)$this->sanitizeNumeric($parameters['INDEX']?->getValue());
				$entity->Priority = (int)$this->sanitizeNumeric($parameters['PREF']?->getValue());
				$entity->Context = $this->sanitizeString($parameters['TYPE']?->getValue());
				$do->Crypto[] = $entity;
			}
		}
		// Photo
		/*
		if (isset($so->PHOTO)) {
			$p = $so->PHOTO->getValue();
			if (str_starts_with($p, 'data:')) {
				$p = explode(';', $p);
				if (count($p) == 2) {
					$p[0] = explode(':', $p[0]);
					$p[1] = explode(',', $p[1]);
					$do->Photo->Type = 'data';
					$do->Photo->Data = $so->UID;
					$do->addAttachment(
						$so->UID,
						$so->UID . '.' . \OCA\JMAPC\Utile\MIME::toExtension($p[0][1]),
						$p[0][1],
						'B64',
						'CP',
						null,
						$p[1][1]
					);
				}
			} elseif (str_starts_with($p, 'uri:')) {
				$do->Photo->Type = 'uri';
				$do->Photo->Data = $this->sanitizeString(substr($p,4));
			}
			unset($p);
		}
		*/

		// return event object
		return $do;

	}

	/**
	 * Convert event object to VCard object
	 *
	 * @since Release 1.0.0
	 *
	 * @param ContactObject $so
	 *
	 * @return VCard
	 */
	public function fromContactObject(ContactObject $so): VCard {

		// construct target object
		$do = new VCard();
		$do->VERSION->setValue('4.0');
		// universal id
		if ($do->UID) {
			$do->UID->setValue($so->UUID);
		} else {
			$do->add('UID', $so->UUID);
		}
		// creation date time
		if ($so->CreatedOn) {
			$do->add('CREATED', $so->CreatedOn);
		}
		// modification date time
		if ($so->ModifiedOn) {
			$do->add('REV', $so->ModifiedOn->format(self::DATE_FORMAT_UTC));
		}
		// language
		if ($so->Language) {
			$do->add('LANGUAGE', $so->Language);
		}
		// time zone
		if ($so->TimeZone) {
			$do->add('TZ', $so->TimeZone->getName());
		}
		// label
		if ($so->Label) {
			$do->add('FN', $so->Label);
		}
		// name
		if ($so->Name) {
			$do->add(
				'N',
				[
					$so->Name->Last,
					$so->Name->First,
					$so->Name->Other,
					$so->Name->Prefix,
					$so->Name->Suffix,
					$so->Name->PhoneticLast,
					$so->Name->PhoneticFirst
				]
			);
		}
		// aliases
		foreach ($so->Name->Aliases as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('NICKNAME', $entry->Label);
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
			if ($entry->URI !== null) {
				$property->add('VALUE', $entry->URI);
			}
		}
		unset($index, $entry, $property);
		// anniversaries
		foreach ($so->Anniversaries as $entry) {
			switch ($entry->Type) {
				case ContactAnniversaryTypes::Birth:
					$do->add('BDAY', $entry->When);
					if ($entry->Location !== null) {
						$do->add('BIRTHPLACE', $entry->Location);
					}
					break;
				case ContactAnniversaryTypes::Death:
					$do->add('DEATHDAY', $entry->When);
					if ($entry->Location !== null) {
						$do->add('DEATHPLACE', $entry->Location);
					}
					break;
				case ContactAnniversaryTypes::Nuptial:
					$do->add('ANNIVERSARY', $entry->When);
					break;
			}
		}
		// pronouns
		foreach ($so->Pronouns as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('PRONOUNS', $entry->Pronoun);
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
		}
		unset($index, $entry, $property);
		// notes
		foreach ($so->Notes as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('NOTE', $entry->Content);
			if ($entry->Date !== null) {
				$property->add('CREATED', $entry->Date->format(self::DATE_FORMAT_UTC));
			}
			if ($entry->AuthorUri !== null) {
				$property->add('AUTHOR', $entry->AuthorUri);
			}
			if ($entry->AuthorName !== null) {
				$property->add('AUTHOR-NAME', $entry->AuthorName);
			}
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
			if ($entry->URI !== null) {
				$property->add('VALUE', $entry->URI);
			}
		}
		unset($index, $entry, $property);
		// phone(s)
		foreach ($so->Phone as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('TEL', $entry->Number);
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->URI !== null) {
				$property->add('VALUE', $entry->URI);
			}
		}
		unset($index, $entry, $property);
		// email(s)
		foreach ($so->Email as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('EMAIL', $entry->Address);
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->URI !== null) {
				$property->add('VALUE', $entry->URI);
			}
		}
		unset($index, $entry, $property);
		// physical location(s)
		foreach ($so->PhysicalLocations as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add(
				'ADR',
				[
					(string)$entry->Box,
					(string)$entry->Unit,
					(string)$entry->Street,
					(string)$entry->Locality,
					(string)$entry->Region,
					(string)$entry->Code,
					(string)$entry->Country,
				]
			);
			if ($entry->Label !== null) {
				$property->add('LABEL', $entry->Label);
			}
			if ($entry->Coordinates !== null) {
				$property->add('GEO', $entry->Coordinates);
			}
			if ($entry->TimeZone !== null) {
				$property->add('TZ', $entry->TimeZone);
			}
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
		}
		unset($index, $entry, $property);
		// organization(s)
		foreach ($so->Organizations as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add(
				'ORG',
				$entry->Label,
				[
					$entry->Units[0] ? $entry->Units[0] : null,
					$entry->Units[1] ? $entry->Units[1] : null
				]
			);
			if ($entry->SortName !== null) {
				$property->add('SORT-AS', $entry->SortName);
			}
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
			if ($entry->URI !== null) {
				$property->add('VALUE', $entry->URI);
			}
		}
		unset($index, $entry, $property);
		// title(s)
		foreach ($so->Titles as $index => $entry) {
			switch ($entry->Kind) {
				case ContactTitleTypes::Title:
					/** @var \Sabre\VObject\Property $property */
					$property = $do->add('TITLE', $entry->Label);
					break;
				case ContactTitleTypes::Role:
					/** @var \Sabre\VObject\Property $property */
					$property = $do->add('ROLE', $entry->Label);
					break;
			}
			if ($entry->Relation !== null) {
				$property->add('X-ORG-ID', $entry->Relation);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
			if ($entry->Language !== null) {
				$property->add('LANGUAGE', $entry->Language);
			}
		}
		unset($index, $entry, $property);
		// crypto
		foreach ($so->Crypto as $index => $entry) {
			/** @var \Sabre\VObject\Property $property */
			$property = $do->add('KEY', $entry->Data);
			if ($entry->Type !== null) {
				$property->add('MEDIATYPE', $entry->Type);
			}
			if ($entry->Id !== null) {
				$property->add('X-ID', $entry->Id);
			}
			if ($entry->Index !== null) {
				$property->add('INDEX', $entry->Index);
			}
			if ($entry->Priority !== null) {
				$property->add('PREF', $entry->Priority);
			}
			if ($entry->Context !== null) {
				$property->add('TYPE', $entry->Context);
			}
		}
		unset($index, $entry, $property);
		// Photo(s)
		/*
		if (isset($so->Photo)) {
			if ($so->Photo->Type == 'uri') {
				$do->add(
					'PHOTO',
					'uri:' . $so->Photo->Data
				);
			} elseif ($so->Photo->Type == 'data') {
				$k = array_search($so->Photo->Data, array_column($so->Attachments, 'Id'));
				if ($k !== false) {
					switch ($so->Attachments[$k]->Encoding) {
						case 'B':
							$do->add(
								'PHOTO',
								'data:' . $so->Attachments[$k]->Type . ';base64,' . base64_encode($so->Attachments[$k]->Data)
							);
							break;
						case 'B64':
							$do->add(
								'PHOTO',
								'data:' . $so->Attachments[$k]->Type . ';base64,' . $so->Attachments[$k]->Data
							);
							break;
					}
				}
			}
		}
		*/

		return $do;

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

	public function sanitizeString(?string $value): ?string {
		return $value === null || $value === '' ? null : trim($value);
	}

	public function sanitizeNumeric(?string $value): ?string {
		return $value !== null && is_numeric($value) ? $value : null;
	}

}
