<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Filters;

use DateTimeInterface;
use OCA\DAVC\Store\Common\Filters\FilterBase;

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
