<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use DateTime;
use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\Local\LocalService;
use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCA\DAVC\Constants;
use OCP\BackgroundJob\IJobList;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/*
use OCA\DAVC\Tasks\HarmonizationLauncher;
*/

class CoreService {
	public function __construct(
		private LoggerInterface $logger,
		private IJobList $TaskService,
		private INotificationManager $notificationManager,
		private ConfigurationService $ConfigurationService,
		private ServicesService $ServicesService,
		private ServicesTemplateService $ServicesTemplateService,
		private RemoteFactory $remoteFactory,
		private HarmonizationThreadService $HarmonizationThreadService,
	) {
	}

	/**
	 * locates connection point using users login details
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param string $account_bauth_id account username
	 * @param string $account_bauth_secret account secret
	 *
	 * @return object
	 */
	public function locateAccount(array $configuration): ?array {

		// determine account and host from identity
		$identity = $configuration['bauth_id'] ?? $configuration['oauth_id'];
		if (strpos($identity, '@') === false) {
			return null;
		}
		[$identityAccount, $identityDomain] = explode('@', $identity);
		// find template for identity domain
		$template = $this->ServicesTemplateService->findByDomain($identityDomain);
		if (isset($template[0]['connection'])) {
			$settings = json_decode($template[0]['connection'], true, 512, JSON_THROW_ON_ERROR);
			foreach ($settings as $property => $value) {
				$configuration[$property] = $value;
			}
		}
		// find dns service records
		if (empty($configuration['location_host'])) {
			$dns = dns_get_record('_caldav._tcp.' . $identityDomain, DNS_SRV);
			if ($dns[0]['type'] === 'SRV') {
				$dnsTarget = $dns[0]['target'];
				$dnsPort = $dns[0]['port'];
				$configuration['location_host'] = $dnsTarget;
				$configuration['location_port'] = $dnsPort;
			}
		}
		// find template for dns service target
		if ($dnsTarget) {
			$template = $this->ServicesTemplateService->findByDomain($dnsTarget);
			if (isset($template[0]['connection'])) {
				$settings = json_decode($template[0]['connection'], true, 512, JSON_THROW_ON_ERROR);
				foreach ($settings as $property => $value) {
					$configuration[$property] = $value;
				}
			}
		}

		if (empty($configuration['location_host'])) {
			$configuration['location_host'] = $identityDomain;
			$configuration['location_path'] = '/.well-known/caldav';
		}

		return $configuration;
	}

	/**
	 * connects to account, verifies details, then create service
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param array $configuration service connection data
	 * @param array $flags
	 *
	 * @return bool
	 */
	public function connectAccount(string $uid, array $configuration, array $flags = []): bool {

		// validate service configuration
		if (!empty($configuration['location_host']) && !\OCA\DAVC\Utile\Validator::host($configuration['location_host'])) {
			return false;
		}

		if ($configuration['auth'] === Constants::AUTHENTICATION_TYPE_BASIC) {
			// validate id
			if (!\OCA\DAVC\Utile\Validator::username($configuration['bauth_id'])) {
				return false;
			}
			// validate secret
			if (empty($configuration['bauth_secret'])) {
				return false;
			}
		} elseif ($configuration['auth'] === Constants::AUTHENTICATION_TYPE_TOKEN) {
			// validate id
			if (!\OCA\DAVC\Utile\Validator::username($configuration['oauth_id'])) {
				return false;
			}
			// validate secret
			if (empty($configuration['oauth_access_token'])) {
				return false;
			}
		} else {
			return false;
		}
		// if host was not provided, attempt to locate it
		if (empty($configuration['location_host'])) {
			$configuration = $this->locateAccount($configuration);
		}

		// construct service entity
		$service = new ServiceEntity();
		if (isset($configuration['id'])) {
			unset($configuration['id']);
		}
		$service->setUuid(\OCA\DAVC\Utile\UUID::v4());
		$service->setLabel($configuration['label'] ?? 'Unknown');
		$service->setLocationProtocol($configuration['location_protocol'] ?? 'https');
		$service->setLocationHost($configuration['location_host']);
		$service->setLocationPort($configuration['location_port'] ?? 443);
		$service->setLocationPath($configuration['location_path'] ?? null);
		$service->setLocationSecurity((bool)($configuration['location_security'] ?? 1));
		$service->setAuth($configuration['auth']);
		if ($configuration['auth'] === Constants::AUTHENTICATION_TYPE_BASIC) {
			$service->setBauthId($configuration['bauth_id']);
			$service->setBauthSecret($configuration['bauth_secret']);
			$service->setBauthLocation($configuration['bauth_location'] ?? null);
		}
		if ($configuration['auth'] === Constants::AUTHENTICATION_TYPE_TOKEN) {
			$service->setOauthId($configuration['oauth_id']);
			$service->setOauthAccessToken($configuration['oauth_access_token']);
			$service->setOauthAccessLocation($configuration['oauth_location'] ?? null);
		}

		// construct remote data store client
		$remoteStore = $this->remoteFactory->freshClient($service);

		// connect client
		try {
			$info = $remoteStore->discover();
		} catch (Throwable $e) {
			$this->logger->error('Connection failed:', ['app' => 'davc', 'exception' => $e]);
			return false;
		}

		// determine if connection was established
		if ($info['connected'] === false) {
			return false;
		}

		if ($info['principalUrl'] !== null) {
			$service->setPrincipalUrl($info['principalUrl']);
		}
		if ($info['calendarHomeSet'] !== null) {
			$service->setCalendarsUrl($info['calendarHomeSet']);
		}
		if ($info['addressbookHomeSet'] !== null) {
			$service->setAddressbooksUrl($info['addressbookHomeSet']);
		}

		$service->setEnabled(true);
		$service->setConnected(true);

		$this->ServicesService->deposit($uid, $service);

		// TODO: Should this be implemented?
		// register harmonization task
		//$this->TaskService->add(\OCA\DAVC\Tasks\HarmonizationLauncher::class, ['uid' => $uid, 'sid' => $service->getId()]);

		return true;

	}

