<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models;

class HarmonizationStatistics {

	public int $LocalCreated = 0;
	public int $LocalUpdated = 0;
	public int $LocalDeleted = 0;
	public int $RemoteCreated = 0;
	public int $RemoteUpdated = 0;
	public int $RemoteDeleted = 0;

	public function total(): int {
		return $this->LocalCreated + $this->LocalUpdated + $this->LocalDeleted + $this->RemoteCreated + $this->RemoteUpdated + $this->RemoteDeleted;
	}

	public function totalCreated(): int {
		return $this->LocalCreated + $this->RemoteCreated;
	}

	public function totalUpdated(): int {
		return $this->LocalUpdated + $this->RemoteUpdated;
	}

	public function totalDeleted(): int {
		return $this->LocalDeleted + $this->RemoteDeleted;
	}

	public function totalLocal(): int {
		return $this->LocalCreated + $this->LocalUpdated + $this->LocalDeleted;
	}

	public function totalRemote(): int {
		return $this->RemoteCreated + $this->RemoteUpdated + $this->RemoteDeleted;
	}

}
