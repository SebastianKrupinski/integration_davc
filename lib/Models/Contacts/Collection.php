<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models\Contacts;

class Collection {

	public string $Id;
	public string|null $Label = null;
	public string|null $Description = null;
	public int|null $Priority = null;
	public bool|null $Visibility = null;
	public string|null $Color = null;
	public string|null $Signature = null;

}
