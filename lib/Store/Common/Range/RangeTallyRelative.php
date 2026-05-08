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
namespace OCA\JMAPC\Store\Common\Range;

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
