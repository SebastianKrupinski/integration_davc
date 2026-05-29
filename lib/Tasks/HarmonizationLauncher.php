<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tasks;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\HarmonizationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;

class HarmonizationLauncher extends TimedJob {

	private const INTERVAL_SECONDS = 15 * 60;

	public function __construct(
		ITimeFactory $time,
		private readonly HarmonizationService $harmonizationService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::INTERVAL_SECONDS);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @param array{uid?: string, sid?: int|string} $argument
	 */
	protected function run($argument): void {
		if (!is_array($argument) || !isset($argument['uid'], $argument['sid'])) {
			$this->logger->warning('HarmonizationLauncher invoked without uid/sid', [
				'app' => Application::APP_ID,
				'argument' => $argument,
			]);
			return;
		}

		try {
			$this->harmonizationService->performHarmonization(
				(string)$argument['uid'],
				(int)$argument['sid'],
			);
		} catch (Throwable $e) {
			$this->logger->error('HarmonizationLauncher failed', [
				'app' => Application::APP_ID,
				'exception' => $e,
				'uid' => $argument['uid'],
				'sid' => $argument['sid'],
			]);
		}
	}
}
