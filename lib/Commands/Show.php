<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Commands;

use OCA\DAVC\Service\ServicesService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends Command {

	public function __construct(
		private IUserManager $userManager,
		private ServicesService $servicesService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('davc:show')
			->setDescription('Show all configured services')
			->addArgument('user', InputArgument::OPTIONAL, 'User with configured service(s)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');

		if ($uid !== null) {
			if (!$this->userManager->userExists($uid)) {
				$output->writeln("<error>User $uid does not exist</error>");
				return self::INVALID;
			}
			$services = $this->servicesService->fetchByUserId($uid);
		} else {
			$services = $this->servicesService->list();
		}

		$list = [];
		foreach ($services as $service) {
			$harmonizedAt = $service->getHarmonizationEnd();
			$list[] = [
				$service->getUid(),
				$service->getId(),
				$service->getLabel(),
				$service->getLocationProtocol(),
				$service->getLocationHost(),
				$service->getLocationPort(),
				$service->getLocationPath(),
				$service->getAuth(),
				$service->getEnabled() ? 'yes' : 'no',
				$service->getConnected() ? 'yes' : 'no',
				$service->getDebug() ? 'yes' : 'no',
				is_int($harmonizedAt) && $harmonizedAt > 0 ? date('Y-m-d H:i:s', $harmonizedAt) : 'no',
				$service->getEventsMode() ?: 'Cached',
				$service->getContactsMode() ?: 'Cached',
			];
		}

		if (count($list) > 0) {
			$table = new Table($output);
			$table->setHeaders(['User', 'Id', 'Label', 'Protocol', 'Host', 'Port', 'Path', 'Auth', 'Enabled', 'Connected', 'Debug', 'Harmonized', 'Events Mode', 'Contacts Mode'])->setRows($list);
			$table->render();
		} else {
			$output->writeln("<info>User $uid has no configured services</info>");
		}
		return self::SUCCESS;
	}
}
