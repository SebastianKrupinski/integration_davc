<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Sort;

use OCA\DAVC\Store\Common\Sort\ISort;

class SortBase implements ISort {

	protected array $attributes = [];
	protected array $conditions = [];

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,bool>
	 */
	public function attributes(): array {
		return $this->attributes;
	}

	/**
	 *
	 * @since 1.0.0
	 * 
	 * @param string $attribute attribute name
	 * @param bool $direction true for ascending, false for descending
	 */
	public function condition(string $attribute, bool $direction): void {
		if (isset($this->attributes[$attribute])) {
			$this->conditions[$attribute] = [
				'attribute' => $attribute,
				'direction' => $direction,
			];
		}
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array{attribute:string, direction:bool}>
	 */
	public function conditions(): array {
		return $this->conditions;
	}

}
