<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models;

class DeltaObject {

	public function __construct(
		public ?BaseStringCollection $additions = null,
		public ?BaseStringCollection $modifications = null,
		public ?BaseStringCollection $deletions = null,
		public ?string $signature = null,
	) {
		if ($this->additions === null) {
			$this->additions = new BaseStringCollection;
		}
		if ($this->modifications === null) {
			$this->modifications = new BaseStringCollection;
		}
		if ($this->deletions === null) {
			$this->deletions = new BaseStringCollection;
		}
		if ($this->signature === null) {
			$this->signature = '';
		}
	}

}
