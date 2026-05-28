<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAVC\Logging;

use OCP\ILogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

final class FileLogger implements LoggerInterface {
	public function __construct(
		private string $path,
		private ?string $app = null,
	) {
	}

	#[\Override]
	public function emergency(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::FATAL, $message);
	}

	#[\Override]
	public function alert(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message);
	}

	#[\Override]
	public function critical(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message);
	}

	#[\Override]
	public function error(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::ERROR, $message);
	}

	#[\Override]
	public function warning(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::WARN, $message);
	}

	#[\Override]
	public function notice(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::INFO, $message);
	}

	#[\Override]
	public function info(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::INFO, $message);
	}

	#[\Override]
	public function debug(string|Stringable $message, array $context = []): void {
		$this->write(ILogger::DEBUG, $message);
	}

	#[\Override]
	public function log($level, $message, array $context = []): void {
		if (is_string($level)) {
			$level = match ($level) {
				LogLevel::EMERGENCY => ILogger::FATAL,
				LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR => ILogger::ERROR,
				LogLevel::WARNING => ILogger::WARN,
				LogLevel::NOTICE, LogLevel::INFO => ILogger::INFO,
				LogLevel::DEBUG => ILogger::DEBUG,
				default => null,
			};
		}

		if (!is_int($level)) {
			throw new InvalidArgumentException('Unsupported custom log level');
		}

		$this->write($level, (string)$message);
	}

	private function write(int $level, string|Stringable $message): void {
		$handle = @fopen($this->path, 'ab');
		if ($handle === false) {
			error_log((string)$message);
			return;
		}

		foreach (preg_split("/(\r\n|\n|\r)/", (string)$message) ?: [''] as $line) {
			fwrite($handle, $this->formatLine($level, $line) . "\n");
		}

		fclose($handle);
	}

	private function formatLine(int $level, string $message): string {
		$timestamp = (new \DateTimeImmutable())->format('H:i:s.v');

		return sprintf('%s|%s|   %s', $timestamp, $this->formatLevel($level), $message);
	}

	private function formatLevel(int $level): string {
		return match ($level) {
			ILogger::DEBUG => 'DBG',
			ILogger::INFO => 'INF',
			ILogger::WARN => 'WRN',
			ILogger::ERROR => 'ERR',
			ILogger::FATAL => 'FTL',
			default => 'LOG',
		};
	}
}
