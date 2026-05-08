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

namespace OCA\JMAPC\Service;

use OCA\JMAPC\Service\Remote\RemoteService;
use OCA\JMAPC\Store\Local\ServiceEntity;
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
