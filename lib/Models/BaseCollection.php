<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Models;

/**
 * @extends \ArrayObject<array-key, mixed>
 */
class BaseCollection extends \ArrayObject {
	private $type;

	public function __construct($type, $data = []) {
		$this->type = $type;
		parent::__construct($data);
	}

	private function validate($value): bool {
		return match ($this->type) {
			'string' => is_string($value),
			'int' => is_int($value),
			'float' => is_float($value),
			default => $value instanceof $this->type
		};
	}

	public function append($value): void {
		if (!$this->validate($value)) {
			throw new \InvalidArgumentException(
				sprintf('Cannot append value of type %s to collection expecting %s', gettype($value), $this->type)
			);
		}
		parent::append($value);
	}

	public function offsetSet(mixed $key, mixed $value): void {
		if (!$this->validate($value)) {
			throw new \InvalidArgumentException(
				sprintf('Cannot set offset %s with value of type %s in collection expecting %s', var_export($key, true), gettype($value), $this->type)
			);
		}
		parent::offsetSet($key, $value);
	}

}
