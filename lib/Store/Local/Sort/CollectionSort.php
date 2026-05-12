<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local\Sort;

use OCA\DAVC\Store\Common\Sort\SortBase;

class CollectionSort extends SortBase {
	
	protected array $attributes = [
		'id' => true,
		'uid' => true,
		'sid' => true,
		'type' => true,
		'ccid' => true,
		'uuid' => true,
		'label' => true,
		'color' => true,
		'visible' => true,
	];

}
