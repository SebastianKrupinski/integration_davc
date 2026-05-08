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

namespace OCA\JMAPC\Service\Remote;

use JmapClient\Authentication\Basic;
use JmapClient\Authentication\Bearer;
use JmapClient\Authentication\JsonBasic;
use JmapClient\Authentication\JsonBasicCookie;
use JmapClient\Client as JmapClient;
use OCA\JMAPC\Service\Remote\FM\RemoteContactsServiceFM;
use OCA\JMAPC\Service\Remote\FM\RemoteCoreServiceFM;
use OCA\JMAPC\Service\Remote\FM\RemoteEventsServiceFM;
use OCA\JMAPC\Store\Local\ServiceEntity;

class RemoteService {
	static string $clientTransportAgent = 'NextcloudJMAP/1.0 (1.0; x64)';
	//public static string $clientTransportAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0';

	/**
	 * Initialize remote data store client
	 *
	 * @since Release 1.0.0
	 */
	public static function freshClient(ServiceEntity $service): JmapClient {

		// defaults
		$client = new JmapClient();
		$client->setTransportAgent(self::$clientTransportAgent);
		// location
		$client->configureTransportMode($service->getLocationProtocol());
		$client->setHost($service->getLocationHost() . ':' . $service->getLocationPort());
		if (!empty($service->getLocationPath())) {
			$client->setDiscoveryPath($service->getLocationPath());
		}
		$client->configureTransportVerification((bool)$service->getLocationSecurity());
		// authentication
		if ($service->getAuth() == 'OA') {
			$client->setAuthentication(new Bearer(
				$service->getOauthId(),
				$service->getOauthAccessToken(),
				(int)$service->getOauthExpiry(),
				$service->getOauthAccessLocation(),
			));
		}
		if ($service->getAuth() == 'BA') {
			$client->setAuthentication(new Basic(
				$service->getBauthId(),
				$service->getBauthSecret(),
				$service->getBauthLocation(),
			));
		}
		if ($service->getAuth() == 'JB') {
			$client->setAuthentication(new JsonBasic(
				$service->getBauthId(),
				$service->getBauthSecret(),
				$service->getBauthLocation(),
			));
		}
		if ($service->getAuth() == 'JBC') {
			$client->setAuthentication(new JsonBasicCookie(
				$service->getBauthId(),
				$service->getBauthSecret(),
				$service->getBauthLocation(),
				$service->getCauthLocation(),
				md5((string)$service->getId()),
				self::cookieStoreRetrieve(...),
				self::cookieStoreDeposit(...),
			));
		}
		// debugging
		if ($service->getDebug()) {
			$client->configureTransportLogState(true);
			$client->configureTransportLogLocation(
				sys_get_temp_dir() . '/' . $service->getLocationHost() . '-' . $service->getAddressPrimary() . '.log'
			);
		}
		// return
		return $client;

	}

	/**
	 * Destroys remote data store client (Jmap Client)
	 *
	 * @since Release 1.0.0
	 */
	public static function destroyClient(JmapClient $Client): void {

		// destroy remote data store client
		$Client = null;

	}

	/**
	 * Appropriate Mail Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public static function coreService(JmapClient $Client, ?string $dataAccount = null): RemoteCoreService {
		// determine if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		// construct service based on capabilities
		if ($Client->sessionCapable('https://www.fastmail.com/dev/user', false)) {
			$service = new RemoteCoreServiceFM();
		} else {
			$service = new RemoteCoreService();
		}
		$service->initialize($Client, $dataAccount);
		return $service;
	}

	/**
	 * Appropriate Mail Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public static function mailService(JmapClient $Client, ?string $dataAccount = null): RemoteMailService {
		// determine if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		$service = new RemoteMailService();
		$service->initialize($Client, $dataAccount);
		return $service;
	}

	/**
	 * Appropriate Contacts Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public static function contactsService(JmapClient $Client, ?string $dataAccount = null): RemoteContactsService {
		// determine if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		// construct service based on capabilities
		if ($Client->sessionCapable('https://www.fastmail.com/dev/contacts', false)) {
			$service = new RemoteContactsServiceFM();
		} else {
			$service = new RemoteContactsService();
		}
		$service->initialize($Client, $dataAccount);
		return $service;
	}

	/**
	 * Appropriate Events Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public static function eventsService(JmapClient $Client, ?string $dataAccount = null): RemoteEventsService {
		// determine if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		// construct service based on capabilities
		if ($Client->sessionCapable('https://www.fastmail.com/dev/calendars', false)) {
			$service = new RemoteEventsServiceFM();
		} else {
			$service = new RemoteEventsService();
		}
		$service->initialize($Client, $dataAccount);
		return $service;
	}

	/**
	 * Appropriate Tasks Service for Connection
	 *
	 * @since Release 1.0.0
	 */
	public static function tasksService(JmapClient $Client, ?string $dataAccount = null): RemoteTasksService {
		// determine if client is connected
		if (!$Client->sessionStatus()) {
			$Client->connect();
		}
		$service = new RemoteTasksService();
		$service->initialize($Client, $dataAccount);
		return $service;
	}

	public static function cookieStoreRetrieve(mixed $id): ?array {

		$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . (string)$id . '.jmapc';

		if (!file_exists($file)) {
			return null;
		}

		$data = file_get_contents($file);
		$crypto = \OC::$server->get(\OCP\Security\ICrypto::class);
		$data = $crypto->decrypt($data);

		if (!empty($data)) {
			return json_decode($data, true);
		}

		return null;

	}

	public static function cookieStoreDeposit(mixed $id, array $value): void {

		if (empty($value)) {
			return;
		}

		$crypto = \OC::$server->get(\OCP\Security\ICrypto::class);
		$data = $crypto->encrypt(json_encode($value));

		$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . (string)$id . '.jmapc';
		file_put_contents($file, $data);

	}

}
