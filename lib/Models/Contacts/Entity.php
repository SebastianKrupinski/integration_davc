<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models\Contacts;

use OCA\DAVC\Models\OriginTypes;
use Sabre\VObject\Component\VCard;

class Entity {

	public OriginTypes|null $Origin = null;		// System
	public string|null $ID = null;              // System Entity Id
	public string|null $CID = null;             // System Collection Id
	public string|null $Signature = null;       // System Entity Signature
	public string|null $CCID = null;            // Correlation Collection Id
	public string|null $CEID = null;            // Correlation Entity Id
	public string|null $CESN = null;            // Correlation Signature
	public VCard|null $data = null;             // Contact Data

}
