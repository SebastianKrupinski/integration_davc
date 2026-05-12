<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Utile;

class Sanitizer {

	/**
	 * sanitize string for use in folder name
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $name - String to be sanitized
	 * @param bool $lp - Stip leading special characters
	 * @param bool $tp - Stip trailing special characters
	 *
	 * @return string sanitized version of the string
	 */
	public static function folder(string $name, bool $lsc = false, bool $tsc = false): string {

		// strip forbidden characters
		$name = preg_replace("/[^\w\s\.\,\_\-\~\'\[\]\(\)]/iu", '', $name);
		// replace all white space with single space
		$name = preg_replace('/\s+/iu', ' ', $name);
		// trim lenth to 255 characters
		$name = substr($name, 0, 255);
		// strip leading special characters or white space
		if ($lsc) {
			$name = preg_replace("/^[\s\.|\,|\_|\-|\~]*/iu", '', $name);
		} else {
			$name = ltrim($name);
		}
		// strip trailing special characters or white space
		if ($tsc) {
			$name = preg_replace("/[\s\.|\,|\_|\-|\~]*$/iu", '', $name);
		} else {
			$name = rtrim($name);
		}
		// return result
		return $name;

	}

	/**
	 * sanitize string for use in user name
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $name - String to be sanitized
	 *
	 * @return string sanitized version of the string
	 */
	public static function username(string $name): string {

		// strip forbidden characters
		$name = filter_var($name, FILTER_SANITIZE_EMAIL, FILTER_FLAG_STRIP_HIGH);
		
		// return result
		return $name;

	}

}
