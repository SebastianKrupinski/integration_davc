<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local;

/**
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
 */
class ContactEntity extends BaseEntity {
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
}
