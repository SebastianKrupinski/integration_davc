<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Store\Local;

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
