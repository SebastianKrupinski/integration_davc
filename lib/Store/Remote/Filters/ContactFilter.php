<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Filters;

use OCA\DAVC\Store\Common\Filters\FilterBase;

class ContactFilter extends FilterBase {

	protected array $attributes = [
		'createBefore' => true,
		'createAfter' => true,
		'modifiedBefore' => true,
		'modifiedAfter' => true,
		'uid' => true,
		'kind' => true,
		'member' => true,
		'text' => true,
		'name' => true,
		'nameGiven' => true,
		'nameSurname' => true,
		'nameAlias' => true,
		'organization' => true,
		'email' => true,
		'phone' => true,
		'address' => true,
		'note' => true,
	];

}
