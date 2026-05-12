<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Contacts\Live;

use OCA\DAVC\Objects\Contact\ContactObject;

class ContactEntity implements \Sabre\CardDAV\ICard, \Sabre\DAVACL\IACL {

	/**
	 * Entity Constructor
	 */
	public function __construct(
		private ContactCollection $_collection,
		private ContactObject $_entity
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
		return $this->_collection->fromContactObject($this->_entity);
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
		return 'text/vcard; charset=utf-8';
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
		return $this->_entity->ID . '.vcf';
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
