<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Local;

use OC\Files\Node\LazyUserFolder;
use OCA\DAVC\Models\Calendars\Collection;
use OCA\DAVC\Models\Calendars\Entity;
use OCA\DAVC\Models\OriginTypes;
use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\EventEntity;
use OCA\DAVC\Store\Local\EventStore;
use Sabre\VObject\Reader;

class LocalEventsService {
	protected EventStore $_Store;
	protected string $UserAttachmentPath = '';
	protected ?LazyUserFolder $FileStore = null;

	public function initialize(EventStore $Store) {
		$this->_Store = $Store;
	}

	/**
	 * retrieve collection from local storage
	 *
	 * @param int $cid Collection ID
	 *
	 * @return Collection|null
	 */
	public function collectionFetch(int $cid): ?Collection {

		// retrieve collection properties
		$co = $this->_Store->collectionFetch($cid);
		// evaluate if properties where retrieve
		if ($co instanceof CollectionEntity) {
			// construct object and return
			return new Collection(
				(string)$co->getId(),
				$co->getLabel(),
				null,
				null,
				$co->getVisible(),
				$co->getColor()
			);
		}
		
		return null;
	}

	/**
	 * delete collection from local storage
	 *
	 * @param int $cid collection id
	 *
	 * @return void
	 */
	public function collectionDeleteById(int $cid): void {

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
	 * @return Entity|null
	 */
	public function entityFetch(int $id): ?Entity {

		// retrieve entity object
		$eo = $this->_Store->entityFetch($id);
		// evaluate if entity was retrieved
		if ($eo instanceof EventEntity) {
			return $this->fromStoreEntity($eo);
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
	 * @return Entity|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?Entity {

		// retrieve entity object
		$eo = $this->_Store->entityFetchByCorrelation($cid, $ccid, $ceid);
		// evaluate if entity was retrieved
		if ($eo instanceof EventEntity) {
			return $this->fromStoreEntity($eo);
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
	 * @param Entity $so source object
	 *
	 * @return Entity Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityCreate(string $uid, int $sid, int $cid, Entity $so): ?Entity {

		// convert event object to data store entity
		$eo = $this->toStoreEntity(
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
	 * @param Entity $so source object
	 *
	 * @return Entity Status Object - item id, item uuid, item state token / Null - failed to create
	 */
	public function entityModify(string $uid, int $sid, int $cid, int $eid, Entity $so): ?Entity {

		// convert event object to data store entity
		$eo = $this->toStoreEntity(
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
	 * @param EventEntity $so
	 * @param array<string,mixed>
	 *
	 * @return Entity
	 */
	public function fromStoreEntity(EventEntity $so): Entity {

		/** VCalendar $vo */
		$vo = Reader::read($so->getData());

		$to = new Entity();
		$to->Origin = OriginTypes::Internal;
		$to->ID = (string)$so->getId();
		$to->CID = (string)$so->getCid();
		$to->Signature = $so->getSignature();
		$to->CCID = $so->getCcid();
		$to->CEID = $so->getCeid();
		$to->CESN = $so->getCesn();
		$to->data = $vo;

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
	public function toStoreEntity(Entity $so, array $additional = []): EventEntity {
		// construct entity
		$to = new EventEntity();
		// convert source object to entity
		$to->setData($so->data->serialize());
		$to->setSignature($so->Signature);
		$to->setCcid($so->CCID);
		$to->setCeid($so->CEID);
		$to->setCesn($so->CESN);

		try {
			$vc = $so->data->getBaseComponent();

			if ($vc === null) {
				foreach ($so->data->getComponents() as $component) {
					if (in_array($component->name, ['VEVENT', 'VTODO', 'VJOURNAL'], true)) {
						$vc = $component;
						break;
					}
				}
				if ($vc === null) {
					throw new \RuntimeException('Failed to parse event data: No base component found.');
				}
			}

			$to->setUuid($vc->UID->getValue());
			$to->setStartson($vc->DTSTART->getDateTime()->getTimestamp());

			if ($vc->DTEND) {
				$to->setEndson($vc->DTEND->getDateTime()->getTimestamp());
			} elseif ($vc->DURATION) {
				$to->setEndson($vc->DTSTART->getDateTime()->getTimestamp() + $vc->DURATION->getDateInterval()->s);
			} else {
				$to->setEndson($vc->DTSTART->getDateTime()->getTimestamp());
			}
			if ($vc->SUMMARY) {
				$to->setLabel($vc->SUMMARY->getValue());
			}
		} catch (\Throwable $t) {
			throw new \RuntimeException('Failed to parse event data: ' . $t->getMessage());
		}

		// override / assign additional values
		foreach ($additional as $key => $value) {
			$method = 'set' . ucfirst($key);
			$to->$method($value);
		}
		
		return $to;
	}

}
