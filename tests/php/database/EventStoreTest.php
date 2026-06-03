<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Database;

use DateTimeImmutable;
use OCA\DAVC\Store\Common\Range\RangeDate;
use OCA\DAVC\Store\Common\Sort\ISort;
use OCA\DAVC\Store\Common\Sort\SortBase;
use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\EventEntity;
use OCA\DAVC\Store\Local\EventStore;
use OCA\DAVC\Store\Local\Filters\CollectionFilter;
use OCA\DAVC\Store\Local\Filters\EventFilter;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class EventStoreTest extends TestCase {

	private IDBConnection $db;
	private EventStore $store;
	private string $userId = 'test-user';
	private int $serviceId = 1000;

	protected function setUp(): void {
		parent::setUp();

		$this->db = Server::get(IDBConnection::class);
		$this->store = new EventStore($this->db);

		$this->purgeTestData();
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->purgeTestData();
	}

	private function purgeTestData(): void {
		$this->store->collectionDeleteByUser($this->userId);
	}

	public function testCollectionCreate(): void {
		$collection = $this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha');

		$created = $this->store->collectionCreate($collection);

		$this->assertInstanceOf(CollectionEntity::class, $created);
		$this->assertGreaterThan(0, $created->getId());
		$this->assertSame($this->userId, $created->getUid());
		$this->assertSame('EC', $created->getType());
		$this->assertEquals(0, count($created->getUpdatedFields()));
	}

	public function testCollectionModify(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);

		$collection->setLabel('collection-modified');
		$collection->setColor('#004422');
		$mutated = $this->store->collectionModify($collection);

		$this->assertInstanceOf(CollectionEntity::class, $mutated);
		$this->assertSame('collection-modified', $mutated->getLabel());
		$this->assertSame('#004422', $mutated->getColor());
		$this->assertEquals(0, count($mutated->getUpdatedFields()));
	}

	public function testCollectionFetch(): void {
		$created = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);

		$fetched = $this->store->collectionFetch((int)$created->getId());

		$this->assertInstanceOf(CollectionEntity::class, $fetched);
		$this->assertSame($created->getId(), $fetched->getId());
		$this->assertSame('collection-alpha', $fetched->getLabel());
	}

	public function testCollectionListFilter(): void {
		$filter = $this->store->collectionListFilter();

		$this->assertInstanceOf(CollectionFilter::class, $filter);
	}

	public function testCollectionList(): void {
		$this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-beta')
		);

		$filter = $this->store->collectionListFilter();
		$filter->condition('uid', $this->userId);
		$collections = $this->store->collectionList($filter);

		$this->assertIsArray($collections);
		$this->assertCount(2, $collections);
	}

	public function testEntityCreate(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha');

		$created = $this->store->entityCreate($event);

		$this->assertInstanceOf(EventEntity::class, $created);
		$this->assertGreaterThan(0, $created->getId());
		$this->assertSame($this->userId, $created->getUid());
		$this->assertSame($collection->getId(), $created->getCid());
		$this->assertEquals(0, count($created->getUpdatedFields()));
	}

	public function testEntityModify(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$event->setLabel('event-modified');
		$mutated = $this->store->entityModify($event);

		$this->assertInstanceOf(EventEntity::class, $mutated);
		$this->assertGreaterThan(0, $mutated->getId());
		$this->assertSame($this->userId, $mutated->getUid());
		$this->assertSame('event-modified', $mutated->getLabel());
		$this->assertEquals(0, count($mutated->getUpdatedFields()));
	}

	public function testEntityDelete(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$deleted = $this->store->entityDelete($event);
		$fetched = $this->store->entityFetch((int)$event->getId());

		$this->assertInstanceOf(EventEntity::class, $deleted);
		$this->assertSame($event->getId(), $deleted->getId());
		$this->assertNull($fetched);
	}

	public function testEntityDeleteById(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$result = $this->store->entityDeleteById((int)$event->getId());

		$this->assertSame(1, $result);
		$this->assertNull($this->store->entityFetch((int)$event->getId()));
	}

	public function testEntityFetch(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$fetched = $this->store->entityFetch((int)$event->getId());

		$this->assertInstanceOf(EventEntity::class, $fetched);
		$this->assertGreaterThan(0, $fetched->getId());
		$this->assertSame($this->userId, $fetched->getUid());
		$this->assertSame((int)$collection->getId(), (int)$fetched->getCid());
		$this->assertSame('event-alpha', $fetched->getLabel());
	}

	public function testEntityFetchByCorrelation(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$fetched = $this->store->entityFetchByCorrelation((int)$collection->getId(), $event->getCcid(), $event->getCeid());

		$this->assertInstanceOf(EventEntity::class, $fetched);
		$this->assertSame($event->getId(), $fetched->getId());
		$this->assertSame($event->getUuid(), $fetched->getUuid());
	}

	public function testEntityListFilter(): void {
		$filter = $this->store->entityListFilter();

		$this->assertInstanceOf(EventFilter::class, $filter);
	}

	public function testEntityListSort(): void {
		$sort = $this->store->entityListSort();

		$this->assertInstanceOf(ISort::class, $sort);
		$this->assertInstanceOf(SortBase::class, $sort);
	}

	public function testEntityListRange(): void {
		$range = $this->store->entityListRange();

		$this->assertNotNull($range);
	}

	public function testEntityList(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-beta', 7200)
		);

		$filter = $this->store->entityListFilter();
		$filter->condition('uid', $this->userId);
		$events = $this->store->entityList($filter);

		$this->assertIsArray($events);
		$this->assertCount(2, $events);
	}

	public function testEntityListWithDateRange(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-in-range', 0, 3600)
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-out-of-range', 86400, 3600)
		);

		$filter = $this->store->entityListFilter();
		$filter->condition('cid', (int)$collection->getId());
		$range = new RangeDate(
			new DateTimeImmutable('@1735689600'),
			new DateTimeImmutable('@1735696800'),
		);

		$events = $this->store->entityList($filter, null, $range);

		$this->assertCount(1, $events);
		$this->assertSame('event-in-range', $events[0]->getLabel());
	}

	public function testChronicleApex(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$stamp = $this->store->chronicleApex((int)$collection->getId(), true);

		$this->assertIsString($stamp);
		$this->assertNotSame('', $stamp);
		$this->assertTrue(is_numeric(base64_decode($stamp, true)));
	}

	public function testChronicleReminisce(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$event = $this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$delta = $this->store->chronicleReminisce((int)$collection->getId(), '');

		$this->assertSame([
			['id' => $event->getId(), 'uuid' => $event->getUuid()],
		], $delta['additions']);
		$this->assertSame([], $delta['modifications']);
		$this->assertSame([], $delta['deletions']);
		$this->assertIsString($delta['stamp']);
	}

	public function testCollectionDeleteById(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);

		$result = $this->store->collectionDeleteById((int)$collection->getId());

		$this->assertSame(1, $result);
		$this->assertNull($this->store->collectionFetch((int)$collection->getId()));
		$this->assertCount(0, $this->store->entityListByCollection((int)$collection->getId()));
	}

	public function testEntityDeleteByCollection(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-alpha')
		);
		$this->store->entityCreate(
			$this->createEventEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'event-beta', 7200)
		);

		$result = $this->store->entityDeleteByCollection((int)$collection->getId());

		$this->assertSame(2, $result);
		$this->assertCount(0, $this->store->entityListByCollection((int)$collection->getId()));
		$this->assertInstanceOf(CollectionEntity::class, $this->store->collectionFetch((int)$collection->getId()));
	}

	private function createCollectionEntity(string $uid, int $sid, string $label): CollectionEntity {
		$collection = new CollectionEntity();
		$collection->setUid($uid);
		$collection->setSid($sid);
		$collection->setCcid('ccid-' . $label);
		$collection->setUuid(sprintf('22345678-1234-1234-1234-%012d', random_int(1, 999999)));
		$collection->setPermissions(['read' => true, 'write' => true]);
		$collection->setLabel($label);
		$collection->setColor('#008866');
		$collection->setVisible(0);

		return $collection;
	}

	private function createEventEntity(string $uid, int $sid, int $cid, string $label, int $startOffset = 0, int $duration = 3600): EventEntity {
		$baseStart = 1735689600 + $startOffset;

		$event = new EventEntity();
		$event->setUid($uid);
		$event->setSid($sid);
		$event->setCid($cid);
		$event->setUuid(sprintf('42345678-1234-1234-1234-%012d', random_int(1, 999999)));
		$event->setSignature('signature-' . $label);
		$event->setCcid('ccid-' . $label);
		$event->setCeid('ceid-' . $label);
		$event->setCesn('cesn-' . $label);
		$event->setData("BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:$label\nEND:VEVENT\nEND:VCALENDAR");
		$event->setLabel($label);
		$event->setDescription('description-' . $label);
		$event->setStartson($baseStart);
		$event->setEndson($baseStart + $duration);

		return $event;
	}
}
