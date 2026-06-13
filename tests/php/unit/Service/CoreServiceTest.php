<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Unit\Service;

use OCA\DAVC\Constants;
use OCA\DAVC\Service\ConfigurationService;
use OCA\DAVC\Service\CoreService;
use OCA\DAVC\Service\Local\LocalFactory;
use OCA\DAVC\Service\Remote\RemoteClient;
use OCA\DAVC\Service\Remote\RemoteFactory;
use OCA\DAVC\Service\ServicesService;
use OCA\DAVC\Service\ServicesTemplateService;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCP\BackgroundJob\IJobList;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CoreServiceTest extends TestCase {
	private LoggerInterface&MockObject $logger;

	private IJobList&MockObject $jobList;

	private INotificationManager&MockObject $notificationManager;

	private ConfigurationService&MockObject $configurationService;

	private ServicesService&MockObject $servicesService;

	private ServicesTemplateService&MockObject $servicesTemplateService;

	private RemoteFactory&MockObject $remoteFactory;

	private LocalFactory&MockObject $localFactory;

	private CoreService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->configurationService = $this->createMock(ConfigurationService::class);
		$this->servicesService = $this->createMock(ServicesService::class);
		$this->servicesTemplateService = $this->createMock(ServicesTemplateService::class);
		$this->remoteFactory = $this->createMock(RemoteFactory::class);
		$this->localFactory = $this->createMock(LocalFactory::class);

		$this->service = new CoreService(
			$this->logger,
			$this->jobList,
			$this->notificationManager,
			$this->configurationService,
			$this->servicesService,
			$this->servicesTemplateService,
			$this->remoteFactory,
			$this->localFactory,
		);
	}

	public function testConnectAccountThrowsForInvalidBasicCredentials(): void {
		$configuration = [
			'auth' => Constants::AUTHENTICATION_TYPE_BASIC,
			'bauth_id' => ' bad-user ',
			'bauth_secret' => 'secret123',
			'location_host' => 'example.com',
			'location_path' => '/remote.php/dav',
		];

		$this->remoteFactory->expects($this->never())
			->method('freshClient');
		$this->servicesService->expects($this->never())
			->method('deposit');
		$this->jobList->expects($this->never())
			->method('add');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid DAV username provided for basic authentication.');

		$this->service->connectAccount('user-1', $configuration);
	}

	public function testConnectAccountPersistsDiscoveredServiceAndSchedulesTask(): void {
		$configuration = [
			'label' => 'Example Server',
			'auth' => Constants::AUTHENTICATION_TYPE_BASIC,
			'bauth_id' => 'user@example.com',
			'bauth_secret' => 'secret123',
			'bauth_location' => '/login',
			'location_host' => 'example.com',
			'location_path' => '/remote.php/dav',
			'location_protocol' => 'https',
			'location_port' => 8443,
			'location_security' => false,
		];
		$remoteClient = $this->createMock(RemoteClient::class);

		$this->remoteFactory->expects($this->once())
			->method('freshClient')
			->with($this->callback(function (ServiceEntity $service): bool {
				return $service->getLabel() === 'Example Server'
					&& $service->getLocationProtocol() === 'https'
					&& $service->getLocationHost() === 'example.com'
					&& $service->getLocationPort() === 8443
					&& $service->getLocationPath() === '/remote.php/dav'
					&& (bool)$service->getLocationSecurity() === false
					&& $service->getAuth() === Constants::AUTHENTICATION_TYPE_BASIC
					&& $service->getBauthId() === 'user@example.com'
					&& $service->getBauthSecret() === 'secret123'
					&& $service->getBauthLocation() === '/login'
					&& $service->getUuid() !== null;
			}))
			->willReturn($remoteClient);

		$remoteClient->expects($this->once())
			->method('discover')
			->willReturn([
				'connected' => true,
				'principalUrl' => '/principals/users/user/',
				'calendarHomeSet' => '/dav/calendars/user/',
				'addressbookHomeSet' => '/dav/addressbooks/user/',
			]);

		$this->servicesService->expects($this->once())
			->method('deposit')
			->with('user-1', $this->callback(function (ServiceEntity $service): bool {
				return $service->getPrincipalUrl() === '/principals/users/user/'
					&& $service->getCalendarsUrl() === '/dav/calendars/user/'
					&& $service->getAddressbooksUrl() === '/dav/addressbooks/user/'
					&& $service->getConnected() === true
					&& $service->getEnabled() === true;
			}))
			->willReturnCallback(function (string $uid, ServiceEntity $service): ServiceEntity {
				$service->setUid($uid);
				$service->setId(42);
				return $service;
			});

		$this->jobList->expects($this->once())
			->method('add')
			->with('OCA\\DAVC\\Tasks\\HarmonizationLauncher', ['uid' => 'user-1', 'sid' => 42]);

		$connectedService = $this->service->connectAccount('user-1', $configuration);

		$this->assertInstanceOf(ServiceEntity::class, $connectedService);
		$this->assertSame(42, $connectedService->getId());
		$this->assertSame('user-1', $connectedService->getUid());
		$this->assertSame('/principals/users/user/', $connectedService->getPrincipalUrl());
	}

	public function testConnectAccountThrowsWhenRemoteDiscoveryThrows(): void {
		$configuration = [
			'auth' => Constants::AUTHENTICATION_TYPE_BASIC,
			'bauth_id' => 'user@example.com',
			'bauth_secret' => 'secret123',
			'location_host' => 'example.com',
			'location_path' => '/remote.php/dav',
		];
		$remoteClient = $this->createMock(RemoteClient::class);

		$this->remoteFactory->expects($this->once())
			->method('freshClient')
			->willReturn($remoteClient);
		$remoteClient->expects($this->once())
			->method('discover')
			->willThrowException(new RuntimeException('boom'));
		$this->logger->expects($this->once())
			->method('error')
			->with('Connection failed:', $this->arrayHasKey('exception'));
		$this->servicesService->expects($this->never())
			->method('deposit');
		$this->jobList->expects($this->never())
			->method('add');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DAV discovery failed for https://example.com:443/remote.php/dav: boom');

		$this->service->connectAccount('user-1', $configuration);
	}
}
