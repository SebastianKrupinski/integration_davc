<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use OCA\DAVC\Store\Local\ServicesTemplateStore;

class ServicesTemplateService {
	private ServicesTemplateStore $_Store;

	public function __construct(ServicesTemplateStore $store) {

		$this->_Store = $store;

	}

	public function findByDomain(string $domain): array {

		return $this->_Store->fetchByDomain($domain);

	}

}
