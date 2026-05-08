<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\JMAPC\Migration;

use OCA\JMAPC\Store\Local\ServicesTemplateStore;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DefaultServiceTemplates implements IRepairStep {

	public function __construct(
		private ServicesTemplateStore $store
	) {}

	public function getName(): string {
		return 'Create or update default Jmap Client service templates';
	}

	/**
	 * @return void
	 */
	public function run(IOutput $output) {
		// load the default service templates from file
		$defaultTemplates = json_decode(file_get_contents(__DIR__ . '/Defaults/ServiceTemplates.json'), true);
		// create or update the service templates in the database
		foreach ($defaultTemplates as $key => $value) {
			$existingTemplate = $this->store->fetchById($key);
			if ($existingTemplate) {
				$this->store->modify($key, $value['domain'], $value['connection']);
			} else {
				$this->store->create($key, $value['domain'], $value['connection']);
			}
		}
	}
	
}
