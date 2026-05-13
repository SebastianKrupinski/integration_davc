<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use OCA\DAVC\Constants;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCP\Http\Client\IClientService;

class RemoteFactory {
	public static string $clientTransportAgent = 'NextcloudDAVC/1.0 (1.0; x64)';

	public function __construct(
		private IClientService $clientService,
	) {
	}

	/**
	 * Initialize remote data store client
	 *
	 * @since Release 1.0.0
	 */
	public function freshClient(ServiceEntity $service): RemoteClient {
		$client = new RemoteClient($this->clientService);
		$client->setTransportAgent(self::$clientTransportAgent);
		$client->configureLocation(
			$service->getLocationProtocol(),
			$service->getLocationHost(),
			$service->getLocationPort(),
			$service->getLocationPath(),
		);
		$client->configureTransportVerification((bool)$service->getLocationSecurity());

		if ($service->getAuth() === Constants::AUTHENTICATION_TYPE_BASIC) {
			$client->setBasicAuthentication(
				(string)$service->getBauthId(),
				(string)$service->getBauthSecret(),
			);
		}

		if ($service->getAuth() === Constants::AUTHENTICATION_TYPE_TOKEN) {
			$client->setBearerAuthentication((string)$service->getOauthAccessToken());
		}

		// Set default capabilities from saved service
		// This is does to short circuit the discovery process and avoid 
		// unnecessary network calls when capabilities are already known from previous discovery
		$client->setPrincipalUrl($service->getPrincipalUrl());
		$client->setCalendarHomeSet($service->getCalendarsUrl());
		$client->setAddressbookHomeSet($service->getAddressbooksUrl());

		return $client;
	}

	/**
	 * Appropriate Core Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public function coreService(RemoteClient $client): RemoteCoreService {
		$service = new RemoteCoreService();
		$service->initialize($client);

		return $service;
	}

	/**
	 * Appropriate Contacts Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public function contactsService(RemoteClient $client): RemoteContactsService {
		$service = new RemoteContactsService();
		$service->initialize($client);

		return $service;
	}

	/**
	 * Appropriate Events Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public function eventsService(RemoteClient $client): RemoteEventsService {
		$service = new RemoteEventsService();
		$service->initialize($client);

		return $service;
	}

}
