<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Contacts\Hybrid;

use OCA\DAVC\Store\Local\ContactEntity as ContactEntityData;

class ContactEntity implements \Sabre\CardDAV\ICard, \Sabre\DAVACL\IACL {
	/**
	 * Entity Constructor
	 *
	 * @param ContactCollection $collection
	 * @param ContactEntityData $entity
	 */
	public function __construct(
		private readonly ContactCollection $collection,
		private readonly ContactEntityData $entity
	){}

	/**
	 * @inheritDoc
	 */
	public function getOwner() {
		return $this->collection->getOwner();
	}

	/**
	 * @inheritDoc
	 */
	public function getGroup() {
		return $this->collection->getGroup();
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
		return $this->entity->getData();
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		return $this->collection->modifyFile($this->entity, $data);
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->collection->deleteFile($this->entity);
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
		return $this->entity->getSignature();
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return strlen($this->entity->getData());
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->entity->getUuid() . '.vcf';
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
