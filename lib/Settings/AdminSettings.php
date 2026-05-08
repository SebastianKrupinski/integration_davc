<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Settings;

use OCA\JMAPC\AppInfo\Application;
use OCA\JMAPC\Service\ConfigurationService;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {

	public function __construct(
		private IInitialState $initialStateService,
		private ConfigurationService $ConfigurationService
	) {}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-AdminSettings');

		// retrieve user configuration
		$configuration = $this->ConfigurationService->retrieveSystem();
		
		$this->initialStateService->provideInitialState('admin-configuration', $configuration);

		return new TemplateResponse(Application::APP_ID, 'AdminSettings');
	}

	public function getSection(): string {
		return 'integration-jmapc';
	}

	public function getPriority(): int {
		return 10;
	}
}
