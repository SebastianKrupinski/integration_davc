<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Range;

use DateTimeInterface;

interface IRangeDate extends IRange {

	/**
	 *
	 * @since 1.0.0
	 */
	public function getStart(): DateTimeInterface;

	/**
	 *
	 * @since 1.0.0
	 */
	public function setStart(DateTimeInterface $value): void;

	/**
	 *
	 * @since 1.0.0
	 */
	public function getEnd(): DateTimeInterface;

	/**
	 *
	 * @since 1.0.0
	 */
	public function setEnd(DateTimeInterface $value): void;

}
