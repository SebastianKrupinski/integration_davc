<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Filters;

class FilterBase implements IFilter {

	protected array $attributes = [];
	protected array $conditions = [];

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function attributes(): array {
		return $this->attributes;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function comparators(): FilterComparisonOperator {
		return FilterComparisonOperator::EQ;
	}

	/**
	 *
	 * @since 1.0.0
	 */
	public function conjunctions(): FilterConjunctionOperator {
		return FilterConjunctionOperator::NONE;
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 */
	public function condition(string $property, mixed $value, FilterComparisonOperator $comparator = FilterComparisonOperator::EQ, FilterConjunctionOperator $conjunction = FilterConjunctionOperator::AND): void {
		if (!isset($this->attributes[$property])) {
			$this->conditions[] = [
				'attribute' => $property,
				'value' => $value,
				'comparator' => $comparator,
				'conjunction' => $conjunction,
			];
		}
	}

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{attribute:string, value:mixed, comparator:FilterComparisonOperator, conjunction:FilterConjunctionOperator}>
	 */
	public function conditions(): array {
		return $this->conditions;
	}

}
