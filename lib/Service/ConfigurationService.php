<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
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

namespace OCA\JMAPC\Service;

use OCA\JMAPC\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

class ConfigurationService {
	/**
	 * Default System Configuration
	 * @var array
	 * */
	private const _SYSTEM = [
		'mail_mode' => 'L',
		'mail_meta_store' => 'DB',
		'mail_contents_store' => 'FS',
		'mail_harmonization_mode' => 'P',
		'mail_harmonization_thread_duration' => '3600',
		'mail_harmonization_thread_pause' => '15',
	];

	/**
	 * Default System Secure Parameters
	 * @var array
	 * */
	private const _SYSTEM_SECURE = [];

	/**
	 * Default User Configuration
	 * @var array
	 * */
	private const _USER = [
		'contacts_presentation' => '',
		'events_timezone' => '',
		'events_attachment_path' => '/Calendar',
		'tasks_attachment_path' => '/Tasks',
	];

	/**
	 * Default User Secure Parameters
	 * @var array
	 * */
	private const _USER_SECURE = [];

	public function __construct(
		private IConfig $_ds,
		private ICrypto $_cs,
	) {
	}

	/**
	 * Retrieves collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param array $keys collection of configuration parameter keys
	 *
	 * @return array of key/value pairs, of configuration parameter
	 */
	public function retrieveUser(string $uid, ?array $keys = null): array {

		// define parameters place holder
		$parameters = [];
		// evaluate if we are looking for specific parameters

		if (!isset($keys) || count($keys) == 0) {
			// retrieve all user configuration keys
			$keys = array_keys(self::_USER);
			// retrieve all user configuration values
			foreach ($keys as $entry) {
				$parameters[$entry] = $this->retrieveUserValue($uid, $entry);
			}
			// retrieve system parameters
			$parameters['system_timezone'] = date_default_timezone_get();
			$parameters['system_contacts'] = $this->isContactsAppAvailable();
			$parameters['system_events'] = $this->isCalendarAppAvailable();
			$parameters['system_tasks'] = $this->isTasksAppAvailable();
			$parameters['user_id'] = $uid;
			// user default time zone
			$v = $this->_ds->getUserValue($uid, 'core', 'timezone');
			if (!empty($v)) {
				$parameters['user_timezone'] = $v;
			}
			// user events attachment path
			$v = $this->_ds->getUserValue($uid, 'dav', 'attachmentsFolder');
			if (!empty($v)) {
				$parameters['events_attachment_path'] = $v;
			}
		} else {
			// retrieve specific user configuration values
			foreach ($keys as $entry) {
				$parameters[$entry] = $this->retrieveUserValue($uid, $entry);
			}
		}
		// remove account secret
		if (isset($parameters['account_bauth_secret'])) {
			$parameters['account_bauth_secret'] = null;
		}
		// return configuration parameters
		return $parameters;

	}

	/**
	 * Deposit collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param array $parameters collection of key/value pairs, of parameters
	 *
	 * @return void
	 */
	public function depositUser($uid, array $parameters): void {

		// deposit system configuration parameters
		foreach ($parameters as $key => $value) {
			$this->depositUserValue($uid, $key, $value);
		}

	}

	/**
	 * Destroy collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param array $keys collection of configuration parameter keys
	 *
	 * @return void
	 */
	public function destroyUser(string $uid, ?array $keys = null): void {

		// evaluate if we are looking for specific parameters
		if (!isset($keys) || count($keys) == 0) {
			$keys = $this->_ds->getUserKeys($uid, Application::APP_ID);
		}
		// destroy system configuration parameter
		foreach ($keys as $entry) {
			$this->destroyUserValue($uid, $entry);
		}

	}

	/**
	 * Retrieves single system configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param string $key configuration parameter key
	 *
	 * @return string configuration parameter value
	 */
	public function retrieveUserValue(string $uid, string $key): string {

		// retrieve configured parameter value
		$value = $this->_ds->getUserValue($uid, Application::APP_ID, $key);
		// evaluate if value was returned
		if ($value != '') {
			// evaluate if parameter is on the secure list
			if (isset(self::_USER_SECURE[$key])) {
				$value = $this->_cs->decrypt($value);
			}
			// return configuration parameter value
			return $value;
		} else {
			// return default system configuration value
			return self::_USER[$key];
		}

	}

