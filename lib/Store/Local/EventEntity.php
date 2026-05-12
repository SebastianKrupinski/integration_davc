<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local;

use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUID(): string
 * @method setUID(string $uid): void
 * @method getSID(): string
 * @method setSID(int $sid): void
 * @method getCID(): string
 * @method setCID(int $sid): void
 * @method getUUID(): string
 * @method setUUID(string $uuid): void
 * @method getSignature(): string
 * @method setSignature(string $uuid): void
 * @method getCCID(): string
 * @method setCCID(string $ccid): void
 * @method getCEID(): string
 * @method setCEID(string $ceid): void
 * @method getCESN(): string
 * @method setCESN(string $cesn): void
 * @method getData(): string
 * @method setData(string $data): void
 * @method getLabel(): string
 * @method setLabel(string $label): void
 * @method getDescription(): string
 * @method setDescription(string $description): void
 * @method getStartsOn(): string
 * @method setStartsOn(int $startson): void
 * @method getEndsOn(): string
 * @method setEndsOn(int $endson): void
 */
class EventEntity extends Entity {
	protected ?string $uid = null;
	protected ?int $sid = null;
	protected ?int $cid = null;
	protected ?string $uuid = null;
	protected ?string $signature = null;
	protected ?string $ccid = null;
	protected ?string $ceid = null;
	protected ?string $cesn = null;
	protected ?string $data = null;
	protected ?string $label = null;
	protected ?string $description = null;
	protected ?int $startson = null;
	protected ?int $endson = null;
}
