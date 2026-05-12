<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local\Sort;

use OCA\DAVC\Store\Common\Sort\SortBase;

class ContactSort extends SortBase {
	
	protected array $attributes = [
		'uid' => true,
		'sid' => true,
		'cid' => true,
		'uid' => true,
		'uuid' => true,
		'label' => true,
	];

}