	/**
	 * Deposit single system configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param string $key configuration parameter key
	 * @param string $value configuration parameter value
	 *
	 * @return void
	 */
	public function depositUserValue(string $uid, string $key, string $value): void {

		// trim whitespace
		$value = trim($value);
		// evaluate if parameter is on the secure list
		if (isset(self::_USER_SECURE[$key]) && strlen($value) != 0) {
			$value = $this->_cs->encrypt($value);
		}
		// deposit user configuration parameter value
		$this->_ds->setUserValue($uid, Application::APP_ID, $key, $value);

	}

	/**
	 * Destroy single user configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param string $key configuration parameter keys
	 *
	 * @return void
	 */
	public function destroyUserValue(string $uid, string $key): void {

		// destroy user configuration parameter
		$this->_ds->deleteUserValue($uid, Application::APP_ID, $key);

	}

	/**
	 * Retrieves collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $keys collection of configuration parameter keys
	 *
	 * @return array of key/value pairs, of configuration parameter
	 */
	public function retrieveSystem(?array $keys = null): array {

		// evaluate if we are looking for specific parameters
		if (!isset($keys) || count($keys) == 0) {
			$keys = array_keys(self::_SYSTEM);
		}
		// retrieve system configuration values
		$parameters = [];
		foreach ($keys as $entry) {
			$parameters[$entry] = $this->retrieveSystemValue($entry);
		}
		// return configuration parameters
		return $parameters;

	}

	/**
	 * Deposit collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $parameters collection of key/value pairs, of parameters
	 *
	 * @return void
	 */
	public function depositSystem(array $parameters): void {

		// deposit system configuration parameters
		foreach ($parameters as $key => $value) {
			$this->depositSystemValue($key, $value);
		}

	}

	/**
	 * Destroy collection of system configuration parameters
	 *
	 * @since Release 1.0.0
	 *
	 * @param array $keys collection of configuration parameter keys
	 *
	 * @return void
	 */
	public function destroySystem(?array $keys = null): void {

		// evaluate if we are looking for specific parameters
		if (!isset($keys) || count($keys) == 0) {
			$keys = $this->_ds->getAppKeys(Application::APP_ID);
		}
		// destroy system configuration parameter
		foreach ($keys as $entry) {
			$this->destroySystemValue($entry);
		}

	}

	/**
	 * Retrieves single system configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $key configuration parameter key
	 *
	 * @return string configuration parameter value
	 */
	public function retrieveSystemValue(string $key): string {

		// retrieve configured parameter value
		$value = $this->_ds->getAppValue(Application::APP_ID, $key);
		// evaluate if value was returned
		if ($value != '') {
			if (isset(self::_SYSTEM_SECURE[$key])) {
				$value = $this->_cs->decrypt($value);
			}
			// return configuration parameter value
			return $value;
		} else {
			// return default system configuration value
			return self::_SYSTEM[$key];
		}

	}

	/**
	 * Deposit single system configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $key configuration parameter key
	 * @param string $value configuration parameter value
	 *
	 * @return void
	 */
	public function depositSystemValue(string $key, string $value): void {

		// trim whitespace
		$value = trim($value);
		// evaluate if parameter is on the secure list
		if (isset(self::_SYSTEM_SECURE[$key]) && !empty($value)) {
			$value = $this->_cs->encrypt($value);
		}
		// deposit system configuration parameter value
		$this->_ds->setAppValue(Application::APP_ID, $key, $value);

	}

	/**
	 * Destroy single system configuration parameter
	 *
	 * @since Release 1.0.0
	 *
	 * @return void
	 */
	public function destroySystemValue(string $key): void {

		// destroy system configuration parameter
		$this->_ds->deleteAppValue(Application::APP_ID, $key);

	}

	/**
	 * Gets harmonization mode
	 *
	 * @since Release 1.0.0
	 *
	 * @return string harmonization mode (default P - passive)
	 */
	public function getHarmonizationMode(): string {

		// retrieve harmonization mode
		$mode = $this->retrieveSystemValue('harmonization_mode');
		// return harmonization mode or default
		if (!empty($mode)) {
			return $mode;
		} else {
			return self::_SYSTEM['harmonization_mode'];
		}

	}

	/**
	 * Sets harmonization mode
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $mode harmonization mode (A - Active / P - Passive)
	 *
	 * @return void
	 */
	public function setHarmonizationMode(string $mode): void {

		// set harmonization mode
		$this->depositSystemValue('harmonization_mode', $mode);

	}

	/**
	 * Gets harmonization thread run duration interval
	 *
	 * @since Release 1.0.0
	 *
	 * @return string harmonization thread run duration interval (default 3600 seconds)
	 */
	public function getHarmonizationThreadDuration(): int {

		// retrieve value
		$interval = $this->retrieveSystemValue('harmonization_thread_duration');

		// return value or default
		if (is_numeric($interval)) {
			return intval($interval);
		} else {
			return intval(self::_SYSTEM['harmonization_thread_duration']);
		}

	}

