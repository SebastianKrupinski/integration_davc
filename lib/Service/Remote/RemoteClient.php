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

	public function getCalendarHomeSet(): ?string {
		return $this->capabilities['calendarHomeSet'] ?? null;
	}

    public function setCalendarHomeSet(?string $calendarHomeSet): void {
        $this->capabilities['calendarHomeSet'] = $calendarHomeSet;
    }

	public function getAddressbookHomeSet(): ?string {
		return $this->capabilities['addressbookHomeSet'] ?? null;
	}

	public function setAddressbookHomeSet(?string $addressbookHomeSet): void {
		$this->capabilities['addressbookHomeSet'] = $addressbookHomeSet;
	}

	public function discover(): array {
		$url = $this->buildUrl();
		$this->capabilities['endpoint'] = $url;

		try {
			$optionsResponse = $this->getClient()->request('OPTIONS', $url, $this->buildOptionsRequestOptions());
			$discoveryResponse = $this->getClient()->request('PROPFIND', $url, $this->buildPropfindRequestOptions([
				'{DAV:}current-user-principal' => null,
			]));
			$discoveryProperties = $this->parseMultistatusProperties($discoveryResponse);

			$this->capabilities['dav'] = $this->parseHeaderList($optionsResponse->getHeader('DAV'));
			$this->capabilities['allow'] = $this->parseHeaderList($optionsResponse->getHeader('Allow'));
			$this->capabilities['principalUrl'] = $this->extractHrefProperty(
				$discoveryProperties,
				'{DAV:}current-user-principal',
				$url,
			);

			if ($this->capabilities['principalUrl'] !== null) {
				$principalResponse = $this->getClient()->request(
					'PROPFIND',
					$this->capabilities['principalUrl'],
					$this->buildPropfindRequestOptions([
						'{DAV:}principal-URL' => null,
						'{urn:ietf:params:xml:ns:caldav}calendar-home-set' => null,
						'{urn:ietf:params:xml:ns:carddav}addressbook-home-set' => null,
					]),
				);
				$principalProperties = $this->parseMultistatusProperties($principalResponse);

				$this->capabilities['principalUrl'] = $this->extractHrefProperty(
					$principalProperties,
					'{DAV:}principal-URL',
					$this->capabilities['principalUrl'],
				) ?? $this->capabilities['principalUrl'];
				$this->capabilities['calendarHomeSet'] = $this->extractHrefProperty(
					$principalProperties,
					'{urn:ietf:params:xml:ns:caldav}calendar-home-set',
					$this->capabilities['principalUrl'],
				);
				$this->capabilities['addressbookHomeSet'] = $this->extractHrefProperty(
					$principalProperties,
					'{urn:ietf:params:xml:ns:carddav}addressbook-home-set',
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

	private function buildUrl(): string {
		$host = sprintf(
			'%s://%s:%d',
			$this->locationProtocol,
			$this->locationHost,
			$this->locationPort,
		);
		$host = rtrim($host, '/') . '/';
		$path = $this->locationPath ?? '/';

		$uri = \GuzzleHttp\Psr7\UriResolver::resolve(
			\GuzzleHttp\Psr7\Utils::uriFor($host),
			\GuzzleHttp\Psr7\Utils::uriFor($path),
		);

		return (string)$uri;
	}

	private function buildOptionsRequestOptions(): array {
		$headers = [
			'Accept' => 'application/xml, text/xml;q=0.9, */*;q=0.8',
		];

		if ($this->transportAgent !== '') {
			$headers['User-Agent'] = $this->transportAgent;
		}

		$options = [
			'headers' => $headers,
			'timeout' => IClient::DEFAULT_REQUEST_TIMEOUT,
			'verify' => $this->locationSecurity,
		];

		if ($this->basicAuthentication !== null) {
			$options['auth'] = $this->basicAuthentication;
		}

		if ($this->bearerToken !== null) {
			$options['headers']['Authorization'] = 'Bearer ' . $this->bearerToken;
		}

		return $options;
	}

	private function buildPropfindRequestOptions(array $properties): array {
		$options = $this->buildOptionsRequestOptions();
		$options['headers']['Content-Type'] = 'application/xml; charset=utf-8';
		$options['headers']['Depth'] = '0';
		$options['body'] = $this->buildPropfindBody($properties);

		return $options;
	}

	private function parseHeaderList(string $header): array {
		if ($header === '') {
			return [];
		}

		return array_values(array_filter(array_map('trim', explode(',', $header)), static fn (string $value): bool => $value !== ''));
	}

	private function extractHrefProperty(array $properties, string $propertyName, string $baseUrl): ?string {
		foreach ($properties as $responseProperties) {
			if (!isset($responseProperties[200][$propertyName])) {
				continue;
			}

			$propertyValue = $responseProperties[200][$propertyName];
			if (!is_array($propertyValue) || !isset($propertyValue[0]['name']) || $propertyValue[0]['name'] !== '{DAV:}href') {
				continue;
			}

			return $this->resolveUrl((string)$propertyValue[0]['value'], $baseUrl);
		}

		return null;
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

	private function resolveUrl(string $path, string $baseUrl): string {
		$baseUrl = rtrim($baseUrl, '/') . '/';
		$uri = \GuzzleHttp\Psr7\UriResolver::resolve(
			\GuzzleHttp\Psr7\Utils::uriFor($baseUrl),
			\GuzzleHttp\Psr7\Utils::uriFor($path),
		);

		return (string)$uri;
	}

	private function buildPropfindBody(array $properties): string {
		return (new SabreXmlService())->write(self::DAV_PROFIND, [self::DAV_PROPFIND_ONE => $properties ]);
	}
}