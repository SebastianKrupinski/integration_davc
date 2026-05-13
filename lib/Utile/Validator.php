<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Utile;

class Validator {

	private const _fqdn = '/(?=^.{1,254}$)(^(?:(?!\d|-)[a-z0-9\-]{1,63}(?<!-)\.)+(?:[a-z]{2,})$)/i';
	private const _ip4 = '/^(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/';
	private const _ip6 = '/^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/';

	/**
	 * validate fully quntified domain name
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $fqdn - FQDN to validate
	 *
	 * @return bool
	 */
	public static function fqdn(string $fqdn): bool {

		return (!empty($fqdn) && preg_match(self::_fqdn, $fqdn) > 0);

	}

	/**
	 * validate IPv4 address
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $ip - IPv4 address to validate
	 *
	 * @return bool
	 */
	public static function ip4(string $ip): bool {

		return (!empty($ip) && preg_match(self::_ip4, $ip) > 0);

	}

	/**
	 * validate IPv6 address
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $ip - IPv6 address to validate
	 *
	 * @return bool
	 */
	public static function ip6(string $ip): bool {

		return (!empty($ip) && preg_match(self::_ip6, $ip) > 0);

	}

	/**
	 * validate host
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $host - FQDN/IPv4/IPv6 address to validate
	 *
	 * @return bool
	 */
	public static function host(string $host): bool {

		if ($host === 'localhost') {
			return true;
		}

		if (self::fqdn($host)) {
			return true;
		}

		if (self::ip4($host)) {
			return true;
		}

		if (self::ip6($host)) {
			return true;
		}

		return false;

	}

	/**
	 * validate email address
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $address - email address to validate
	 *
	 * @return bool
	 */
	public static function email(string $address): bool {

		return (!empty($address) && filter_var($address, FILTER_VALIDATE_EMAIL));

	}

	/**
	 * validate username
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $username - username to validate
	 *
	 * @return bool
	 */
	public static function username(string $username): bool {

		if (self::email($username)) {
			return true;
		}
		
		// TODO: Windows Login Validator
		/*
		if (self::windows_username($username)) {
			return true;
		}
		*/

		return false;

	}
}
