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
namespace OCA\JMAPC\Store\Remote\Filters;

use DateTimeInterface;
use OCA\JMAPC\Store\Common\Filters\FilterBase;

class MailMessageFilter extends FilterBase {

	protected array $attributes = [
		'in' => true,
		'inOmit' => true,
		'text' => true,
		'from' => true,
		'to' => true,
		'cc' => true,
		'bcc' => true,
		'subject' => true,
		'body' => true,
		'attachmentPresent' => true,
		'tagPresent' => true,
		'tagAbsent' => true,
		'receivedBefore' => true,
		'receivedAfter' => true,
		'sizeMin' => true,
		'sizeMax' => true,
	];

	public function in(string $value): self {
		$this->condition('in', $value);
		return $this;
	}

	public function inOmit(string ...$value): self {
		$this->condition('inOmit', $value);
		return $this;
	}

	public function text(string $value): self {
		$this->condition('text', $value);
		return $this;
	}

	public function from(string $value): self {
		$this->condition('from', $value);
		return $this;
	}

	public function to(string $value): self {
		$this->condition('to', $value);
		return $this;
	}

	public function cc(string $value): self {
		$this->condition('cc', $value);
		return $this;
	}

	public function bcc(string $value): self {
		$this->condition('bcc', $value);
		return $this;
	}

	public function subject(string $value): self {
		$this->condition('subject', $value);
		return $this;
	}

	public function body(string $value): self {
		$this->condition('body', $value);
		return $this;
	}

	public function attachmentPresent(bool $value): self {
		$this->condition('attachmentPresent', $value);
		return $this;
	}

	public function tagPresent(string $value): self {
		$this->condition('tagPresent', $value);
		return $this;
	}

	public function tagAbsent(string $value): self {
		$this->condition('tagAbsent', $value);
		return $this;
	}

	public function receivedBefore(DateTimeInterface $value): self {
		$this->condition('receivedBefore', $value);
		return $this;
	}

	public function receivedAfter(DateTimeInterface $value): self {
		$this->condition('receivedAfter', $value);
		return $this;
	}

	public function sizeMin(int $value): self {
		$this->condition('sizeMin', $value);
		return $this;
	}

	public function sizeMax(int $value): self {
		$this->condition('sizeMax', $value);
		return $this;
	}
	
}
