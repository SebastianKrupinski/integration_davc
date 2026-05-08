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
namespace OCA\JMAPC\Store\Remote\Sort;

use OCA\JMAPC\Store\Common\Sort\SortBase;

class MailObjectSort extends SortBase {

	protected array $attributes = [
		'received' => true,
		'sent' => true,
		'from' => true,
		'to' => true,
		'subject ' => true,
		'size' => true,
		'keyword' => true,
	];

	public function received(bool $direction): self {
		$this->condition('received', $direction);
		return $this;
	}

	public function sent(bool $direction): self {
		$this->condition('sent', $direction);
		return $this;
	}

	public function from(bool $direction): self {
		$this->condition('from', $direction);
		return $this;
	}

	public function to(bool $direction): self {
		$this->condition('to', $direction);
		return $this;
	}

	public function subject(bool $direction): self {
		$this->condition('subject', $direction);
		return $this;
	}

	public function size(bool $direction): self {
		$this->condition('size', $direction);
		return $this;
	}

	public function tag(bool $direction): self {
		$this->condition('tag', $direction);
		return $this;
	}

}
