<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC;

class Constants {
	public const AUTHENTICATION_TYPE_BASIC = 'BA';
	public const AUTHENTICATION_TYPE_TOKEN = 'TA';
	public const AUTHENTICATION_TYPES = [
		self::AUTHENTICATION_TYPE_BASIC,
		self::AUTHENTICATION_TYPE_TOKEN,
	];
}
