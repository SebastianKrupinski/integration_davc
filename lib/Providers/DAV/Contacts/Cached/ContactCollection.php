<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Sebastian Krupinski <krupinski01@gmail.com>
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

namespace OCA\JMAPC\Providers\DAV\Contacts\Cached;

use OCA\JMAPC\Store\Common\Filters\FilterComparisonOperator;
use OCA\JMAPC\Store\Local\CollectionEntity as CollectionEntityData;
use OCA\JMAPC\Store\Local\ContactEntity as ContactEntityData;
use OCA\JMAPC\Store\Local\ContactStore;
use Sabre\CardDAV\IAddressBook;
use Sabre\DAV\IMultiGet;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Sync\ISyncCollection;
use Sabre\VObject\Component\VCard;

class ContactCollection implements IAddressBook, IProperties, IMultiGet, ISyncCollection {

	private const DAV_USER_PREFIX = 'principals/users/';
	private ContactStore $_store;
	private CollectionEntityData $_collection;

	/**
	 * Collection constructor.
	 *
	 * @param ContactStore $store
	 * @param CollectionEntityData $data
	 */
	public function __construct(ContactStore &$store, CollectionEntityData $data) {
		// parent::__construct(Application::APP_ID, $data->getUuid());
		$this->_store = $store;
		$this->_collection = $data;
	}

	/**
	 * collection principal owner
	 *
	 * @return string|null
	 */
	public function getOwner(): ?string {
		return self::DAV_USER_PREFIX . $this->_collection->getUid();
	}

	/**
	 * collection principal group
	 *
	 * @return string|null
	 */
	public function getGroup(): ?string {
		return null;
	}

	/**
	 * collection id
	 */
	public function getName(): string {
		return $this->_collection->getUuid();
	}

