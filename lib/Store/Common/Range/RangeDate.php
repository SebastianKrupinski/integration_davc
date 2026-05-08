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

use DateTime;
use DateTimeInterface;

class RangeDate implements IRangeDate {

	public function __construct(
		protected ?DateTimeInterface $start = null,
		protected ?DateTimeInterface $end = null,
	) {
		
		if ($start === null) {
			$start = new DateTime();
		}
		if ($end === null) {
			$end = new DateTime();
		}
		$this->start = $start;
		$this->end = $end;

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
