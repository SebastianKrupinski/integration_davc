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

use OCA\JMAPC\Store\Common\Filters\FilterBase;
use OCA\JMAPC\Store\Common\Filters\FilterComparisonOperator;
use OCA\JMAPC\Store\Common\Filters\FilterConjunctionOperator;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Sort\ISort;
use OCA\JMAPC\Store\Common\Sort\SortBase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ServicesStore {

	protected IDBConnection $_Store;
	protected string $_EntityTable = 'jmapc_services';
	protected string $_EntityClass = 'OCA\JMAPC\Store\Local\ServiceEntity';

	public function __construct(IDBConnection $store) {
		$this->_Store = $store;
	}

	protected function toEntity(array $row): ServiceEntity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($this->_EntityClass . '::fromRow', $row);
	}

	protected function fromFilter(IQueryBuilder $cmd, IFilter $filter): void {
		foreach ($filter->conditions() as $entry) {
			$comparison = match ($entry['comparator']) {
				FilterComparisonOperator::EQ => $cmd->expr()->eq($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::GT => $cmd->expr()->gt($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LT => $cmd->expr()->lt($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::GTE => $cmd->expr()->gte($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LTE => $cmd->expr()->lte($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NEQ => $cmd->expr()->neq($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::IN => $cmd->expr()->in($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NIN => $cmd->expr()->notIn($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::LIKE => $cmd->expr()->like($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
				FilterComparisonOperator::NLIKE => $cmd->expr()->notLike($entry['attribute'], $cmd->createNamedParameter($entry['value'])),
			};
			if ($entry['conjunction'] === FilterConjunctionOperator::AND) {
				$cmd->andWhere($comparison);
			} elseif ($entry['conjunction'] === FilterConjunctionOperator::OR) {
				$cmd->orWhere($comparison);
			} else {
				$cmd->where($comparison);
			}
		}
	}

	protected function fromSort(IQueryBuilder $cmd, ISort $sort): void {
		foreach ($sort->conditions() as $entry) {
			$cmd->addOrderBy($entry['attribute'], $entry['direction'] ? 'ASC' : 'DESC');
		}
	}

	/**
	 * retrieve services for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param IFilter $filter filter options
	 * @param ISort $sort sort options
	 *
	 * @return array<int,ServiceEntity>
	 */
	public function list(?IFilter $filter = null, ?ISort $sort = null): array {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable);
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
				$entities[$data['id']] = $this->toEntity($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}
	}

	public function listFilter(): IFilter {
		return new FilterBase();
	}

	public function listSort(): ISort {
		return new SortBase();
	}

	/**
	 * confirm entity exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id entity id
	 *
	 * @return int|false entry id on success / false on failure
	 */
	public function confirm(int $id): int|false {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$data = $cmd->executeQuery()->fetch();
		$cmd->executeQuery()->closeCursor();
		// evaluate if anything was found
		if (is_array($data) && count($data) > 0) {
			return (int)$data['id'];
		} else {
			return false;
		}

	}

	/**
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id entity id
	 *
	 * @return ServiceEntity|null
	 */
	public function fetch(int $id): ?ServiceEntity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if (is_array($entity)) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * create a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param ServiceEntity $entity
	 *
	 * @return ServiceEntity
	 */
	public function create(ServiceEntity $entity): ServiceEntity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_EntityTable);
		// assign values
		foreach (array_keys($entity->getUpdatedFields()) as $property) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();
			$cmd->setValue($column, $cmd->createNamedParameter($value));
		}
		// execute command
		$cmd->executeStatement();
		// determine if id needs to be assigned
		if ($entity->id === null) {
			$entity->setId($cmd->getLastInsertId());
		}

		$entity->resetUpdatedFields();

		return $entity;
		
	}
	
	/**
	 * modify a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param ServiceEntity $entity
	 *
	 * @return ServiceEntity
	 */
	public function modify(ServiceEntity $entity): ServiceEntity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// assign values
		if (count($entity->getUpdatedFields())) {
			foreach (array_keys($entity->getUpdatedFields()) as $property) {
				$column = $entity->propertyToColumn($property);
				$getter = 'get' . ucfirst($property);
				$value = $entity->$getter();
				$cmd->set($column, $cmd->createNamedParameter($value));
			}
			// execute command
			$cmd->executeStatement();
			// determine if id needs to be assigned
			if ($entity->id === null) {
				$entity->setId($cmd->getLastInsertId());
			}
		}

		$entity->resetUpdatedFields();
		
		return $entity;
		
	}

	/**
	 * delete a entity from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param ServiceEntity $entity
	 *
	 * @return ServiceEntity
	 */
	public function delete(ServiceEntity $entity): ServiceEntity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();

		// return result
		return $entity;
		
	}

	/**
	 * delete services for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return mixed
	 */
	public function deleteByUser(string $uid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

}
