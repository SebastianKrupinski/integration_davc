<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Store\Common\Filters;

enum FilterComparisonOperator: string {
	case EQ = '=';
	case GT = '>';
	case LT = '<';
	case GTE = '>=';
	case LTE = '<=';
	case NEQ = '!=';
	case IN = 'IN';
	case NIN = 'NOT IN';
	case LIKE = 'LIKE';
	case NLIKE = 'NOT LIKE';
}
