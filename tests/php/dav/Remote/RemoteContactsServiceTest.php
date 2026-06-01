<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Tests\Dav\Remote;

use OCA\DAVC\AppInfo\Application;
use OCA\DAVC\Models\Contacts\Collection;
use OCA\DAVC\Service\Remote\RemoteClient;
use OCA\DAVC\Service\Remote\RemoteContactsService;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class RemoteContactsServiceTest extends TestCase {
	private RemoteContactsService $service;

	protected function setUp(): void {
		parent::setUp();

		$davProtocol = $this->requiredEnv('DAV_PROTOCOL');
		$davHost = $this->requiredEnv('DAV_HOST');
		$davPort = (int)$this->requiredEnv('DAV_PORT');
		$davPath = $this->requiredEnv('DAV_PATH');
		$davUsername = $this->requiredEnv('DAV_USERNAME');
		$davPassword = $this->requiredEnv('DAV_PASSWORD');

		$container = (new Application())->getContainer();
		$client = new RemoteClient($container->get(IClientService::class));
		$client->configureLocation(
			$davProtocol,
			$davHost,
			$davPort,
			$davPath,
		);
		$client->configureTransportVerification(false);
		$client->setBasicAuthentication($davUsername, $davPassword);

		$capabilities = $client->discover();
		if (!is_string($capabilities['addressbookHomeSet'] ?? null) || $capabilities['addressbookHomeSet'] === '') {
			self::markTestSkipped('Remote address book home set is not available for DAV integration tests.');
		}

		$this->service = new RemoteContactsService();
		$this->service->initialize($client);
	}

	private function requiredEnv(string $key): string {
		$value = getenv($key);
		if ($value === false || $value === '') {
			self::markTestSkipped('DAV integration test environment is not configured.');
		}

		self::assertIsString($value);

		return $value;
	}

	public function testCollectionList(): void {
		$collections = $this->service->collectionList();

		$this->assertNotEmpty($collections);

		foreach ($collections as $collection) {
			$this->assertInstanceOf(Collection::class, $collection);
			$this->assertIsString($collection->remoteId);
			$this->assertNotSame('', $collection->remoteId);
			$this->assertStringStartsWith('/', $collection->remoteId);
		}
	}

	public function testCollectionFetch(): void {
		$collections = $this->service->collectionList('default');
		$this->assertNotEmpty($collections);

		$listedCollection = $collections[0];
		$this->assertInstanceOf(Collection::class, $listedCollection);
		$this->assertIsString($listedCollection->remoteId);
		$this->assertNotSame('', $listedCollection->remoteId);

		$fetchedCollection = $this->service->collectionFetch($listedCollection->remoteId);

		$this->assertInstanceOf(Collection::class, $fetchedCollection);
		$this->assertSame($listedCollection->remoteId, $fetchedCollection->remoteId);
		$this->assertIsString($fetchedCollection->remoteId);
		$this->assertNotSame('', $fetchedCollection->remoteId);
		$this->assertTrue($fetchedCollection->label === null || is_string($fetchedCollection->label));
	}

}
