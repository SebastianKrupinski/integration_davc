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

namespace OCA\JMAPC\Store\Local;

use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Range\IRange;
use OCA\JMAPC\Store\Common\Range\IRangeDate;
use OCA\JMAPC\Store\Common\Sort\ISort;
use OCP\IDBConnection;

class EventStore extends BaseStore {

	public function __construct(IDBConnection $store) {
		$this->_Store = $store;
		$this->_CollectionTable = 'jmapc_collections';
		$this->_CollectionIdentifier = 'EC';
		$this->_CollectionClass = 'OCA\JMAPC\Store\Local\CollectionEntity';
		$this->_EntityTable = 'jmapc_entities_event';
		$this->_EntityIdentifier = 'EE';
		$this->_EntityClass = 'OCA\JMAPC\Store\Local\EventEntity';
		$this->_ChronicleTable = 'jmapc_chronicle';
	}

	/**
	 * retrieve entities from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $elements data fields
	 * @param IFilter $filter filter options
	 * @param IRange $range range options
	 * @param ISort $sort sort options
	 *
	 * @return array of entities
	 */
	public function entityList(?IFilter $filter = null, ?ISort $sort = null, ?IRange $range = null, ?array $elements = null): array {
		// evaluate if specific elements where requested
		if (!is_array($elements)) {
			$elements = ['*'];
		}
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select($elements)
			->from($this->_EntityTable);
		// apply range
		if ($range instanceof IRangeDate) {
			// date range filter
			// case 1: event starts and ends within range
			// case 2: event starts before range and ends within range
			// case 3: event starts within range and ends after range
			// case 4: event starts before range and ends after range
			$rangerStart = $range->getStart()->format('U');
			$rangerEnd = $range->getEnd()->format('U');
			$cmd->andWhere($cmd->expr()->orX(
				// case 1
				$cmd->expr()->andX(
					$cmd->expr()->gte('startson', $cmd->createNamedParameter($rangerStart)),
					$cmd->expr()->lte('endson', $cmd->createNamedParameter($rangerEnd)),
				),
				// case 2
				$cmd->expr()->andX(
					$cmd->expr()->lt('startson', $cmd->createNamedParameter($rangerStart)),
					$cmd->expr()->gte('endson', $cmd->createNamedParameter($rangerStart)),
					$cmd->expr()->lte('endson', $cmd->createNamedParameter($rangerEnd))
				),
				// case 3
				$cmd->expr()->andX(
					$cmd->expr()->gte('startson', $cmd->createNamedParameter($rangerStart)),
					$cmd->expr()->lte('startson', $cmd->createNamedParameter($rangerEnd)),
					$cmd->expr()->gt('endson', $cmd->createNamedParameter($rangerEnd))
				),
				// case 4
				$cmd->expr()->andX(
					$cmd->expr()->lt('startson', $cmd->createNamedParameter($rangerStart)),
					$cmd->expr()->gt('endson', $cmd->createNamedParameter($rangerEnd))
				)
			));
		}
		// apply filters
		if ($filter instanceof IFilter) {
			$this->fromFilter($cmd, $filter);
		}
		// apply sort
		if ($sort instanceof ISort) {
			$this->fromSort($cmd, $sort);
		}
		// execute command
		$rsl = $cmd->executeQuery();
		$entities = [];
		try {
			while ($data = $rsl->fetch()) {
				$entities[] = $this->toEntity($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}
	}

}
