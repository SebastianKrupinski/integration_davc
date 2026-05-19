<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Commands;

use OCA\DAVC\Service\CoreService;
use OCA\DAVC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Disconnect extends Command {

	public function __construct(
		private IUserManager $userManager,
		private CoreService $CoreService,
		private ServicesService $servicesService
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('davc:disconnect')
			->setDescription('Disconnects a user from a DAV server')
			->addArgument('user', InputArgument::REQUIRED, 'User with service(s) to disconnect')
			->addArgument('service', InputArgument::OPTIONAL, 'Service to disconnect');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		$serviceArgument = $input->getArgument('service');

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		if ($serviceArgument !== null) {
			$sid = (int)$serviceArgument;
			$service = $this->servicesService->fetchByUserIdAndServiceId($uid, $sid);
			if (!$service) {
				$output->writeln("<error>Service $sid does not exist</error>");
				return self::INVALID;
			}
			$services = [$service];
		} else {
			$services = $this->servicesService->fetchByUserId($uid);
		}

		foreach ($services as $service) {
			$sid = (int)$service->getId();
			$serviceName = $service->getLabel() ?: $service->getLocationHost();
			$this->CoreService->disconnectAccount($uid, $sid);
			$output->writeln("<info>Disconnected user $uid from DAV service $serviceName</info>");
		}

		return self::SUCCESS;
	}
}
