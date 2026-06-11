<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Range;

use DateTime;
use DateTimeInterface;

class RangeDate implements IRangeDate {

	protected DateTimeInterface $start;
	protected DateTimeInterface $end;

	public function __construct(
		?DateTimeInterface $start = null,
		?DateTimeInterface $end = null,
	) {
		$this->start = $start ?? new DateTime();
		$this->end = $end ?? new DateTime();
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function type(): string {
		return 'date';
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getStart(): DateTimeInterface {
		return $this->start;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setStart(DateTimeInterface $value): void {
		$this->start = $value;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function getEnd(): DateTimeInterface {
		return $this->end;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function setEnd(DateTimeInterface $value): void {
		$this->end = $value;
	}

}
