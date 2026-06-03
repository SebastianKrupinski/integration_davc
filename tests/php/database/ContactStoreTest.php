<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Database;

use OCA\DAVC\Store\Local\CollectionEntity;
use OCA\DAVC\Store\Local\ContactEntity;
use OCA\DAVC\Store\Local\ContactStore;
use OCA\DAVC\Store\Local\Filters\CollectionFilter;
use OCA\DAVC\Store\Local\Filters\ContactFilter;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class ContactStoreTest extends TestCase {

	private IDBConnection $db;
	private ContactStore $store;
	private string $userId = 'test-user';
	private int $serviceId = 1000;

	protected function setUp(): void {
		parent::setUp();

		$this->db = Server::get(IDBConnection::class);
		$this->store = new ContactStore($this->db);

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
		$this->assertSame('CC', $created->getType());
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
		$contact = $this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha');

		$created = $this->store->entityCreate($contact);

		$this->assertInstanceOf(ContactEntity::class, $created);
		$this->assertGreaterThan(0, $created->getId());
		$this->assertSame($this->userId, $created->getUid());
		$this->assertSame($collection->getId(), $created->getCid());
		$this->assertEquals(0, count($created->getUpdatedFields()));
	}

	public function testEntityModify(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$contact->setLabel('contact-modified');
		$mutated = $this->store->entityModify($contact);

		$this->assertInstanceOf(ContactEntity::class, $mutated);
		$this->assertGreaterThan(0, $mutated->getId());
		$this->assertSame($this->userId, $mutated->getUid());
		$this->assertSame('contact-modified', $mutated->getLabel());
		$this->assertEquals(0, count($mutated->getUpdatedFields()));
	}

	public function testEntityDelete(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$deleted = $this->store->entityDelete($contact);
		$fetched = $this->store->entityFetch((int)$contact->getId());

		$this->assertInstanceOf(ContactEntity::class, $deleted);
		$this->assertSame($contact->getId(), $deleted->getId());
		$this->assertNull($fetched);
	}

	public function testEntityDeleteById(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$result = $this->store->entityDeleteById((int)$contact->getId());

		$this->assertSame(1, $result);
		$this->assertNull($this->store->entityFetch((int)$contact->getId()));
	}

	public function testEntityFetch(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$fetched = $this->store->entityFetch((int)$contact->getId());

		$this->assertInstanceOf(ContactEntity::class, $fetched);
		$this->assertGreaterThan(0, $fetched->getId());
		$this->assertSame($this->userId, $fetched->getUid());
		$this->assertSame((int)$collection->getId(), (int)$fetched->getCid());
		$this->assertSame('contact-alpha', $fetched->getLabel());
	}

	public function testEntityFetchByCorrelation(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$fetched = $this->store->entityFetchByCorrelation((int)$collection->getId(), $contact->getCcid(), $contact->getCeid());

		$this->assertInstanceOf(ContactEntity::class, $fetched);
		$this->assertSame($contact->getId(), $fetched->getId());
		$this->assertSame($contact->getUuid(), $fetched->getUuid());
	}

	public function testEntityListFilter(): void {
		$filter = $this->store->entityListFilter();

		$this->assertInstanceOf(ContactFilter::class, $filter);
	}

	public function testEntityList(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);
		$this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-beta')
		);

		$filter = $this->store->entityListFilter();
		$filter->condition('uid', $this->userId);
		$contacts = $this->store->entityList($filter);

		$this->assertIsArray($contacts);
		$this->assertCount(2, $contacts);
	}

	public function testChronicleApex(): void {
		$collection = $this->store->collectionCreate(
			$this->createCollectionEntity($this->userId, $this->serviceId, 'collection-alpha')
		);
		$this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
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
		$contact = $this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);

		$delta = $this->store->chronicleReminisce((int)$collection->getId(), '');

		$this->assertSame([
			['id' => $contact->getId(), 'uuid' => $contact->getUuid()],
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
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
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
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-alpha')
		);
		$this->store->entityCreate(
			$this->createContactEntity($this->userId, $this->serviceId, (int)$collection->getId(), 'contact-beta')
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

	private function createContactEntity(string $uid, int $sid, int $cid, string $label): ContactEntity {
		$contact = new ContactEntity();
		$contact->setUid($uid);
		$contact->setSid($sid);
		$contact->setCid($cid);
		$contact->setUuid(sprintf('32345678-1234-1234-1234-%012d', random_int(1, 999999)));
		$contact->setSignature('signature-' . $label);
		$contact->setCcid('ccid-' . $label);
		$contact->setCeid('ceid-' . $label);
		$contact->setCesn('cesn-' . $label);
		$contact->setData("BEGIN:VCARD\nFN:$label\nEND:VCARD");
		$contact->setLabel($label);

		return $contact;
	}
}
