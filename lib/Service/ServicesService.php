<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service;

use OCA\DAVC\Store\Common\Filters\IFilter;
use OCA\DAVC\Store\Common\Sort\ISort;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCA\DAVC\Store\Local\ServicesStore;

class ServicesService {
	private ServicesStore $_Store;

	public function __construct(ServicesStore $ServicesStore) {
		$this->_Store = $ServicesStore;
	}

	/**
	 * @return array<int,ServiceEntity>
	 */
	public function list(?IFilter $filter = null, ?ISort $sort = null): array {
		return $this->_Store->list($filter, $sort);
	}

	public function listFilter(): IFilter {
		return $this->_Store->listFilter();
	}

	public function listSort(): ISort {
		return $this->_Store->listSort();
	}

	/**
	 * @return array<int,ServiceEntity>
	 */
	public function fetchByUserId(string $uid): array {
		$filter = $this->_Store->listFilter();
		$filter->condition('uid', $uid);
		return $this->_Store->list($filter);
	}

	public function fetchByUserIdAndServiceId(string $uid, int $sid): ?ServiceEntity {
		$filter = $this->_Store->listFilter();
		$filter->condition('uid', $uid);
		$filter->condition('id', $sid);
		$services = $this->_Store->list($filter);
		if (isset($services[$sid])) {
			return $services[$sid];
		}
		return null;
	}

	public function fresh(): ServiceEntity {
		return new ServiceEntity();
	}

	public function fetch(int $id): ?ServiceEntity {
		return $this->_Store->fetch($id);
	}

	public function deposit(string $uid, ServiceEntity $service): ServiceEntity {
		$serviceId = $service->jsonSerialize()['id'] ?? null;
		if (!is_numeric($serviceId)) {
			return $this->create($uid, $service);
		} else {
			return $this->modify($uid, $service);
		}
	}

	public function create(string $uid, ServiceEntity $service): ServiceEntity {
		$service->setUid($uid);
		return $this->_Store->create($service);
	}

	public function modify(string $uid, ServiceEntity $service): ServiceEntity {
		return $this->_Store->modify($uid, $service);
	}

	public function delete(string $uid, ServiceEntity $service): void {
		$this->_Store->delete($uid, $service);
	}

}