	/**
	 * collection id
	 *
	 * @param string $id
	 */
	public function setName($id): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');
	}

	/**
	 * collection permissions
	 *
	 * @return array
	 */
	public function getACL(): array {
		return [
			[
				'privilege' => '{DAV:}all',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
		];
	}

	/**
	 * collection permissions
	 *
	 * @return void
	 */
	public function setACL(array $acl): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * supported permissions
	 *
	 * @return array|null
	 */
	public function getSupportedPrivilegeSet(): ?array {
		return null;
	}

	/**
	 * collection modification timestamp
	 *
	 * @return int|null
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * collection mutation signature
	 *
	 * @return string|null
	 */
	public function getSyncToken(): ?string {
		return $this->_store->chronicleApex($this->_collection->getId(), true);
	}

	/**
	 * collection delta
	 *
	 * @param string $token
	 * @param int $level
	 * @param int $limit
	 *
	 * @return array|null
	 */
	public function getChanges($token, $level, $limit = null): array {
		// retrieve delta
		$delta = $this->_store->chronicleReminisce($this->_collection->getId(), (string)$token, $limit);
		// convert results
		$changes['added'] = array_column($delta['additions'], 'uuid');
		$changes['modified'] = array_column($delta['modifications'], 'uuid');
		$changes['deleted'] = array_column($delta['deletions'], 'uuid');
		$changes['syncToken'] = $delta['stamp'];
		return $changes;
	}

	/**
	 * retrieves properties for this collection
	 *
	 * @param array $properties requested properties
	 *
	 * @return array
	 */
	public function getProperties($properties): array {
		// return collection properties
		return [
			'{DAV:}displayname' => $this->_collection->getLabel(),
			'{http://owncloud.org/ns}enabled' => (string)$this->_collection->getVisible(),
		];
	}

	/**
	 * modifies properties of this collection
	 *
	 * @param PropPatch $data
	 *
	 * @return void
	 */
	public function propPatch(PropPatch $propPatch): void {
		// retrieve mutations
		$mutations = $propPatch->getMutations();
		// evaluate if any mutations apply
		if (count($mutations) > 0) {
			// evaluate if name was changed
			if (isset($mutations['{DAV:}displayname'])) {
				$this->_collection->setLabel($mutations['{DAV:}displayname']);
				$propPatch->setResultCode('{DAV:}displayname', 200);
			}
			if (isset($mutations['{http://owncloud.org/ns}enabled'])) {
				$this->_collection->setVisible((bool)$mutations['{http://owncloud.org/ns}enabled']);
				$propPatch->setResultCode('{http://owncloud.org/ns}enabled', 200);
			}
			// update collection
			if (count($this->_collection->getUpdatedFields()) > 0) {
				$this->_store->collectionModify($this->_collection);
			}
		}
	}

	/**
	 * creates sub collection
	 *
	 * @param string $name
	 */
	public function createDirectory($name): void {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported');
	}

	/**
	 * Deletes this collection and all entities
	 *
	 * @return void
	 */
	public function delete(): void {
		// delete local entities
		$this->_store->entityDeleteByCollection($this->_collection->getId());
		// delete local collection
		$this->_store->collectionDelete($this->_collection);
	}

	/**
	 * list all entities in this collection
	 *
	 * @return array<int,ContactEntity>
	 */
	public function getChildren(): array {
		// construct collection filter
		$storeFilter = $this->_store->entityListFilter();
		$storeFilter->condition('cid', $this->_collection->getId());
		// retrieve entries
		$entries = $this->_store->entityList($storeFilter);
		// transform entries
		$list = [];
		foreach ($entries as $entry) {
			$list[] = new ContactEntity($this, $entry);
		}
		return $list;
	}

	/**
	 * determine if a specific entity exists in this collection
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	public function childExists($id): bool {
		// remove extension
		$id = str_replace('.vcf', '', $id);
		// confirm object exists
		return (bool)$this->_store->entityConfirmByUUID($this->_collection->getId(), $id);
	}

	/**
	 * retrieve specific entities in this collection
	 *
	 * @param array<int,string> $ids
	 *
	 * @return array<int,ContactEntity>
	 */
	public function getMultipleChildren(array $ids): array {
		// construct filter
		$filter = $this->_store->entityListFilter();
		$filter->condition('cid', $this->_collection->getId());
		$filter->condition('uuid', $ids, FilterComparisonOperator::IN);
		// retrieve object properties
		$entities = $this->_store->entityList($filter);
		// construct place holder
		$list = [];
		// convert entities
		foreach ($entities as $entry) {
			$list[] = new ContactEntity($this, $entry);
		}
		return $list;
	}

	/**
	 * retrieve a specific entity in this collection
	 *
	 * @param string $id existing entity id
	 *
	 * @return ContactEntity|false
	 */
	public function getChild($id): ContactEntity|false {
		// remove extension
		$id = str_replace('.vcf', '', $id);
		// construct filter
		$filter = $this->_store->entityListFilter();
		$filter->condition('cid', $this->_collection->getId());
		$filter->condition('uuid', $id);
		// retrieve object properties
		$entities = $this->_store->entityList($filter);
		// evaluate if object properties where retrieved
		if (count($entities) > 0) {
			return new ContactEntity($this, $entities[0]);
		} else {
			throw new \Sabre\DAV\Exception\NotFound('Entity not found');
		}
	}

	/**
	 * create a entity in this collection
	 *
	 * @param string $id fresh entity id
	 * @param string $data fresh entity contents
	 *
	 * @return string entity signature
	 */
	public function createFile($id, $data = null): string {
		// remove extension
		$id = str_replace('.vcf', '', $id);
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// data store entry
		$entity = new ContactEntityData();
		// read the data
		$vObject = \Sabre\VObject\Reader::read($data);
		// direct properties
		$entity->setUid($this->_collection->getUid());
		$entity->setSid($this->_collection->getSid());
		$entity->setCid($this->_collection->getId());
		$entity->setData($data);
		// calculated properties
		$entity->setSignature(md5($data));
		// extract additional properties
		$this->extractProperties($entity, $vObject);
		// deposit entity to data store
		$entity = $this->_store->entityCreate($entity);
		// return state
		return $entity->getSignature();
	}

	/**
	 * modify a entity in this collection
	 *
	 * @param contactEntityData $entity existing entity object
	 * @param string $data modified entity contents
	 *
	 * @return string entity signature
	 */
	public function modifyFile(ContactEntityData $entity, string $data): string {
		// evaluate if data is in UTF8 format and convert if needed
		if (!mb_check_encoding($data, 'UTF-8')) {
			$data = iconv(mb_detect_encoding($data), 'UTF-8', $data);
		}
		// read the data
		$vObject = \Sabre\VObject\Reader::read($data);
		// direct properties
		$entity->setData($data);
		// calculated properties
		$entity->setSignature(md5($data));
		// extract additional properties
		$this->extractProperties($entity, $vObject);
		// deposit entry to data store
		$entity = $this->_store->entityModify($entity);
		// return state
		return $entity->getSignature();

	}

	/**
	 * delete a entity in this collection
	 *
	 * @param ContactEntityData $entity existing entity object
	 *
	 * @return void
	 */
	public function deleteFile(ContactEntityData $entity): void {
		// delete entry from data store and return result
		$this->_store->entityDelete($entity);
	}

	/**
	 * converts vObject properties to entity properties
	 *
	 * @return void
	 */
	protected function extractProperties(ContactEntityData $entity, VCard $vObject): void {
		$entity->setUuid(isset($vObject->UID) ? $this->extractString($vObject->UID) : null);
		$entity->setLabel(isset($vObject->FN) ? $this->extractString($vObject->FN) : null);
	}

	/**
	 * converts entity text property to string
	 *
	 * @return string
	 */
	protected function extractString($property): string {
		return trim($property->getValue());
	}

}
