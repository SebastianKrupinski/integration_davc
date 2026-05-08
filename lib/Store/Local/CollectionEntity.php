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

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method getId(): int
 * @method getUID(): ?string
 * @method setUID(string $uid): void
 * @method getSID(): ?string
 * @method setSID(string $sid): void
 * @method getType(): ?string
 * @method setType(string $type): void
 * @method getCCID(): ?string
 * @method setCCID(string $ccid): void
 * @method getUUID(): ?string
 * @method setUUID(string $uuid): void
 * @method getLabel(): ?string
 * @method setLabel(string $label): void
 * @method getColor(): ?string
 * @method setColor(string $color): void
 * @method getVisible(): ?string
 * @method setVisible(string $visible): void
 * @method getHISN(): ?string
 * @method setHISN(string $hisn): void
 * @method getHESN(): ?string
 * @method setHESN(string $hesn): void
 * @method getHLock(): ?int
 * @method setHLock(int $status): void
 * @method getHLockHD(): ?int
 * @method setHLockHD(int $id): void
 * @method getHLockHB(): ?int
 * @method setHLockHB(int $timestamp): void
 */
class CollectionEntity extends Entity implements JsonSerializable {
	protected ?string $uid = null;
	protected ?int $sid = null;
	protected ?string $type = null;
	protected ?string $ccid = null;
	protected ?string $uuid = null;
	protected ?string $label = null;
	protected ?string $color = null;
	protected ?int $visible = 1;
	protected ?string $hisn = null;
	protected ?string $hesn = null;
	protected ?int $hlock = 0;
	protected ?int $hlockhd = 0;
	protected ?int $hlockhb = 0;
		
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'sid' => $this->sid,
			'type' => $this->type,
			'ccid' => $this->ccid,
			'uuid' => $this->uuid,
			'label' => $this->label,
			'color' => $this->color,
			'visible' => $this->visible,
			'hisn' => $this->hisn,
			'hesn' => $this->hesn,
			'hlock' => $this->hlock,
			'hlockhd' => $this->hlockhd,
			'hlockhb' => $this->hlockhb,
		];
	}
}
