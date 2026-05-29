<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Providers\DAV\Calendar\Hybrid;

use OCA\DAVC\Models\Calendars\Entity;

class EventEntity implements \Sabre\CalDAV\ICalendarObject, \Sabre\DAVACL\IACL {

	public function __construct(
		private readonly EventCollection $collection,
		private readonly Entity $entity,
	) {
	}

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
		return $this->entity->data;
	}

	/**
	 * @inheritDoc
	 */
	public function put($data) {
		if (is_resource($data)) {
			$data = stream_get_contents($data);
		}
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
		return 'text/calendar; charset=utf-8';
	}

	/**
	 * @inheritDoc
	 */
	public function getETag() {
		return $this->entity->localSignature ?? $this->entity->remoteSignature ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSize() {
		return strlen($this->entity->data ?? '');
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return ($this->entity->uuid ?? $this->entity->remoteEntityId);
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
