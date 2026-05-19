<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Models\Contacts\Entity;
use OCA\DAVC\Models\DeltaObject;
use OCA\DAVC\Models\HarmonizationStatistics;
use OCA\DAVC\Service\Local\LocalContactsService;
use OCA\DAVC\Service\Local\LocalFactory;
use OCA\DAVC\Service\Remote\RemoteClient;
use OCA\DAVC\Service\Remote\RemoteContactsService;
use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\ContactStore;
use OCA\DAVC\Store\Local\ServiceEntity;
use Psr\Log\LoggerInterface;

class ContactsService {
	private bool $debug;
	private string $userId;
	private ServiceEntity $configuration;
	private RemoteContactsService $remoteContactsService;
	private LocalContactsService $localContactsService;
	private RemoteClient $remoteStore;
	private ContactStore $localStore;

	public function __construct(
		private LoggerInterface $logger,
		private readonly LocalFactory $localFactory,
		private readonly RemoteFactory $remoteFactory,
	) {}

	/**
	 * Perform harmonization for all collections for a service
	 */
	public function harmonize(string $uid, ServiceEntity $service, RemoteClient $remoteStore) {

		$this->userId = $uid;
		$this->configuration = $service;
		$this->remoteStore = $remoteStore;
		// assign service defaults
		$this->debug = (bool)$service->getDebug();
		// initialize service remote and local services
		$this->remoteContactsService = $this->remoteFactory->contactsService($this->remoteStore);
		$this->localContactsService = $this->localFactory->contactsService($uid);
		$this->localStore = $this->localFactory->contactsStore();

		// retrieve list of collections
		$collections = $this->localStore->collectionListByService($this->configuration->getId());
		// iterate through collections
		foreach ($collections as $collection) {
			// evaluate if collection is locked and lock has not expired
			if ($collection->getHlock() == 1 &&
			   (time() - $collection->getHlockhb()) < 3600) {
				continue;
			}
			// lock collection before harmonization
			if (!$this->debug) {
				$collection->setHlock(1);
			}
			$collection->setHlockhd((int)getmypid());
			$collection = $this->localStore->collectionModify($collection);
			// execute harmonization loop
			do {
				// update lock heartbeat
				$collection->setHlockhb(time());
				$collection = $this->localStore->collectionModify($collection);
				// harmonize collection
				$statistics = $this->harmonizeCollection($collection);
				// evaluate if anything was done and publish notice if needed
				if ($statistics->total() > 0) {
					//$this->CoreService->publishNotice($uid,'Contacts_harmonized', (array)$statistics);
				}
			} while ($statistics->total() > 0);
			// update harmonization time stamp
			$collection->setHlockhb(time());
			// unlock correlation after harmonization
			$collection->setHlock(0);
			$collection = $this->localStore->collectionModify($collection);
		}

	}

