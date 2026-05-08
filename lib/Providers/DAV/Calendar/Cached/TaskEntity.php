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

namespace OCA\JMAPC\Providers\DAV\Calendar\Cached;

use OCA\JMAPC\Store\Local\TaskEntity as TaskEntityData;

class TaskEntity implements \Sabre\CalDAV\ICalendarObject, \Sabre\DAVACL\IACL {
	private TaskCollection $_collection;
	private TaskEntityData $_entity;

	/**
	 * Entity Constructor
	 *
	 * @param Collection $calendar
	 * @param string $name
	 */
	public function __construct(TaskCollection $collection, TaskEntityData $entity) {
		$this->_collection = $collection;
		$this->_entity = $entity;
	}

	/**
	 * @inheritDoc
	 */
	public function getOwner() {
		return $this->_collection->getOwner();
	}

	/**
	 * @inheritDoc
	 */
	public function getGroup() {
		return $this->_collection->getGroup();
	}

	/**
	 * @inheritDoc
	 */
	public function getACL() {
		return [
			[
				'privilege' => '{DAV:}all',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function setACL(array $acl) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedPrivilegeSet() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function get() {
		return $this->_entity->getData();
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		return $this->_collection->modifyFile($this->_entity, $data);
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->_collection->deleteFile($this->_entity);
	}

	/**
	 * @inheritDoc
	 */
	public function getContentType() {
		return 'text/calendar; charset=utf-8';
	}

	/**
	 * @inheritDoc
	 */
	public function getETag() {
		return $this->_entity->getSignature();
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return strlen($this->_entity->getData());
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->_entity->getUuid() . '.ics';
	}

	/**
	 * @inheritDoc
	 */
	public function setName($name) {
		throw new \Sabre\DAV\Exception\Forbidden('This function is not supported yet');
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
		return time();
	}

}
