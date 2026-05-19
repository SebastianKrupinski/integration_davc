<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Commands;

use OCA\DAVC\Service\HarmonizationService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Harmonize extends Command {

	public function __construct(
		private IUserManager $userManager,
		private HarmonizationService $HarmonizationService
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('davc:harmonize')
			->setDescription('Harmonizes a user\'s DAV contacts and calendars')
			->addArgument('user', InputArgument::REQUIRED, 'User whom to harmonize')
			->addArgument('service', InputArgument::OPTIONAL, 'Service to harmonize');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('user');
		$sid = $input->getArgument('service');

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		$output->writeln("<info>Starting harmonization for User $uid</info>");

		if ($sid !== null) {
			$this->HarmonizationService->performHarmonization($uid, (int)$sid);
		}
		else {
			$this->HarmonizationService->performHarmonization($uid);
		}

		$output->writeln("<info>Ended harmonization for User $uid</info>");

		return self::SUCCESS;

	}
}
