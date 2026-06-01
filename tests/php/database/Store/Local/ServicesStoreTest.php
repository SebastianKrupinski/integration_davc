<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Database\Store\Local;

use OCA\DAVC\Store\Common\Filters\FilterBase;
use OCA\DAVC\Store\Local\ServiceEntity;
use OCA\DAVC\Store\Local\ServicesStore;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class ServicesStoreTest extends TestCase {
	private IDBConnection $db;

	private ServicesStore $store;

	protected function setUp(): void {
		parent::setUp();

		$this->db = Server::get(IDBConnection::class);
		$this->store = new ServicesStore($this->db);

		$qb = $this->db->getQueryBuilder();
		$qb->delete('davc_services')->executeStatement();
	}

	public function testCreateAndFetchPersistService(): void {
		$service = $this->createServiceEntity('user-1', 'alpha.example.test');

		$created = $this->store->create($service);
		$serviceId = (int)($created->jsonSerialize()['id'] ?? 0);
		$fetched = $this->store->fetch($serviceId);

		$this->assertGreaterThan(0, $serviceId);
		$this->assertNotNull($fetched);
		$this->assertSame('user-1', $fetched->getUid());
		$this->assertSame('alpha.example.test', $fetched->getLocationHost());
		$this->assertSame('service-user-1', $fetched->getLabel());
	}

	public function testListReturnsOnlyMatchingUserServices(): void {
		$first = $this->store->create($this->createServiceEntity('user-1', 'alpha.example.test'));
		$this->store->create($this->createServiceEntity('user-2', 'beta.example.test'));

		$filter = new FilterBase();
		$filter->condition('uid', 'user-1');
		$services = $this->store->list($filter);

		$firstId = (int)($first->jsonSerialize()['id'] ?? 0);
		$this->assertCount(1, $services);
		$this->assertArrayHasKey($firstId, $services);
		$this->assertSame('user-1', $services[$firstId]->getUid());
	}

	public function testModifyOnlyUpdatesOwnedService(): void {
		$service = $this->store->create($this->createServiceEntity('user-1', 'alpha.example.test'));
		$serviceId = (int)($service->jsonSerialize()['id'] ?? 0);

		$service->setLabel('wrong-user-update');
		$this->store->modify('user-2', $service);
		$unchanged = $this->store->fetch($serviceId);

		$this->assertNotNull($unchanged);
		$this->assertSame('service-user-1', $unchanged->getLabel());

		$service->setLabel('right-user-update');
		$this->store->modify('user-1', $service);
		$updated = $this->store->fetch($serviceId);

		$this->assertNotNull($updated);
		$this->assertSame('right-user-update', $updated->getLabel());
	}

	public function testDeleteOnlyRemovesOwnedService(): void {
		$service = $this->store->create($this->createServiceEntity('user-1', 'alpha.example.test'));
		$serviceId = (int)($service->jsonSerialize()['id'] ?? 0);

		$this->store->delete('user-2', $service);
		$this->assertNotNull($this->store->fetch($serviceId));

		$this->store->delete('user-1', $service);
		$this->assertNull($this->store->fetch($serviceId));
	}

	public function testDeleteByUserRemovesOnlyTargetUsersServices(): void {
		$this->store->create($this->createServiceEntity('user-1', 'alpha.example.test'));
		$other = $this->store->create($this->createServiceEntity('user-2', 'beta.example.test'));

		$this->store->deleteByUser('user-1');
		$remaining = $this->store->list();
		$otherId = (int)($other->jsonSerialize()['id'] ?? 0);

		$this->assertCount(1, $remaining);
		$this->assertArrayHasKey($otherId, $remaining);
		$this->assertSame('user-2', $remaining[$otherId]->getUid());
	}

	private function createServiceEntity(string $uid, string $host): ServiceEntity {
		$service = new ServiceEntity();
		$service->setUid($uid);
		$service->setUuid(sprintf('12345678-1234-1234-1234-%012d', random_int(1, 999999)));
		$service->setLabel('service-' . $uid);
		$service->setLocationProtocol('https');
		$service->setLocationHost($host);
		$service->setLocationPort(443);
		$service->setLocationPath('/remote.php/dav');
		$service->setLocationSecurity(true);
		$service->setAuth('BA');
		$service->setBauthId('user@example.test');
		$service->setBauthSecret('secret');

		return $service;
	}
}
