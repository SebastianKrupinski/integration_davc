<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\JMAPC\Store\Common\Filters;

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