	/**
	 * Sets harmonization thread pause interval
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $interval harmonization thread pause interval in seconds
	 *
	 * @return void
	 */
	public function setHarmonizationThreadDuration(int $interval): void {

		// set value
		$this->depositSystemValue('harmonization_thread_duration', $interval);

	}

	/**
	 * Gets harmonization thread pause interval
	 *
	 * @since Release 1.0.0
	 *
	 * @return string harmonization thread pause interval (default 5 seconds)
	 */
	public function getHarmonizationThreadPause(): int {

		// retrieve value
		$interval = $this->retrieveSystemValue('harmonization_thread_pause');

		// return value or default
		if (is_numeric($interval)) {
			return intval($interval);
		} else {
			return intval($self::_SYSTEM['harmonization_thread_pause']);
		}

	}

	/**
	 * Sets harmonization thread pause interval
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $interval harmonization thread pause interval in seconds
	 *
	 * @return void
	 */
	public function setHarmonizationThreadPause(int $interval): void {

		// set value
		$this->depositSystemValue('harmonization_thread_pause', $interval);

	}

	/**
	 * Gets harmonization thread id
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 *
	 * @return string|null thread id if exists | null if does not exist
	 */
	public function getHarmonizationThreadId(string $uid): int {

		// retrieve thread id
		$tid = $this->retrieveUserValue($uid, 'account_harmonization_tid');
		// return thread id
		if (is_numeric($tid)) {
			return intval($tid);
		} else {
			return 0;
		}

	}

	/**
	 * Sets harmonization thread id
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param string $tid thread id
	 *
	 * @return void
	 */
	public function setHarmonizationThreadId(string $uid, int $tid): void {

		// update harmonization thread id
		$this->depositUserValue($uid, 'account_harmonization_tid', (string)$tid);

	}

	/**
	 * Gets harmonization thread heart beat
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 *
	 * @return int thread heart beat time stamp if exists | null if does not exist
	 */
	public function getHarmonizationThreadHeartBeat(string $uid): int {

		// retrieve thread heart beat
		$thb = $this->retrieveUserValue($uid, 'account_harmonization_thb');
		// return thread heart beat
		if (is_numeric($thb)) {
			return (int)$thb;
		} else {
			return 0;
		}

	}

	/**
	 * Sets harmonization thread heart beat
	 *
	 * @since Release 1.0.0
	 *
	 * @param string $uid nextcloud user id
	 * @param int $thb thread heart beat time stamp
	 *
	 * @return void
	 */
	public function setHarmonizationThreadHeartBeat(string $uid, int $thb): void {

		// update harmonization thread id
		$this->depositUserValue($uid, 'account_harmonization_thb', $thb);

	}

	/**
	 * retrieve contacts app status
	 *
	 * @since Release 1.0.0
	 *
	 * @return bool
	 */
	public function isMailAppAvailable(): bool {

		// retrieve contacts app status
		$status = $this->_ds->getAppValue('mail', 'enabled');
		// evaluate status
		if ($status == 'yes') {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * retrieve contacts app status
	 *
	 * @since Release 1.0.0
	 *
	 * @return bool
	 */
	public function isContactsAppAvailable(): bool {

		// retrieve contacts app status
		$status = $this->_ds->getAppValue('contacts', 'enabled');
		// evaluate status
		if ($status == 'yes') {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * retrieve calendar app status
	 *
	 * @since Release 1.0.0
	 *
	 * @return bool
	 */
	public function isCalendarAppAvailable(): bool {

		// retrieve calendar app status
		$status = $this->_ds->getAppValue('calendar', 'enabled');
		// evaluate status
		if ($status == 'yes') {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * retrieve task app status
	 *
	 * @since Release 1.0.0
	 *
	 * @return bool
	 */
	public function isTasksAppAvailable(): bool {

		// retrieve calendar app status
		$status = $this->_ds->getAppValue('tasks', 'enabled');
		// evaluate status
		if ($status == 'yes') {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * encrypt string
	 *
	 * @since Release 1.0.0
	 *
	 * @return string
	 */
	public function encrypt(string $value): string {

		return $this->_cs->encrypt($value);

	}

	/**
	 * decrypt string
	 *
	 * @since Release 1.0.0
	 *
	 * @return string
	 */
	public function decrypt(string $value): string {

		return $this->_cs->decrypt($value);

	}

}
