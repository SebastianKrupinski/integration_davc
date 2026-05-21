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
 * @method getUid(): string
 * @method setUid(string $uid): void
 * @method getSid(): string
 * @method setSid(int $sid): void
 * @method getCid(): string
 * @method setCid(int $cid): void
 * @method getUuid(): string
 * @method setUuid(string $uuid): void
 * @method getSignature(): string
 * @method setSignature(string $signature): void
 * @method getCcid(): string
 * @method setCcid(string $ccid): void
 * @method getCeid(): string
 * @method setCeid(string $ceid): void
 * @method getCesn(): string
 * @method setCesn(string $cesn): void
 * @method getData(): string
 * @method setData(string $data): void
 * @method getLabel(): string
 * @method setLabel(string $label): void
 * @method getDescription(): string
 * @method setDescription(string $description): void
 * @method getStartson(): int
 * @method setStartson(int $startson): void
 * @method getEndson(): int
 * @method setEndson(int $endson): void
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