	/**
	 * Removes all users settings, etc for specific user
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return void
	 */
	public function disconnectAccount(string $uid, int $sid): void {

		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service->getUid() !== $uid) {
			return;
		}
		// deregister task
		$this->TaskService->remove(\OCA\DAVC\Tasks\HarmonizationLauncher::class, ['uid' => $uid, 'sid' => $sid]);
		// terminate harmonization thread
		$this->HarmonizationThreadService->terminate($uid);
		// initialize contacts data store
		$localStore = LocalService::contactsStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize events data store
		$localStore = LocalService::eventsStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// initialize tasks data store
		$localStore = LocalService::tasksStore();
		// delete local entities
		$localStore->entityDeleteByService($sid);
		// delete local collection
		$localStore->collectionDeleteByService($sid);
		// delete service
		$this->ServicesService->delete($uid, $service);

	}

	/**
	 * retrieves remote collections for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return array of remote collection(s) and attributes
	 */
	public function remoteCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactsSupported' => false, 'ContactsCollections' => [], 'EventsSupported' => false, 'EventsCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user is the service owner
		if ($service->getUid() !== $uid) {
			return $data;
		}
		// create remote store client
		$remoteStore = $this->remoteFactory->freshClient($service);
		// retrieve collections for contacts module
		if ($this->ConfigurationService->isContactsAppAvailable() && $remoteStore->getAddressbookHome() !== null) {
			try {
				$remoteContactsService = $this->remoteFactory->contactsService($remoteStore);
				$collections = $remoteContactsService->collectionList();
				$data['ContactsSupported'] = true;
				$data['ContactsCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (Throwable $e) {
				// AddressBook name space is not supported fail silently
			}
			// if AddressBook name space is not supported see if Contacts name space works
			if (count($data['ContactsCollections']) === 0) {
				try {
					$list = $remoteContactsService->entityList('', 'B');
					$data['ContactsSupported'] = true;
					$data['ContactsCollections'][] = ['id' => 'Default', 'label' => 'Personal - Contacts', 'count' => $list['total']];
				} catch (Throwable $e) {
					// ContactCard name space is not supported fail silently
				}

			}
		}
		// retrieve collections for events module
		if ($this->ConfigurationService->isCalendarAppAvailable() && $remoteStore->getCalendarHome() !== null) {
			$data['EventsSupported'] = true;
			$data['EventsCollections'] = [];

			try {
				$remoteEventsService = $this->remoteFactory->eventsService($remoteStore);
				$collections = $remoteEventsService->collectionList();
				$data['EventsCollections'] = array_map(function ($collection) {
					return ['id' => $collection->Id, 'label' => 'Personal - ' . $collection->Label];
				}, $collections);
			} catch (Throwable $e) {
				$data['EventsSupported'] = false;
				$data['EventsCollections'] = [];
				$this->logger->error('Failed to retrieve remote events collections:', ['app' => 'davc', 'exception' => $e]);
			}
		}
		// return response
		return $data;

	}

	/**
	 * retrieves local collections for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 *
	 * @return array of collection correlation(s) and attributes
	 */
	public function localCollectionsFetch(string $uid, int $sid): array {

		// construct response object
		$data = ['ContactCollections' => [], 'EventCollections' => []];
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user if the service owner
		if ($service->getUid() !== $uid) {
			return $data;
		}
		// retrieve local collections
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			$localStore = LocalService::contactsStore();
			$data['ContactCollections'] = $localStore->collectionListByService($sid);
		}
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			$localStore = LocalService::eventsStore();
			$data['EventCollections'] = $localStore->collectionListByService($sid);
		}
		// return response
		return $data;

	}

	/**
	 * Deposit collection correlations for all modules
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param array $cc contacts collection(s) correlations
	 * @param array $ec events collection(s) correlations
	 *
	 * @return array of collection correlation(s) and attributes
	 */
	public function localCollectionsDeposit(string $uid, int $sid, array $cc, array $ec): void {

		// terminate harmonization thread, in case the user changed any correlations
		//$this->HarmonizationThreadService->terminate($uid);
		// retrieve service information
		$service = $this->ServicesService->fetch($sid);
		// determine if user is the service owner
		if ($service->getUid() !== $uid) {
			return;
		}
		// deposit contacts correlations
		if ($this->ConfigurationService->isContactsAppAvailable()) {
			// initialize data store
			$localStore = LocalService::contactsStore();
			// process entries
			foreach ($cc as $entry) {
				if (!isset($entry['enabled']) || !is_bool($entry['enabled'])) {
					continue;
				}
				switch ((bool)$entry['enabled']) {
					case false:
						if (is_numeric($entry['id'])) {
							$collection = $localStore->collectionFetch($entry['id']);
							if ($collection->getUid() === $uid) {
								$localStore->collectionDelete($collection);
							}
						}
						break;
					case true:
						if (empty($entry['id'])) {
							// create local collection
							$collection = $localStore->collectionFresh();
							$collection->setUid($uid);
							$collection->setSid($sid);
							$collection->setCcid($entry['ccid']);
							$collection->setUuid(\OCA\DAVC\Utile\UUID::v4());
							$collection->setLabel('DavC: ' . ($entry['label'] ?? 'Unknown'));
							$collection->setColor($entry['color'] ?? '#0055aa');
							$collection->setVisible(true);
							$id = $localStore->collectionCreate($collection);
						}
						break;
				}
			}
		}
		// deposit events correlations
		if ($this->ConfigurationService->isCalendarAppAvailable()) {
			// initialize data store
			$localStore = LocalService::eventsStore();
			// process entries
			foreach ($ec as $entry) {
				if (!isset($entry['enabled']) || !is_bool($entry['enabled'])) {
					continue;
				}
				switch ((bool)$entry['enabled']) {
					case false:
						if (is_numeric($entry['id'])) {
							$collection = $localStore->collectionFetch($entry['id']);
							if ($collection->getUid() === $uid) {
								$localStore->collectionDelete($collection);
							}
						}
						break;
					case true:
						if (empty($entry['id'])) {
							// create local collection
							$collection = $localStore->collectionFresh();
							$collection->setUid($uid);
							$collection->setSid($sid);
							$collection->setCcid($entry['ccid']);
							$collection->setUuid(\OCA\DAVC\Utile\UUID::v4());
							$collection->setLabel('DavC: ' . ($entry['label'] ?? 'Unknown'));
							$collection->setColor($entry['color'] ?? '#0055aa');
							$collection->setVisible(true);
							$id = $localStore->collectionCreate($collection);
						}
						break;
				}
			}
		}
	}

	/**
	 * publish user notification
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param string $subject notification type
	 * @param array $params notification parameters to pass
	 *
	 * @return array of collection correlation(s) and attributes
	 */
	public function publishNotice(string $uid, string $subject, array $params): void {
		// construct notification object
		$notification = $this->notificationManager->createNotification();
		// assign attributes
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new DateTime())
			->setObject('eas', 'eas')
			->setSubject($subject, $params);
		// submit notification
		$this->notificationManager->notify($notification);
	}

}
