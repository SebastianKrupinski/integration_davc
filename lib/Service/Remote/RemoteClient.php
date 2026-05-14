<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Service\Remote;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\IClientService;
use Psr\Http\Client\ClientExceptionInterface;
use Sabre\DAV\Xml\Response\MultiStatus;
use Sabre\DAV\Xml\Service as SabreXmlService;
use Sabre\Xml\ParseException;

class RemoteClient {

	public const DAV_HREF = '{DAV:}href';
	public const DAV_USER_PRINCIPAL = '{DAV:}current-user-principal';
	public const DAV_PRINCIPAL_URL = '{DAV:}principal-URL';
	public const DAV_RESOURCE_TYPE = '{DAV:}resourcetype';
	public const DAV_DISPLAYNAME = '{DAV:}displayname';
	public const DAV_OWNER = '{DAV:}owner';
	public const DAV_ACL = '{DAV:}acl';
	public const CALDAV_CALENDAR_TYPE = '{urn:ietf:params:xml:ns:caldav}calendar';
	public const CALDAV_CALENDAR_HOME_SET = '{urn:ietf:params:xml:ns:caldav}calendar-home-set';
	public const CALDAV_CALENDAR_DESCRIPTION = '{urn:ietf:params:xml:ns:caldav}calendar-description';
	public const CALDAV_SUPPORTED_CALENDAR_COMPONENT_SET = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';
	public const CARDDAV_ADDRESSBOOK_TYPE = '{urn:ietf:params:xml:ns:carddav}addressbook';
	public const CARDDAV_ADDRESSBOOK_HOME_SET = '{urn:ietf:params:xml:ns:carddav}addressbook-home-set';
	public const CARDDAV_ADDRESSBOOK_DESCRIPTION = '{urn:ietf:params:xml:ns:carddav}addressbook-description';
	public const CARDDAV_SUPPORTED_ADDRESS_DATA = '{urn:ietf:params:xml:ns:carddav}supported-address-data';
	public const CARDDAV_SUPPORTED_COLLATION_SET = '{urn:ietf:params:xml:ns:carddav}supported-collation-set';
	public const CARDDAV_MAX_RESOURCE_SIZE = '{urn:ietf:params:xml:ns:carddav}max-resource-size';
	public const APPLE_ICAL_CALENDAR_COLOR = '{http://apple.com/ns/ical/}calendar-color';
	public const APPLE_ICAL_CALENDAR_ORDER = '{http://apple.com/ns/ical/}calendar-order';
	public const CALENDARSERVER_GETCTAG = '{http://calendarserver.org/ns/}getctag';
	public const SABREDAV_SYNC_TOKEN = '{http://sabredav.org/ns}sync-token';

    private const DAV_PROFIND = '{DAV:}propfind';
    private const DAV_PROPFIND_ALL = '{DAV:}allprop';
    private const DAV_PROPFIND_ONE = '{DAV:}prop';
    private const DAV_MULTISTATUS = '{DAV:}multistatus';

	private ?IClient $client = null;

	private bool $connected = false;

	private string $transportAgent = '';

	private string $locationProtocol = 'https';

	private string $locationHost = '';

	private int $locationPort = 443;

	private ?string $locationPath = null;

	private bool $locationSecurity = true;

	private ?array $basicAuthentication = null;

	private ?string $bearerToken = null;

    private array $capabilities = [
        'discovery' => false,
        'endpoint' => null,
        'dav' => [],
        'allow' => [],
        'principalUrl' => null,
        'calendarHomeSet' => null,
        'addressbookHomeSet' => null,
    ];

	public function __construct(
		private IClientService $clientService,
	) {
	}

	public function setTransportAgent(string $transportAgent): void {
		$this->transportAgent = $transportAgent;
	}

	public function configureLocation(string|null $protocol, string $host, int|null $port, string|null $path): void {
		$this->locationHost = $host;
        if ($protocol !== null) {
            $this->locationProtocol = $protocol;
        }
        if ($port !== null) {
            $this->locationPort = $port;
        }
        if ($path !== null) {
            $this->locationPath = $path;
        }
	}

	public function configureTransportVerification(bool $verify): void {
		$this->locationSecurity = $verify;
	}

	public function setBasicAuthentication(string $username, string $password): void {
		$this->basicAuthentication = [$username, $password];
		$this->bearerToken = null;
	}

	public function setBearerAuthentication(string $token): void {
		$this->bearerToken = $token;
		$this->basicAuthentication = null;
	}

	public function getPrincipalUrl(): ?string {
		return $this->capabilities['principalUrl'] ?? null;
	}

    public function setPrincipalUrl(?string $principalUrl): void {
        $this->capabilities['principalUrl'] = $principalUrl;
    }

	public function getCalendarHome(): ?string {
		return $this->capabilities['calendarHomeSet'] ?? null;
	}

    public function setCalendarHome(?string $calendarHomeSet): void {
        $this->capabilities['calendarHomeSet'] = $calendarHomeSet;
    }

	public function getAddressbookHome(): ?string {
		return $this->capabilities['addressbookHomeSet'] ?? null;
	}

	public function setAddressbookHome(?string $addressbookHomeSet): void {
		$this->capabilities['addressbookHomeSet'] = $addressbookHomeSet;
	}

