<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Sort;

interface ISort {
	
	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,bool>
	 */
	public function attributes(): array;

	/**
	 *
	 * @since 1.0.0
	 * 
	 * @param string $attribute attribute name
	 * @param bool $direction true for ascending, false for descending
	 */
	public function condition(string $property, bool $direction): void;

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array{attribute:string,direction:bool}>
	 */
	public function conditions(): array;

}
