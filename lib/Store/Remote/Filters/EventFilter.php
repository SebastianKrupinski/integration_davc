<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Filters;

use OCA\DAVC\Store\Common\Filters\FilterBase;

class EventFilter extends FilterBase {

	protected array $attributes = [
		'before' => true,
		'after' => true,
		'uid' => true,
		'text' => true,
		'title' => true,
		'description' => true,
		'location' => true,
		'owner' => true,
		'attendee' => true,
	];

}
