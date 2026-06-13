<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Unit\Tasks;

use OCA\DAVC\Service\ConfigurationService;
use OCA\DAVC\Service\HarmonizationService;
use OCA\DAVC\Tasks\HarmonizationLauncher;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HarmonizationLauncherTest extends TestCase {
	private ITimeFactory&MockObject $time;

	private HarmonizationService&MockObject $harmonizationService;

	private ConfigurationService&MockObject $configurationService;

	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->time = $this->createMock(ITimeFactory::class);
		$this->harmonizationService = $this->createMock(HarmonizationService::class);
		$this->configurationService = $this->createMock(ConfigurationService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testUsesConfiguredHarmonizationInterval(): void {
		$this->configurationService->expects($this->once())
			->method('getHarmonizationInterval')
			->willReturn(1800);

		$launcher = new HarmonizationLauncher(
			$this->time,
			$this->harmonizationService,
			$this->configurationService,
			$this->logger,
		);

		$this->assertSame(1800, $launcher->getInterval());
	}
}
