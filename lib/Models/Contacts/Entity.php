<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models\Contacts;

class Entity {

	public ?int $localCollectionId = null;
	public ?int $localEntityId = null;
	public ?string $localSignature = null;
	public ?string $remoteCollectionId = null;
	public ?string $remoteEntityId = null;
	public ?string $remoteSignature = null;
	public ?string $correlationSignature = null;
	public ?string $uuid = null;
	public ?string $data = null;

}
