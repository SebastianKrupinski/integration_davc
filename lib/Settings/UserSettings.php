<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Settings;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Service\ConfigurationService;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class UserSettings implements ISettings {

	public function __construct(
		private IInitialState $initialStateService,
		private ConfigurationService $configurationService,
		private string $userId,
	) {}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-UserSettings');

		// retrieve system configuration
		$configuration['system_contacts'] = $this->configurationService->isContactsAppAvailable();
		$configuration['system_events'] = $this->configurationService->isCalendarAppAvailable();
		
		$this->initialStateService->provideInitialState('system-configuration', $configuration);

		return new TemplateResponse(Application::APP_ID, 'UserSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 20;
	}
}
