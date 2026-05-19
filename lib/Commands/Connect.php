<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Commands;

use OCA\DAVC\Constants;
use OCA\DAVC\Service\CoreService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Connect extends Command {

	public function __construct(
		private IUserManager $userManager,
		private CoreService $CoreService
	) {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('davc:connect')
			->setDescription('Connects a user to a DAV server')
			->setHelp(<<<'HELP'
Connect a Nextcloud user to a DAV server.

The Nextcloud user name is always required.

You can run this command in two connection modes:

  Interactive with auto-discovery/manual questions:
    php occ davc:connect <user>

  Automation with auto-discovery:
	php occ davc:connect <user> <accountid> <accountsecret> --auto-discovery

  Automation with manual server settings:
	php occ davc:connect <user> <accountid> <accountsecret> --provider=<host> --port=<port> --path=<path> --secure-transport=<true|false>

Examples:
	php occ davc:connect alice alice@example.com secret --auto-discovery
	php occ davc:connect alice alice@example.com secret --provider=dav.example.com --port=443 --path=/.well-known/caldav --secure-transport=true

When account credentials are omitted, the command prompts for the missing values.
If auto-discovery is not selected, manual setup requires provider, port, path, and secure transport.
HELP)
			->addArgument('user',
				InputArgument::REQUIRED,
				'User whom to connect to the DAV server')
			->addArgument('accountid',
				InputArgument::OPTIONAL,
				'The username of the account to connect to on the DAV server')
			->addArgument('accountsecret',
				InputArgument::OPTIONAL,
				'The password of the account to connect to on the DAV server')
			->addOption('auto-discovery', null, InputOption::VALUE_NONE, 'Use automatic DAV discovery based on the account ID domain')
			->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Manual DAV server host name or IP address')
			->addOption('port', null, InputOption::VALUE_REQUIRED, 'Manual DAV server port')
			->addOption('path', null, InputOption::VALUE_REQUIRED, 'Manual DAV server path')
			->addOption('secure-transport', null, InputOption::VALUE_REQUIRED, 'Use secure transport for manual setup (true for https, false for http)');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void {
		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		if ($input->getArgument('accountid') === null) {
			$accountIdQuestion = new Question('DAV account ID: ');
			$accountIdQuestion->setValidator(function (?string $answer): string {
				$answer = trim((string)$answer);
				if ($answer === '') {
					throw new \RuntimeException('The DAV account ID is required.');
				}

				return $answer;
			});
			$input->setArgument('accountid', $helper->ask($input, $output, $accountIdQuestion));
		}

		if ($input->getArgument('accountsecret') === null) {
			$accountSecretQuestion = new Question('DAV account secret: ');
			$accountSecretQuestion->setHidden(true);
			$accountSecretQuestion->setHiddenFallback(false);
			$accountSecretQuestion->setValidator(function (?string $answer): string {
				$answer = (string)$answer;
				if ($answer === '') {
					throw new \RuntimeException('The DAV account secret is required.');
				}

				return $answer;
			});
			$input->setArgument('accountsecret', $helper->ask($input, $output, $accountSecretQuestion));
		}

		$manualConfigurationProvided = $this->hasManualConfiguration($input);
		$autoDiscovery = (bool)$input->getOption('auto-discovery');

		if (!$autoDiscovery && !$manualConfigurationProvided) {
			$autoDiscoveryQuestion = new ConfirmationQuestion('Use DAV auto-discovery? [Y/n] ', true);
			$autoDiscovery = $helper->ask($input, $output, $autoDiscoveryQuestion);
			if ($autoDiscovery) {
				$input->setOption('auto-discovery', true);
			}
		}

		if (!$autoDiscovery) {
			if ($input->getOption('provider') === null) {
				$providerQuestion = new Question('DAV server host: ');
				$providerQuestion->setValidator(function (?string $answer): string {
					$answer = trim((string)$answer);
					if ($answer === '') {
						throw new \RuntimeException('The DAV server host is required for manual setup.');
					}

					return $answer;
				});
				$input->setOption('provider', $helper->ask($input, $output, $providerQuestion));
			}

			if ($input->getOption('secure-transport') === null) {
				$secureTransportQuestion = new ConfirmationQuestion('Use secure transport (HTTPS)? [Y/n] ', true);
				$input->setOption('secure-transport', $helper->ask($input, $output, $secureTransportQuestion) ? 'true' : 'false');
			}

			if ($input->getOption('port') === null) {
				$portQuestion = new Question('DAV server port: ');
				$portQuestion->setValidator(function (?string $answer): string {
					$answer = trim((string)$answer);
					if ($answer === '') {
						throw new \RuntimeException('The DAV server port is required for manual setup.');
					}

					$port = filter_var($answer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
					if ($port === false) {
						throw new \RuntimeException('The DAV server port must be a valid integer between 1 and 65535.');
					}

					return (string)$port;
				});
				$input->setOption('port', $helper->ask($input, $output, $portQuestion));
			}

			if ($input->getOption('path') === null) {
				$pathQuestion = new Question('DAV server path: ');
				$pathQuestion->setValidator(function (?string $answer): string {
					$answer = trim((string)$answer);
					if ($answer === '') {
						throw new \RuntimeException('The DAV server path is required for manual setup.');
					}

					if (!str_starts_with($answer, '/')) {
						throw new \RuntimeException('The DAV server path must start with /.');
					}

					return $answer;
				});
				$input->setOption('path', $helper->ask($input, $output, $pathQuestion));
			}
		}
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = (string)$input->getArgument('user');
		$accountBauthId = (string)$input->getArgument('accountid');
		$accountBauthSecret = (string)$input->getArgument('accountsecret');
		$autoDiscovery = (bool)$input->getOption('auto-discovery');
		$accountProvider = trim((string)$input->getOption('provider'));
		$path = $input->getOption('path');
		$port = $input->getOption('port');
		$secureTransport = $input->getOption('secure-transport');
		$flags = [];

		if (!$this->userManager->userExists($uid)) {
			$output->writeln("<error>User $uid does not exist</error>");
			return self::INVALID;
		}

		if ($autoDiscovery) {
			$flags[] = 'AUTO_DISCOVERY';
		}

		if ($autoDiscovery && $this->hasManualConfiguration($input)) {
			$output->writeln('<error>Auto-discovery cannot be combined with manual provider, port, path, or secure transport options.</error>');
			return self::INVALID;
		}

		if (!$autoDiscovery) {
			if ($accountProvider === '' || $path === null || $port === null || $secureTransport === null) {
				$output->writeln('<error>Manual setup requires provider, port, path, and secure transport.</error>');
				return self::INVALID;
			}

			$validatedPort = filter_var((string)$port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
			if ($validatedPort === false) {
				$output->writeln('<error>The DAV server port must be a valid integer between 1 and 65535.</error>');
				return self::INVALID;
			}

			$secureTransportValue = filter_var($secureTransport, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			if ($secureTransportValue === null) {
				$output->writeln('<error>The secure transport option must be true or false.</error>');
				return self::INVALID;
			}

			$path = trim((string)$path);
			if ($path === '' || !str_starts_with($path, '/')) {
				$output->writeln('<error>The DAV server path is required for manual setup and must start with /.</error>');
				return self::INVALID;
			}
		}

		$configuration = [
			'label' => $this->deriveLabel($accountProvider, $accountBauthId),
			'auth' => Constants::AUTHENTICATION_TYPE_BASIC,
			'bauth_id' => $accountBauthId,
			'bauth_secret' => $accountBauthSecret,
		];

		if (!$autoDiscovery) {
			$configuration['location_host'] = $accountProvider;
			$configuration['location_port'] = (int)$port;
			$configuration['location_path'] = (string)$path;
			$configuration['location_protocol'] = filter_var($secureTransport, FILTER_VALIDATE_BOOLEAN) ? 'https' : 'http';
		}

		if (!$this->CoreService->connectAccount($uid, $configuration, $flags)) {
			$output->writeln('<error>Failed to connect user ' . $uid . ' to DAV server ' . ($accountProvider !== '' ? $accountProvider : 'using auto-discovery') . '</error>');
			return self::FAILURE;
		}

		$output->writeln('<info>User ' . $uid . ' connected to DAV server ' . ($accountProvider !== '' ? $accountProvider : 'using auto-discovery') . ' as ' . $accountBauthId . '</info>');

		return self::SUCCESS;
	}

	private function hasManualConfiguration(InputInterface $input): bool {
		return $input->getOption('provider') !== null
			|| $input->getOption('port') !== null
			|| $input->getOption('path') !== null
			|| $input->getOption('secure-transport') !== null;
	}

	private function deriveLabel(string $provider, string $accountId): string {
		if ($provider !== '') {
			return $provider;
		}

		if (str_contains($accountId, '@')) {
			[, $domain] = explode('@', $accountId, 2);
			if ($domain !== '') {
				return $domain;
			}
		}

		return $accountId;
	}
}
