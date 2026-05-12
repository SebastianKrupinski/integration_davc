<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Local;

use OC\Files\Node\Folder;
use OCP\Files\IRootFolder;

class BlobFileStore {

	private Folder $cacheBase;

	public function __construct(
		private IRootFolder $_store,
	) {
		$test = $_store->getUserFolder('user1');
		$test = $test->getParent();

		if (!$test->nodeExists('cache')) {
			$test->newFolder('cache');
		}
		$this->cacheBase = $test->get('cache');
	}

	public function retrieve(array $location, array $identifiers): array {
		$folder = clone $this->cacheBase;
		$files = [];
		// select folder
		foreach ($location as $name) {
			if (!$folder->nodeExists($name)) {
				return [];
			}
			$folder = $folder->get($name);
		}
		// select blobs
		foreach ($identifiers as $identifier) {
			if ($folder->nodeExists($identifier)) {
				$file = $folder->get($identifier)->getContent();
				$blob = json_decode($file, true);
				if ($blob === null) {
					continue;
				}
				$files[$identifier] = $blob;
			}
		}
		return $files;
	}

	public function deposit(array $location, array $blobs): void {
		$folder = clone $this->cacheBase;
		// select folder
		foreach ($location as $name) {
			if (!$folder->nodeExists($name)) {
				$folder = $folder->newFolder($name);
			} else {
				$folder = $folder->get($name);
			}
		}
		// deposit blobs
		foreach ($blobs as $identifier => $blob) {
			if ($folder->nodeExists($identifier)) {
				$folder->get($identifier)->delete();
			}
			$file = $folder->newFile($identifier);
			$file->putContent(json_encode($blob));
		}
	}

}
