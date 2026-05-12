<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Sort;

use OCA\DAVC\Store\Common\Sort\SortBase;

class EventSort extends SortBase {

	protected array $attributes = [
		'created' => true,
		'modified' => true,
		'start' => true,
		'uid' => true,
		'recurrence' => true,
	];

}
