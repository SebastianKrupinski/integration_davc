<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\DAV\Service;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Constants;
use OCA\DAVC\Service\CoreService;
use OCA\DAVC\Service\ServicesService;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class CoreServiceConnectAccountTest extends TestCase {

	private CoreService $coreService;
	private ServicesService $servicesService;
	private IDBConnection $db;
	private IJobList $jobList;
	private string $uid = 'tester';
	private bool $initialized = false;
	private string $davProtocol;
	private string $davHost;
	private int $davPort;
	private string $davPath;
	private string $davUsername;
	private string $davPassword;

	protected function setUp(): void {
		parent::setUp();

		$this->davProtocol = $this->requiredEnv('DAV_PROTOCOL');
		$this->davHost = $this->requiredEnv('DAV_HOST');
		$this->davPort = (int)$this->requiredEnv('DAV_PORT');
		$this->davPath = $this->requiredEnv('DAV_PATH');
		$this->davUsername = $this->requiredEnv('DAV_USERNAME');
		$this->davPassword = $this->requiredEnv('DAV_PASSWORD');

		$container = (new Application())->getContainer();
		$this->coreService = $container->get(CoreService::class);
		$this->servicesService = $container->get(ServicesService::class);
		$this->db = Server::get(IDBConnection::class);
		$this->jobList = Server::get(IJobList::class);
		$this->initialized = true;

		$this->cleanupServices();
	}

	protected function tearDown(): void {
		if ($this->initialized) {
			$this->cleanupServices();
		}

		parent::tearDown();
	}

	private function requiredEnv(string $key): string {
		$value = getenv($key);
		if ($value === false || $value === '') {
			self::markTestSkipped('DAV integration test environment is not configured.');
		}

		self::assertIsString($value);

		return $value;
	}

	public function testConnectManually(): void {
		$service = $this->coreService->connectAccount($this->uid, [
			'label' => 'Local Nextcloud DAV',
			'auth' => Constants::AUTHENTICATION_TYPE_BASIC,
			'bauth_id' => $this->davUsername,
			'bauth_secret' => $this->davPassword,
			'location_protocol' => $this->davProtocol,
			'location_host' => $this->davHost,
			'location_port' => $this->davPort,
			'location_path' => $this->davPath,
			'location_security' => false,
		]);

		$this->assertInstanceOf(ServiceEntity::class, $service);
		$this->assertGreaterThan(0, $service->getId());
		$this->assertSame($this->uid, $service->getUid());
		$this->assertTrue($service->getConnected());
		$this->assertTrue($service->getEnabled());
		$this->assertIsString($service->getPrincipalUrl());
		$this->assertNotSame('', $service->getPrincipalUrl());
		$this->assertIsString($service->getCalendarsUrl());
		$this->assertNotSame('', $service->getCalendarsUrl());
		$this->assertIsString($service->getAddressbooksUrl());
		$this->assertNotSame('', $service->getAddressbooksUrl());

		$persistedService = $this->servicesService->fetchByUserIdAndServiceId($this->uid, $service->getId());

		$this->assertNotNull($persistedService);
		$this->assertSame($service->getPrincipalUrl(), $persistedService->getPrincipalUrl());
		$this->assertSame($service->getCalendarsUrl(), $persistedService->getCalendarsUrl());
		$this->assertSame($service->getAddressbooksUrl(), $persistedService->getAddressbooksUrl());
	}

	private function cleanupServices(): void {
		foreach ($this->servicesService->fetchByUserId($this->uid) as $service) {
			$this->servicesService->delete($this->uid, $service);
		}
	}
}
