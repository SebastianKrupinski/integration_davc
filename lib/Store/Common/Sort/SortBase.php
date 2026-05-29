<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Sort;

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
	 * @param string $property attribute name
	 * @param bool $direction true for ascending, false for descending
	 */
	public function condition(string $property, bool $direction): void {
		if (isset($this->attributes[$property])) {
			$this->conditions[$property] = [
				'attribute' => $property,
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
