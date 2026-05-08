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

use DateTimeZone;
use OC\Files\Node\LazyUserFolder;
use OCA\DAV\CalDAV\EventReader;
use OCA\JMAPC\Objects\OriginTypes;
use OCA\JMAPC\Objects\Task\TaskCollectionObject;
use OCA\JMAPC\Objects\Task\TaskObject;
use OCA\JMAPC\Store\Local\CollectionEntity;
use OCA\JMAPC\Store\Local\TaskEntity;
use OCA\JMAPC\Store\Local\TaskStore;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Reader;

class LocalTasksService {
	protected string $DateFormatUTC = 'Ymd\THis\Z';
	protected string $DateFormatDateTime = 'Ymd\THis';
	protected string $DateFormatDateOnly = 'Ymd';
	protected TaskStore $_Store;
	protected ?DateTimeZone $SystemTimeZone = null;
	protected ?DateTimeZone $UserTimeZone = null;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function __construct() {
	}

	public function initialize(TaskStore $Store) {

		$this->_Store = $Store;

	}

	/**
	 * retrieve collection from local storage
	 *
	 * @param int $cid Collection ID
	 *
	 * @return TaskCollectionObject|null
	 */
	public function collectionFetch(int $cid): ?TaskCollectionObject {

		// retrieve collection properties
		$co = $this->_Store->collectionFetch($cid);
		// evaluate if properties where retrieve
		if ($co instanceof CollectionEntity) {
			// construct object and return
			return new TaskCollectionObject(
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
	 * @return TaskObject|null
	 */
	public function entityFetch(int $id): ?TaskObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetch($id);
		// evaluate if entity was retrieved
		if ($eo instanceof TaskEntity) {
			return $this->fromTaskEntity($eo);
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
	 * @return TaskObject|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?TaskObject {

		// retrieve entity object
		$eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
		if ($eo instanceof TaskEntity) {
			return $this->fromTaskEntity($eo);
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
	 * @param TaskObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, TaskObject $so): ?object {

		// convert Task object to data store entity
		$eo = $this->toTaskEntity(
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
	 * @param TaskObject $so source object
	 *
	 * @return object Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, TaskObject $so): ?object {

		// convert Task object to data store entity
		$eo = $this->toTaskEntity(
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
		if ($eo instanceof TaskEntity) {
			// delete entry from data store
			$eo = $this->_Store->entityDelete($eo);
			return true;
		} else {
			return false;
		}

	}

	/**
	 * convert store entity to Task object
	 *
	 * @since Release 1.0.0
	 *
	 * @param TaskEntity $so
	 * @param array<string,mixed>
	 *
	 * @return TaskObject
	 */
	public function fromTaskEntity(TaskEntity $so, array $additional = []): TaskObject {

		// prase vData
		$vObject = Reader::read($so->getData());
		// convert entity
		$to = $this->fromVObject($vObject->VTODO);
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
	 * convert Task object to store entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param TaskObject $so
	 * @param array<string,mixed>
	 *
	 * @return TaskEntity
	 */
	public function toTaskEntity(TaskObject $so, array $additional = []): TaskEntity {

		// construct entity
		$to = new TaskEntity();
		$vo = $this->toVObject($so);
		// convert source object to entity
		$to->setData("BEGIN:VCALENDAR\nVERSION:2.0\n" . $vo->serialize() . "\nEND:VCALENDAR");
		$to->setUuid($so->UUID);
		$to->setSignature($this->generateSignature($so));
		$to->setCcid($so->CCID);
		$to->setCeid($so->CEID);
		$to->setCesn($so->CESN);
		$to->setLabel($so->Label);
		$to->setDescription($so->Description);
		$to->setStartson($so->StartsOn->setTimezone(new DateTimeZone('UTC'))->format('U'));
		if ($so->OccurrencePatterns > 0) {
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
	 * convert vtodo object to Task object
	 *
	 * @since Release 1.0.0
	 *
	 * @param VTodo $so
	 *
	 * @return TaskObject
	 */
	public function fromVObject(VTodo $so): TaskObject {

		// construct target object
		$to = new TaskObject();
		// Origin
		$to->Origin = OriginTypes::Internal;
		// universal id
		if (isset($so->UID)) {
			$to->UUID = trim($so->UID->getValue());
		}
		// creation date time
		if (isset($so->CREATED)) {
			$to->CreatedOn = $so->CREATED->getDateTime();
		}
		// modification date time
		if (isset($so->{'LAST-MODIFIED'})) {
			$to->ModifiedOn = $so->{'LAST-MODIFIED'}->getDateTime();
		}

		// return Task object
		return $to;

	}

	/**
	 * Convert Task object to vtodo object
	 *
	 * @since Release 1.0.0
	 *
	 * @param TaskObject $so
	 *
	 * @return VTodo
	 */
	public function toVObject(TaskObject $so): VTodo {

		// construct target object
		$to = (new \Sabre\VObject\Component\VCalendar())->createComponent('VTODO');
		// UID
		if ($so->UUID) {
			$to->UID->setValue($so->UUID);
		} else {
			$to->add('UUID', $so->UUID);
		}
		// creation date
		if ($so->CreatedOn) {
			$to->add('CREATED', $so->CreatedOn->format($this->DateFormatUTC));
			if ($to->DTSTAMP) {
				$to->DTSTAMP->setValue($so->CreatedOn->format($this->DateFormatUTC));
			} else {
				$to->add('DTSTAMP', $so->CreatedOn->format($this->DateFormatUTC));
			}
		}
		// modification date
		if ($so->ModifiedOn) {
			$to->add('LAST-MODIFIED', $so->ModifiedOn->format($this->DateFormatUTC));
		}

		return $to;

	}

	public function generateSignature(TaskObject $eo): string {

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
