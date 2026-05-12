<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Sort;

use OCA\DAVC\Store\Common\Sort\SortBase;

class MailCollectionSort extends SortBase {

	protected array $attributes = [
		'name' => true,
		'order' => true,
	];

}