	/**
	 * Perform harmonization for all entities in a collection
	 */
	public function harmonizeCollection(CollectionEntity $collection): HarmonizationStatistics {

		// define statistics object
		$statistics = new HarmonizationStatistics();
		// determine that the correlation belongs to the initialized user
		if ($collection->getUid() !== $this->userId) {
			return $statistics;
		}
		// extract required id's
		$sid = $collection->getSid();
		$lcid = $collection->getId();
		$lcsn = (string)$collection->getHisn();
		$rcid = $collection->getCcid();
		$rcsn = (string)$collection->getHesn();
		// delete and skip collection if remote id is missing
		if (empty($rcid)) {
			$this->localContactsService->collectionDeleteById($lcid);
			$this->logger->debug(Application::APP_TAG . ' - Deleted cached contacts collection for ' . $this->userId . ' due to missing external collection');
			return $statistics;
		}
		// delete and skip collection if remote collection is missing
		$remoteCollection = $this->remoteContactsService->collectionFetch($rcid);
		if (!isset($remoteCollection)) {
			$this->localContactsService->collectionDeleteById($lcid);
			$this->logger->debug(Application::APP_TAG . ' - Deleted cached contacts collection for ' . $this->userId . ' due to missing external collection');
			return $statistics;
		}

		// if the remote collection signature matches the correlation signature,
		// we can be sure that there are no changes on the remote side since last harmonization
		if ($remoteCollection->Signature === $rcsn) {
			return $statistics;
		}
		// retrieve a delta of remote entity variations
		try {
			$remoteEntityDelta = $this->remoteContactsService->entityDelta($rcid, $rcsn, 'B');
		} catch (\RuntimeException) {
			$remoteEntityDelta = $this->determineRemoteDelta($collection);
		}
		// process remote additions
		$alterations = array_unique(
			array_merge(
				$remoteEntityDelta->additions->getArrayCopy(),
				$remoteEntityDelta->modifications->getArrayCopy()
			)
		);
		foreach ($alterations as $reid) {
			// process addition
			$as = $this->harmonizeRemoteAltered($this->userId, $sid, $rcid, $reid, $lcid);
			// increment statistics
			switch ($as) {
				case 'LC':
					$statistics->LocalCreated += 1;
					break;
				case 'LU':
					$statistics->LocalUpdated += 1;
					break;
				case 'RU':
					$statistics->RemoteUpdated += 1;
					break;
			}
		}

		// process remote deletions
		$alterations = array_unique(
			$remoteEntityDelta->deletions->getArrayCopy()
		);
		foreach ($alterations as $reid) {
			// process delete
			$as = $this->harmonizeRemoteDelete($rcid, $reid, $lcid);
			if ($as == 'LD') {
				// increment statistics
				$statistics->LocalDeleted += 1;
			}
		}

		// update and deposit remote harmonization signature
		$collection->setHesn((string)$remoteCollection->Signature);
		$collection = $this->localStore->collectionModify($collection);
		// clean up
		unset($remoteCollection, $remoteEntityDelta);

		// TODO: evaluate if we can skip local delta retrieval and processing if there are no remote alterations this would require that we also skip local delta retrieval in the next step if there are remote alterations to prevent synchronization feedback loop
		return $statistics;

		// retrieve a delta of local entity variations
		$localEntityDelta = $this->localContactsService->entityDelta($lcid, $lcsn);

		// evaluate if local entity variations exist
		if (isset($localEntityDelta['stamp'])) {
			// process local additions
			$alterations = array_unique(array_merge(
				array_column($localEntityDelta['additions'], 'id'),
				array_column($localEntityDelta['modifications'], 'id')
			));
			foreach ($alterations as $leid) {
				// process addition
				$as = $this->harmonizeLocalAltered($this->userId, $sid, $lcid, $leid, $rcid, $rcsn);
				// increment statistics
				switch ($as) {
					case 'RC':
						$statistics->RemoteCreated += 1;
						break;
					case 'RU':
						$statistics->RemoteUpdated += 1;
						break;
					case 'LU':
						$statistics->LocalUpdated += 1;
						break;
				}
			}
			// process local deletions
			$alterations = array_unique(array_merge(
				array_column($localEntityDelta['deletions'], 'id')
			));
			foreach ($alterations as $leid) {
				// process deletion
				$as = $this->harmonizeLocalDelete($lcid, $leid);
				if ($as == 'RD') {
					// assign status
					$statistics->RemoteDeleted += 1;
				}
			}
			// update and deposit correlation local state
			$collection->setHisn($localEntityDelta['stamp']);
			$collection = $this->localStore->collectionModify($collection);
			// clean up
			unset($localEntityDelta);
		}

		// return statistics
		return $statistics;

	}

	/**
	 * determine remote delta based on remote and local entity list comparison
	 */
	public function determineRemoteDelta(CollectionEntity $collection): DeltaObject {
		// retrieve remote entity list and local entity list
		//$hon = (int)$collection->getHon();
		$rcid = $collection->getCcid();
		$lcid = $collection->getId();
		$rList = $this->remoteContactsService->entityList($rcid, 'basic');
		$lList = $this->localContactsService->entityList($lcid, 'basic');

		// reindex local list by remote entity id for easier comparison
		$lList = array_reduce($lList, function ($list, $entry) {
			if (!empty($entry->getCeid())) {
				$list[$entry->getCeid()] = $entry;
			}
			return $list;
		}, []);

		// iterate through remote entities to find entities that do and don't exist in correlations
		$delta = new DeltaObject();
		foreach ($rList as $entry) {
			//
			if (!$entry->CID || $entry->CID !== $rcid) {
				continue;
			}
			// determine if entry exists in local list
			// if NOT found add entity to added delta
			if (isset($lList[$entry->ID])) {
				if ($entry->Signature !== $lList[$entry->ID]->Signature) {
					$delta->modifications[] = $entry->ID;
				}
				unset($lList[$entry->ID]);
			} else {
				$delta->additions[] = $entry->ID;
			}
		}
		// iterate through remaining correlations
		// if a correlation that was not removed it must have been deleted on the remote system
		foreach ($lList as $entry) {
			$delta->deletions[] = $entry->getCeid();
		}

		return $delta;
	}

