<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Filters;

interface IFilter {

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function attributes(): array;

	/**
	 *
	 * @since 1.0.0
	 */
	public function comparators(): FilterComparisonOperator;

	/**
	 *
	 * @since 1.0.0
	 */
	public function conjunctions(): FilterConjunctionOperator;

	/**
	 *
	 * @since 1.0.0
	 *
	 */
	public function condition(string $property, mixed $value, FilterComparisonOperator $comparator = FilterComparisonOperator::EQ, FilterConjunctionOperator $conjunction = FilterConjunctionOperator::AND): void;

	/**
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{attribute:string, value:mixed, comparator:FilterComparisonOperator, conjunction:FilterConjunctionOperator}>
	 */
	public function conditions(): array;

}
