<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\JMAPC\Store\Local;

use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUID(): string
 * @method setUID(string $uid): void
 * @method getSID(): string
 * @method setSID(int $sid): void
 * @method getCID(): string
 * @method setCID(int $cid): void
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
 */
class ContactEntity extends Entity {
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
