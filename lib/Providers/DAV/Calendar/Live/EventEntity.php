<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Calendar\Live;

use OCA\DAVC\Objects\Event\EventObject;

class EventEntity implements \Sabre\CalDAV\ICalendarObject, \Sabre\DAVACL\IACL {

	/**
	 * entity constructor
	 */
	public function __construct(
		private EventCollection $_collection,
		private EventObject $_entity
	) {}

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
		return $this->_collection->fromEventObject($this->_entity);
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		return $this->_collection->modifyFile($this->_entity->ID, $data);
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->_collection->deleteFile($this->_entity->ID);
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
		return $this->_entity->Signature;
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return 1024;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->_entity->ID . '.ics';
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
		return $this->_entity->ModifiedOn->getTimestamp();
	}

}
