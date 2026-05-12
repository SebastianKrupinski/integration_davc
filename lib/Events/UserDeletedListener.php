<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Events;

use Exception;
use OCA\DAVC\Service\CoreService;
use OCA\DAVC\Service\ServicesService;
use OCP\EventDispatcher\Event;

use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

class UserDeletedListener implements IEventListener {

	public function __construct(
		private LoggerInterface $logger,
		private ServicesService $servicesService,
		private CoreService $coreService,
	) {
	}

	public function handle(Event $event): void {

		if ($event instanceof UserDeletedEvent) {
			try {
				$services = $this->servicesService->fetchByUserId($event->getUser()->getUID());

				foreach ($services as $service) {
					$this->coreService->disconnectAccount($service->getUid(), $service->Id());
				}
			} catch (Exception $e) {
				$this->logger->warning($e->getMessage(), ['uid' => $event->getUser()->getUID()]);
			}
		}
		
	}
}
