<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Range;

class RangeTallyRelative implements IRangeTally {

	public function __construct(
		protected string|int $position = 0,
		protected string|int $count = 32,
	) {	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string {
		return 'tally';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function anchor(): RangeAnchorType {
		return RangeAnchorType::RELATIVE;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getPosition(): string|int {
		return $this->position;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setPosition(string|int $value): void {
		$this->position = $value;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getCount(): int {
		return $this->count;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setCount(int $value): void {
		$this->count = $value;
	}

}
