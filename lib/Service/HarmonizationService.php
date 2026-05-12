<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use OCA\DAVC\Service\Remote\RemoteService;
use OCA\DAVC\Store\Local\ServiceEntity;
use Psr\Log\LoggerInterface;

class HarmonizationService {
	public function __construct(
		private LoggerInterface $logger,
		private ConfigurationService $ConfigurationService,
		private CoreService $CoreService,
		private ServicesService $ServicesService,
		private ContactsService $ContactsService,
		private EventsService $EventsService,
		private TasksService $TasksService,
		private HarmonizationThreadService $HarmonizationThreadService,
	) {
	}

	/**
	 * perform harmonization for all or specific services of a user
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return void
	 */
	public function performHarmonization(string $uid, ?int $sid = null): void {

		if ($sid !== null) {
			// retrieve service
			$services[] = $this->ServicesService->fetchByUserIdAndServiceId($uid, $sid);
		} else {
			// retrieve all services
			$services = $this->ServicesService->fetchByUserId($uid);
		}

		foreach ($services as $service) {
			$this->performHarmonizationForService($service);
		}

	}

	/**
	 * perform harmonization for all modules of a specific service
	 *
	 * @since Release 1.0.0
	 */
	public function performHarmonizationForService(ServiceEntity $service): void {

		// determine if we should run harmonization
		if (!$service->getEnabled() || !$service->getConnected()) {
			return;
		}
		// update harmonization state and start time
		$service->setHarmonizationState(true);
		$service->setHarmonizationStart(time());
		$service = $this->ServicesService->deposit($service->getUid(), $service);
		// initialize store(s)
		$remoteStore = RemoteService::freshClient($service);

		// contacts
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$this->logger->info('Started Harmonization of Contacts for ' . $service->getUid());
			// assign configuration, data stores and harmonize
			$this->ContactsService->harmonize($service->getUid(), $service, $remoteStore);

			$this->logger->info('Finished Harmonization of Contacts for ' . $service->getUid());
		}

		// events
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			$this->logger->info('Started Harmonization of Events for ' . $service->getUid());
			// assign configuration, data stores and harmonize
			$this->EventsService->harmonize($service->getUid(), $service, $remoteStore);

			$this->logger->info('Finished Harmonization of Events for ' . $service->getUid());
		}

		// tasks
		if ($this->ConfigurationService->isTasksAppAvailable()) {
			$this->logger->info('Started Harmonization of Tasks for ' . $service->getUid());
			// assign configuration, data stores and harmonize
			//$this->TasksService->harmonize($uid, $service, $remoteStore);

			$this->logger->info('Finished Harmonization of Tasks for ' . $service->getUid());
		}

		// update harmonization state and end time
		$service->setHarmonizationState(false);
		$service->setHarmonizationEnd(time());
		$service = $this->ServicesService->deposit($service->getUid(), $service);

		$this->logger->info('Finished Harmonization of Collections for ' . $service->getUid());

	}

}
