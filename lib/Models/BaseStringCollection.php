<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models;

class BaseStringCollection extends BaseCollection {
	public function __construct($data = []) {
		parent::__construct('string', $data);
	}
}
