<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Unit\Service;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\ConfigurationService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase {
	private IConfig&MockObject $config;

	private ICrypto&MockObject $crypto;

	private ConfigurationService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->service = new ConfigurationService($this->config, $this->crypto);
	}

	public function testGetHarmonizationIntervalReturnsDefaultWhenUnset(): void {
		$this->config->method('getAppValue')
			->with(Application::APP_ID, 'harmonization_interval')
			->willReturn('');

		$this->assertSame(900, $this->service->getHarmonizationInterval());
	}

	public function testGetHarmonizationIntervalReturnsStoredValue(): void {
		$this->config->method('getAppValue')
			->with(Application::APP_ID, 'harmonization_interval')
			->willReturn('3600');

		$this->assertSame(3600, $this->service->getHarmonizationInterval());
	}

	public function testGetHarmonizationIntervalClampsToMinimum(): void {
		$this->config->method('getAppValue')
			->with(Application::APP_ID, 'harmonization_interval')
			->willReturn('30');

		$this->assertSame(300, $this->service->getHarmonizationInterval());
	}

	public function testSetHarmonizationIntervalPersistsValue(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'harmonization_interval', '1800');

		$this->service->setHarmonizationInterval(1800);
	}

	public function testSetHarmonizationIntervalClampsToMinimum(): void {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'harmonization_interval', '300');

		$this->service->setHarmonizationInterval(60);
	}
}