	/**
	 * Perform a PROPFIND request.
	 *
	 * @param string $uri The URI to perform the PROPFIND request on.
	 * @param int $depth The depth of the PROPFIND request.
	 * @param array<int|string, string|null> $properties The properties to request.
	 * @return array The response properties.
	 */
	public function propFind(string $path, int $depth, array $properties): array {
		$normalizedProperties = [];
		foreach ($properties as $name => $value) {
			if (is_int($name)) {
				$normalizedProperties[(string)$value] = null;
				continue;
			}

			$normalizedProperties[$name] = $value;
		}

		$request = (new SabreXmlService())->write(self::DAV_PROFIND, [
			self::DAV_PROPFIND_ONE => $normalizedProperties,
		]);
		
		$options = $this->buildOptionsRequestOptions(
			['Depth' => (string)$depth],
			['body' => $request],
		);

		$url = $this->constructUrl($path);
		
		$response = $this->getClient()->request('PROPFIND', $url, $options);

		return $this->parseMultistatusProperties($response);
	}

	public function discover(): array {
		$url = $this->constructUrl($this->locationPath);
		$this->capabilities['endpoint'] = $url;

		try {
			$optionsResponse = $this->getClient()->request('OPTIONS', $url, $this->buildOptionsRequestOptions());
			$discoveryProperties = $this->propFind($url, 0, [
				self::DAV_USER_PRINCIPAL => null,
			]);

			$this->capabilities['dav'] = $this->parseHeaderList($optionsResponse->getHeader('DAV'));
			$this->capabilities['allow'] = $this->parseHeaderList($optionsResponse->getHeader('Allow'));
			$this->capabilities['principalUrl'] = $this->extractHrefProperty(
				$discoveryProperties,
				self::DAV_USER_PRINCIPAL,
				$url,
			);

			if ($this->capabilities['principalUrl'] !== null) {
				$principalProperties = $this->propFind(
					$this->capabilities['principalUrl'],
					0,
					[
						self::DAV_PRINCIPAL_URL,
						self::CALDAV_CALENDAR_HOME_SET,
						self::CARDDAV_ADDRESSBOOK_HOME_SET,
					],
				);

				$this->capabilities['principalUrl'] = $this->extractHrefProperty(
					$principalProperties,
					self::DAV_PRINCIPAL_URL,
					$this->capabilities['principalUrl'],
				) ?? $this->capabilities['principalUrl'];
				$this->capabilities['calendarHomeSet'] = $this->extractHrefProperty(
					$principalProperties,
					self::CALDAV_CALENDAR_HOME_SET,
					$this->capabilities['principalUrl'],
				);
				$this->capabilities['addressbookHomeSet'] = $this->extractHrefProperty(
					$principalProperties,
					self::CARDDAV_ADDRESSBOOK_HOME_SET,
					$this->capabilities['principalUrl'],
				);
			}

			$this->capabilities['discovery'] = true;
		} catch (ClientExceptionInterface|ParseException $e) {
			$this->capabilities['discovery'] = false;
            throw $e;
		}

		return $this->capabilities;
	}

	private function getClient(): IClient {
		if ($this->client === null) {
			$this->client = $this->clientService->newClient();
		}

		return $this->client;
	}

	private function constructUrl(string $path = '/'): string {
		$host = sprintf(
			'%s://%s:%d',
			$this->locationProtocol,
			$this->locationHost,
			$this->locationPort,
		);
		$host = rtrim($host, '/') . '/';
		$path = rtrim($path, '/');

		$uri = \GuzzleHttp\Psr7\UriResolver::resolve(
			\GuzzleHttp\Psr7\Utils::uriFor($host),
			\GuzzleHttp\Psr7\Utils::uriFor($path),
		);

		return (string)$uri;
	}

	private function buildOptionsRequestOptions(array $additionalHeaders = [], array $additionalOptions = []): array {
		$headers = [
			'User-Agent' => $this->transportAgent,
			'Content-Type' => 'application/xml; charset=utf-8',
			'Accept' => 'application/xml, text/xml;q=0.9, */*;q=0.8',
		];

		$headers = array_merge($headers, $additionalHeaders);

		$options = [
			'headers' => $headers,
			'timeout' => IClient::DEFAULT_REQUEST_TIMEOUT,
			'verify' => $this->locationSecurity,
		];

		$options = array_merge($options, $additionalOptions);

		if ($this->basicAuthentication !== null) {
			$options['auth'] = $this->basicAuthentication;
		}

		if ($this->bearerToken !== null) {
			$options['headers']['Authorization'] = 'Bearer ' . $this->bearerToken;
		}

		return $options;
	}

	private function parseHeaderList(string $header): array {
		if ($header === '') {
			return [];
		}

		return array_values(array_filter(array_map('trim', explode(',', $header)), static fn (string $value): bool => $value !== ''));
	}

	private function parseMultistatusProperties(IResponse $response): array {
		$body = $response->getBody();
		if (is_resource($body)) {
			$body = stream_get_contents($body);
		}

		if (!is_string($body) || $body === '') {
			return [];
		}

		/** @var MultiStatus $multistatus */
		$multistatus = (new SabreXmlService())->expect(self::DAV_MULTISTATUS, $body);

		$result = [];
		foreach ($multistatus->getResponses() as $davResponse) {
			$result[$davResponse->getHref()] = $davResponse->getResponseProperties();
		}

		return $result;
	}

	private function extractHrefProperty(array $properties, string $propertyName, string $baseUrl): ?string {
		foreach ($properties as $responseProperties) {
			if (!isset($responseProperties[200][$propertyName])) {
				continue;
			}

			$propertyValue = $responseProperties[200][$propertyName];
			if (!is_array($propertyValue) || !isset($propertyValue[0]['name']) || $propertyValue[0]['name'] !== self::DAV_HREF) {
				continue;
			}

			return (string)$propertyValue[0]['value'];
		}

		return null;
	}

}