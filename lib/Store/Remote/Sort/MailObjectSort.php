<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Remote\Sort;

use OCA\DAVC\Store\Common\Sort\SortBase;

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
