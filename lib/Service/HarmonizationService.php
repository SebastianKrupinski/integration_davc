<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Store\Local\ServiceEntity;
use Psr\Log\LoggerInterface;

class HarmonizationService {
	public function __construct(
		private LoggerInterface $logger,
		private ConfigurationService $configurationService,
		private ServicesService $servicesService,
		private ContactsService $contactsService,
		private EventsService $eventsService,
		private RemoteFactory $remoteFactory,
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
			$services[] = $this->servicesService->fetchByUserIdAndServiceId($uid, $sid);
		} else {
			// retrieve all services
			$services = $this->servicesService->fetchByUserId($uid);
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
		$service->setHarmonizationState(1);
		$service->setHarmonizationStart(time());
		$service = $this->servicesService->deposit($service->getUid(), $service);
		// initialize store(s)
		$remoteStore = $this->remoteFactory->freshClient($service);

		// events
		if ($this->configurationService->isCalendarAppAvailable()) {
			$this->logger->info('Started Harmonization of Events for ' . $service->getUid());
			// assign configuration, data stores and harmonize
			$this->eventsService->harmonize($service->getUid(), $service, $remoteStore);

			$this->logger->info('Finished Harmonization of Events for ' . $service->getUid());
		}

		// contacts
		if ($this->configurationService->isContactsAppAvailable()) {
			$this->logger->info('Started Harmonization of Contacts for ' . $service->getUid());
			// assign configuration, data stores and harmonize
			$this->contactsService->harmonize($service->getUid(), $service, $remoteStore);

			$this->logger->info('Finished Harmonization of Contacts for ' . $service->getUid());
		}

		// update harmonization state and end time
		$service->setHarmonizationState(0);
		$service->setHarmonizationEnd(time());
		$service = $this->servicesService->deposit($service->getUid(), $service);

		$this->logger->info('Finished Harmonization of Collections for ' . $service->getUid());

	}

}
