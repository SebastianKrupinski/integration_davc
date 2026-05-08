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

use OC\DB\QueryBuilder\Literal;
use OCA\JMAPC\Store\Common\Filters\FilterBase;
use OCA\JMAPC\Store\Common\Filters\FilterComparisonOperator;
use OCA\JMAPC\Store\Common\Filters\FilterConjunctionOperator;
use OCA\JMAPC\Store\Common\Filters\IFilter;
use OCA\JMAPC\Store\Common\Range\IRange;
use OCA\JMAPC\Store\Common\Range\RangeAnchorType;
use OCA\JMAPC\Store\Common\Range\RangeDate;
use OCA\JMAPC\Store\Common\Range\RangeTallyAbsolute;
use OCA\JMAPC\Store\Common\Range\RangeTallyRelative;
use OCA\JMAPC\Store\Common\Range\RangeType;
use OCA\JMAPC\Store\Common\Sort\ISort;
use OCA\JMAPC\Store\Common\Sort\SortBase;
use OCA\JMAPC\Store\Local\Filters\CollectionFilter;
use OCA\JMAPC\Store\Local\Sort\CollectionSort;
use OCP\AppFramework\Db\Entity;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class BaseStore {

	protected IDBConnection $_Store;
	protected string $_CollectionTable = '';
	protected string $_CollectionIdentifier = '';
	protected string $_CollectionClass = '';
	protected string $_EntityTable = '';
	protected string $_EntityIdentifier = '';
	protected string $_EntityClass = '';
	protected string $_ChronicleTable = '';

	protected function toCollection(array $row): Entity {
		unset($row['DOCTRINE_ROWNUM']); // remove doctrine/dbal helper column
		return \call_user_func($this->_CollectionClass . '::fromRow', $row);
	}

	protected function toEntity(array $row): Entity {
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
	 * retrieve collections from data store
	 *
	 * @since Release 1.0.0
	 * 
	 * @param IFilter $filter filter options
	 * @param ISort $sort sort options
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionList(?IFilter $filter = null, ?ISort $sort = null): array {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_CollectionTable)
			->where($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
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
				$entities[] = $this->toCollection($data);
			}
			return $entities;
		} finally {
			$rsl->closeCursor();
		}
	}

	/**
	 * retrieve instance of collection filter
	 * 
	 * @since 1.0.0
	 * 
	 * @return CollectionFilter
	 */
	public function collectionListFilter(): IFilter {
		return new CollectionFilter();
	}

	/**
	 * retrieve instance of collection sort
	 * 
	 * @since 1.0.0
	 * 
	 * @return CollectionSort
	 */
	public function collectionListSort(): ISort {
		return new CollectionSort();
	}

	/**
	 * retrieve collections for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionListByUser(string $uid): array {
		// construct filter
		$filter = $this->collectionListFilter();
		$filter->condition('uid', $uid, FilterComparisonOperator::EQ, FilterConjunctionOperator::AND);
		// fetch collections
		return $this->collectionList($filter);
	}

	/**
	 * retrieve collections for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $sid service id
	 *
	 * @return array<int, CollectionEntity>
	 */
	public function collectionListByService(int $sid): array {
		// construct filter
		$filter = $this->collectionListFilter();
		$filter->condition('sid', $sid, FilterComparisonOperator::EQ, FilterConjunctionOperator::AND);
		// fetch collections
		return $this->collectionList($filter);
	}

	/**
	 * retrieve collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return CollectionEntity
	 */
	public function collectionFetch(int $id): ?CollectionEntity {
		// construct filter
		$filter = $this->collectionListFilter();
		$filter->condition('id', $id, FilterComparisonOperator::EQ, FilterConjunctionOperator::AND);
		// fetch collections
		$collection = $this->collectionList($filter);
		if (count($collection) > 0) {
			return $collection[0];
		} else {
			return null;
		}
	}

	/**
	 * fresh instance of a collection entity
	 *
	 * @since Release 1.0.0
	 *
	 * @return CollectionEntity
	 */
	public function collectionFresh(): CollectionEntity {
		return new $this->_CollectionClass;
	}

	/**
	 * create a collection entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionCreate(CollectionEntity $entity): CollectionEntity {
		// force type
		$entity->setType($this->_CollectionIdentifier);
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_CollectionTable);
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
	 * modify a collection entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionModify(CollectionEntity $entity): CollectionEntity {
		// force type
		$entity->setType($this->_CollectionIdentifier);
		// construct command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->update($this->_CollectionTable)
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
	 * delete a collection entry from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param CollectionEntity $entity
	 *
	 * @return CollectionEntity
	 */
	public function collectionDelete(CollectionEntity $entity): CollectionEntity {
		// remove entities
		$this->entityDeleteByCollection($entity->getId());
		// remove chronicle
		$this->chronicleExpungeByCollection($entity->getId());
		// remove collection
		// construct command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();
		
		return $entity;
	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return mixed
	 */
	public function collectionDeleteById(int $id): mixed {
		// remove entities
		$this->entityDeleteByCollection($id);
		// remove chronicle
		$this->chronicleExpungeByCollection($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id user id
	 *
	 * @return mixed
	 */
	public function collectionDeleteByUser(string $id): mixed {
		// remove entities
		$this->entityDeleteByUser($id);
		// remove chronicle
		$this->chronicleExpungeByUser($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($id)))
			->andWhere($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete collections for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id service id
	 *
	 * @return mixed
	 */
	public function collectionDeleteByService(int $id): mixed {
		// remove entities
		$this->entityDeleteByService($id);
		// remove chronicle
		$this->chronicleExpungeByService($id);
		// remove collection
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_CollectionTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($id)))
			->andWhere($cmd->expr()->eq('type', $cmd->createNamedParameter($this->_CollectionIdentifier)));
		// execute command and return result
		return $cmd->executeStatement();
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

	/**
	 * retrieve instance of entity filter
	 *
	 * @since Release 1.0.0
	 *
	 * @return IFilter
	 */
	public function entityListFilter(): IFilter {
		return new FilterBase();
	}

	/**
	 * retrieve instance of entity sort
	 *
	 * @since Release 1.0.0
	 *
	 * @return ISort
	 */
	public function entityListSort(): ISort {
		return new SortBase();
	}

	/**
	 * retrieve instance of entity range
	 *
	 * @since Release 1.0.0
	 *
	 * @param RangeType $type range type
	 * @param RangeAnchorType $anchor range anchor type
	 *
	 * @return IRange
	 */
	public function entityListRange(?RangeType $type = null, ?RangeAnchorType $anchor = null): IRange {
		if ($type === RangeType::DATE) {
			return new RangeDate();
		}
		if ($anchor === RangeAnchorType::RELATIVE) {
			return new RangeTallyRelative();
		} else {
			return new RangeTallyAbsolute();
		}

	}

	/**
	 * retrieve entities for specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return array of entities
	 */
	public function entityListByUser(string $uid): array {
		// construct filter
		$filter = $this->entityListFilter();
		$filter->condition('uid', $uid, FilterComparisonOperator::EQ, FilterConjunctionOperator::NONE);
		// fetch entities
		return $this->entityList($filter);
	}

	/**
	 * retrieve entities for specific user and collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 *
	 * @return array of entities
	 */
	public function entityListByCollection(int $cid): array {
		// construct filter
		$filter = $this->entityListFilter();
		$filter->condition('cid', $cid, FilterComparisonOperator::EQ, FilterConjunctionOperator::NONE);
		// fetch entities
		return $this->entityList($filter);
	}

	/**
	 * confirm entity exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id entity id
	 *
	 * @return int|bool entry id on success / false on failure
	 */
	public function entityConfirm(int $id): int|bool {

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
	 * check if entity exists in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $uuid entity uuid
	 *
	 * @return int|bool entry id on success / false on failure
	 */
	public function entityConfirmByUUID(int $cid, string $uuid): int|bool {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('id')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
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
	 * @return Entity|null
	 */
	public function entityFetch(int $id): ?Entity {

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
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $uuid entity uuid
	 *
	 * @return Entity|null
	 */
	public function entityFetchByUUID(int $cid, string $uuid): ?Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('uuid', $cmd->createNamedParameter($uuid)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if ($entity) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * retrieve entity from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $ccid correlation collection id
	 * @param string $ceid correlation entity id
	 *
	 * @return Entity|null
	 */
	public function entityFetchByCorrelation(int $cid, string $ccid, string $ceid): ?Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('*')
			->from($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('ccid', $cmd->createNamedParameter($ccid)))
			->andWhere($cmd->expr()->eq('ceid', $cmd->createNamedParameter($ceid)));
		// execute command
		$rsl = $cmd->executeQuery();
		try {
			$entity = $rsl->fetch();
			if ($entity) {
				return $this->toEntity($entity);
			} else {
				return null;
			}
		} finally {
			$rsl->closeCursor();
		}

	}

	/**
	 * fresh instance of a entity
	 *
	 * @since Release 1.0.0
	 *
	 * @return Entity
	 */
	public function entityFresh(): Entity {

		return new $this->_EntityClass;
		
	}

	/**
	 * create a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityCreate(Entity $entity): Entity {

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
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 1);

		$entity->resetUpdatedFields();

		return $entity;
		
	}
	
	/**
	 * modify a entity entry in the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityModify(Entity $entity): Entity {

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
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 2);
		
		$entity->resetUpdatedFields();
		
		return $entity;
		
	}

	/**
	 * delete a entity from the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param Entity $entity
	 *
	 * @return Entity
	 */
	public function entityDelete(Entity $entity): Entity {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($entity->getId())));
		// execute command
		$cmd->executeStatement();
		// chronicle operation
		$this->chronicleDocument($entity->getUid(), $entity->getSid(), $entity->getCid(), $entity->getId(), $entity->getUuid(), 3);
		// return result
		return $entity;
		
	}

	/**
	 * delete entity by id
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $id entity id
	 *
	 * @return mixed
	 */
	public function entityDeleteById(int $id): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('id', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 *
	 * @return mixed
	 */
	public function entityDeleteByUser(string $uid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($uid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific service from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $sid service id
	 *
	 * @return mixed
	 */
	public function entityDeleteByService(int $sid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($sid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * delete entities for a specific collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 *
	 * @return mixed
	 */
	public function entityDeleteByCollection(int $cid): mixed {

		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_EntityTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)));
		// execute command and return result
		return $cmd->executeStatement();

	}

	/**
	 * chronicle a operation to an entity to the data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid user id
	 * @param int $sid service id
	 * @param string $cid collection id
	 * @param string $eid entity id
	 * @param string $euuid entity uuid
	 * @param string $operation operation type (1 - Created, 2 - Modified, 3 - Deleted)
	 *
	 * @return string
	 */
	public function chronicleDocument(string $uid, int $sid, int $cid, int $eid, string $euuid, int $operation): string {

		// capture current microtime
		$stamp = microtime(true);
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->insert($this->_ChronicleTable);
		$cmd->setValue('uid', $cmd->createNamedParameter($uid));
		$cmd->setValue('sid', $cmd->createNamedParameter($sid));
		$cmd->setValue('tag', $cmd->createNamedParameter($this->_EntityIdentifier));
		$cmd->setValue('cid', $cmd->createNamedParameter($cid));
		$cmd->setValue('eid', $cmd->createNamedParameter($eid));
		$cmd->setValue('euuid', $cmd->createNamedParameter($euuid));
		$cmd->setValue('operation', $cmd->createNamedParameter($operation));
		$cmd->setValue('stamp', $cmd->createNamedParameter($stamp));
		// execute command
		$cmd->executeStatement();
		// return stamp
		return base64_encode((string)$stamp);
		
	}

	/**
	 * reminisce operations to entities in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param int $encode weather to encode the result
	 *
	 * @return int|float|string
	 */
	public function chronicleApex(int $cid, bool $encode = true): int|float|string {

		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select($cmd->func()->max('stamp'))
			->from($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('tag', $cmd->createNamedParameter($this->_EntityIdentifier)));
		$stampApex = $cmd->executeQuery()->fetchOne();
		$cmd->executeQuery()->closeCursor();

		if ($encode) {
			return base64_encode((string)max(0, $stampApex));
		} else {
			return max(0, $stampApex);
		}
		
	}

	/**
	 * reminisce operations to entities in data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $cid collection id
	 * @param string $stamp time stamp
	 * @param int $limit results limit
	 * @param int $offset results offset
	 *
	 * @return array
	 */
	public function chronicleReminisce(int $cid, string $stamp, ?int $limit = null, ?int $offset = null): array {

		// retrieve apex stamp
		$stampApex = $this->chronicleApex($cid, false);
		// determine nadir stamp
		$stampNadir = !empty($stamp) ? base64_decode($stamp) : '';
		$initial = !is_numeric($stampNadir);

		// retrieve additions
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->select('eid', 'euuid', new Literal('MAX(operation) AS operation'))
			->from($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($cid)))
			->andWhere($cmd->expr()->eq('tag', $cmd->createNamedParameter($this->_EntityIdentifier)))
			->groupBy('eid');
		// evaluate if this is a initial reconciliation
		if ($initial) {
			// select only entries that are not deleted
			$cmd->having(new Literal('MAX(operation) != 3'));
		} else {
			// select entries between nadir and apex
			$cmd->andWhere($cmd->expr()->gt('stamp', $cmd->createNamedParameter($stampNadir)));
			$cmd->andWhere($cmd->expr()->lte('stamp', $cmd->createNamedParameter($stampApex)));
		}
		// evaluate if limit exists
		if (is_numeric($limit)) {
			$cmd->setMaxResults($limit);
		}
		// evaluate if offset exists
		if (is_numeric($offset)) {
			$cmd->setFirstResult($offset);
		}

		// define place holder
		$chronicle = ['additions' => [], 'modifications' => [], 'deletions' => [], 'stamp' => base64_encode((string)$stampApex)];
		
		// execute command
		$rs = $cmd->executeQuery();
		// process result
		while (($entry = $rs->fetch()) !== false) {
			switch ($entry['operation']) {
				case $initial:
				case 1:
					$chronicle['additions'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
				case 2:
					$chronicle['modifications'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
				case 3:
					$chronicle['deletions'][] = ['id' => $entry['eid'], 'uuid' => $entry['euuid']];
					break;
			}
		}
		$rs->closeCursor();

		// return stamp
		return $chronicle;
		
	}

	/**
	 * delete chronicle entries for a specific user from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id user id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByUser(string $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('uid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete chronicle entries for a specific service from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id service id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByService(int $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('sid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

	/**
	 * delete chronicle entries for a specific collection from data store
	 *
	 * @since Release 1.0.0
	 *
	 * @param int $id collection id
	 *
	 * @return mixed
	 */
	public function chronicleExpungeByCollection(int $id) {
		// construct data store command
		$cmd = $this->_Store->getQueryBuilder();
		$cmd->delete($this->_ChronicleTable)
			->where($cmd->expr()->eq('cid', $cmd->createNamedParameter($id)));
		// execute command and return result
		return $cmd->executeStatement();
	}

}
