<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Filters;

enum FilterConjunctionOperator: string {
	case NONE = '';
	case AND = 'AND';
	case OR = 'OR';
}