	/**
	 * harmonize locally altered entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid system user id
	 * @param int $sid service id
	 * @param int $lcid local collection id
	 * @param int $leid local entity id
	 * @param string $rcid remote collection id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeLocalAltered(string $uid, int $sid, int $lcid, int $leid, string $rcid): string {

		// // define default operation status
		$status = 'NA'; // no actions
		// define entity place holder
		$lo = null;
		$ro = null;
		// retrieve local entity
		$lo = $this->localContactsService->entityFetch($leid);
		// evaluate, if local entity was returned
		if (!($lo instanceof Entity)) {
			return $status;
		}
		// retrieve remote entity with correlation collection and entity id
		if (!empty($lo->CEID)) {
			$ro = $this->remoteContactsService->entityFetch($rcid, $lo->CEID);
		}
		// if remote entity exists
		// compare local and remote generated signature to correlation signature
		// stop processing if they match this is necessary to prevent synchronization feedback loop
		if ($lo instanceof Entity && $lo->CESN === ($lo->Signature . $ro->Signature)) {
			return $status;
		}
		// modify remote entity if one EXISTS
		// create remote entity if one DOES NOT EXIST
		if ($ro instanceof Entity) {
			// update remote entity
			$ro = $this->remoteContactsService->entityModify($rcid, $ro->ID, $lo);
			// update local entity
			if ($ro instanceof Entity) {
				$ro->CCID = $rcid; // remote collection id
				$ro->CEID = $ro->ID; // remote entity id
				$ro->CESN = ($lo->Signature . $ro->Signature); // harmonization signature
				$this->localContactsService->entityModify($uid, $sid, $lcid, $leid, $ro);
			}
			// assign operation status
			$status = 'RU'; // Remote Update
		} else {
			// create remote entity
			$ro = $this->remoteContactsService->entityCreate($rcid, $lo);
			// update local entity
			if ($ro instanceof Entity) {
				$ro->CCID = $rcid; // remote collection id
				$ro->CEID = $ro->ID; // remote entity id
				$ro->CESN = ($lo->Signature . $ro->Signature); // harmonization signature
				$this->localContactsService->entityModify($uid, $sid, $lcid, $leid, $ro);
			}
			// assign operation status
			$status = 'RC'; // Remote Create
		}
		// return operation status
		return $status;

	}

	/**
	 * harmonize locally deleted entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $lcid local collection id
	 * @param int $leid local entity id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeLocalDelete(int $leid): string {

		// retrieve local entity
		$lo = $this->localContactsService->entityFetch($leid);
		// evaluate, if local entity was returned
		if (!($lo instanceof Entity)) {
			return 'NA';
		}

		// destroy remote entity
		$rs = $this->remoteContactsService->entityDelete($lo->CCID, $lo->CEID);

		if ($rs) {
			return 'RD';
		} else {
			return 'NA';
		}

	}

	/**
	 * harmonize remotely altered entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid system user id
	 * @param int $sid service id
	 * @param string $rcid remote collection id
	 * @param string $reid remote entity id
	 * @param int $lcid local collection id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeRemoteAltered(string $uid, int $sid, string $rcid, string $reid, int $lcid): string {

		// define default operation status
		$status = 'NA'; // no action
		// define entity place holders
		$ro = null;
		$lo = null;
		// retrieve remote entity
		$ro = $this->remoteContactsService->entityFetch($rcid, $reid);
		// evaluate, if remote entity was returned
		if (!($ro instanceof Entity)) {
			return $status;
		}
		// retrieve local entity with remote collection and entity id
		$lo = $this->localContactsService->entityFetchByCorrelation($lcid, $rcid, $reid);
		// if local entity exists
		// compare local and remote generated signature to correlation signature
		// stop processing if they match this is necessary to prevent synchronization feedback loop
		if ($lo instanceof Entity && $lo->CESN === ($lo->Signature . $ro->Signature)) {
			return $status;
		}
		// modify local entity if one EXISTS
		// create local entity if one DOES NOT EXIST
		if ($lo instanceof Entity) {
			// assign missing parameters
			$ro->CCID = $rcid;
			$ro->CEID = $reid;
			$ro->CESN = ($ro->Signature . $ro->Signature);
			// update local entity
			$lo = $this->localContactsService->entityModify($uid, $sid, $lcid, (int)$lo->ID, $ro);
			// assign operation status
			$status = 'LU'; // Local Update
		} else {
			// assign missing parameters
			$ro->CCID = $rcid;
			$ro->CEID = $reid;
			$ro->CESN = ($ro->Signature . $ro->Signature);
			// create local entity
			$lo = $this->localContactsService->entityCreate($uid, $sid, $lcid, $ro);
			// assign operation status
			$status = 'LC'; // Local Create
		}
		// return operation status
		return $status;

	}

	/**
	 * harmonize remotely deleted entity
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $rcid remote collection id
	 * @param string $reid remote entity id
	 * @param int $lcid local collection id
	 *
	 * @return string what action was performed
	 */
	public function harmonizeRemoteDelete(string $rcid, string $reid, int $lcid): string {

		// destroy local entity
		$rs = $this->localContactsService->entityDeleteByCorrelation($lcid, $rcid, $reid);

		if ($rs) {
			return 'LD';
		} else {
			return 'NA';
		}

	}


}
