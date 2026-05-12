<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use Exception;

use JmapClient\Client;
use JmapClient\Requests\Blob\BlobGet;
use JmapClient\Requests\Core\SubscriptionGet;
use JmapClient\Requests\Core\SubscriptionParameters as SubscriptionParametersRequest;
use JmapClient\Requests\Core\SubscriptionSet;
use JmapClient\Responses\Core\SubscriptionParameters as SubscriptionParametersResponse;
use JmapClient\Responses\ResponseException;
use OCA\DAVC\Exceptions\JmapUnknownMethod;

class RemoteCoreService {
	protected Client $dataStore;
	protected string $dataAccount;

	protected ?string $resourceNamespace = null;
	protected ?string $resourceCollectionLabel = null;
	protected ?string $resourceEntityLabel = null;

	public function __construct() {
	}

	public function initialize(Client $dataStore, ?string $dataAccount = null) {

		$this->dataStore = $dataStore;
		// evaluate if client is connected
		if (!$this->dataStore->sessionStatus()) {
			$this->dataStore->connect();
		}
		// determine account
		if ($dataAccount === null) {
			if ($this->resourceNamespace !== null) {
				$account = $dataStore->sessionAccountDefault($this->resourceNamespace, false);
			} else {
				$account = $dataStore->sessionAccountDefault('contacts');
			}
			$this->dataAccount = $account !== null ? $account->id() : '';
		} else {
			$this->dataAccount = $dataAccount;
		}

	}

	/**
	 * list of subscriptions in remote storage
	 *
	 * @since Release 1.0.0
	 *
	 * @return array<string,SubscriptionParametersResponse>
	 */
	public function subscriptionList(): array {
		// construct request
		$r0 = new SubscriptionGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// determine if command errored
		if ($response instanceof ResponseException) {
			if ($response->type() === 'unknownMethod') {
				throw new JmapUnknownMethod($response->description(), 1);
			} else {
				throw new Exception($response->type() . ': ' . $response->description(), 1);
			}
		}
		// convert jmap objects to collection objects
		$list = [];
		foreach ($response->objects() as $so) {
			if (!$so instanceof SubscriptionParametersResponse) {
				continue;
			}
			$list[] = $so;
		}
		// return collection of collections
		return $list;
	}

	/**
	 * retrieve subscription for specific collection
	 *
	 * @since Release 1.0.0
	 */
	public function subscriptionFetch(string $id): ?SubscriptionParametersResponse {
		// construct request
		$r0 = new SubscriptionGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		if (!empty($id)) {
			$r0->target($id);
		}
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert jmap object to collection object
		$so = $response->object(0);
		$to = null;
		if ($so instanceof SubscriptionParametersResponse) {
			$to = $so;
		}
		return $to;
	}

	/**
	 * create subscription in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function subscriptionCreate(SubscriptionParametersRequest $so): string {
		// construct request
		$r0 = new SubscriptionSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->create('1', $so);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return (string)$response->created()['1']['id'];
	}

	/**
	 * modify collection in remote storage
	 *
	 * @since Release 1.0.0
	 */
	public function subscriptionModify(string $id, SubscriptionParametersRequest $so): string {
		// construct request
		$r0 = new SubscriptionSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceCollectionLabel);
		$r0->update($id, $so);
		// transceive
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// return collection id
		return array_key_exists($id, $response->updated()) ? (string)$id : '';
	}

		/**
	 * retrieve blob from remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function blobFetch(string $account, string $id): Object {

		// TODO: testing remove later
		//$data = '';
		//$this->dataStore->download($account, $id, $data);
		//return null;

		// construct get request
		$r0 = new BlobGet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel);
		// construct object
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->object(0);

	}

	/**
	 * deposit bolb to remote storage
	 *
	 * @since Release 1.0.0
	 *
	 */
	public function blobDeposit(string $account, string $type, &$data): array {

		// TODO: testing remove later
		$response = $this->dataStore->upload($account, $type, $data);
		// convert response to object
		$response = json_decode($response, true);

		return  $response;

		/*
		// construct set request
		$r0 = new BlobSet($this->dataAccount, null, $this->resourceNamespace, $this->resourceEntityLabel)
		// construct object
		$r0->target($id);
		// transmit request and receive response
		$bundle = $this->dataStore->perform([$r0]);
		// extract response
		$response = $bundle->response(0);
		// convert json object to message object and return
		return $response->object(0);
		*/

	}
}
