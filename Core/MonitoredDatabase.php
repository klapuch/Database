<?php
declare(strict_types = 1);
namespace Klapuch\Storage;

use Klapuch\Log;

final class MonitoredDatabase implements Database {
	private $origin;
	private $logs;

	public function __construct(Database $origin, Log\Logs $logs) {
	    $this->origin = $origin;
		$this->logs = $logs;
	}

	public function fetch(string $query, array $parameters = []): array {
		$this->monitor($query);
		return $this->origin->fetch($query, $parameters);
	}

	public function fetchAll(string $query, array $parameters = []): array {
		$this->monitor($query);
		return $this->origin->fetchAll($query, $parameters);
	}

	public function fetchColumn(string $query, array $parameters = []) {
		$this->monitor($query);
		return $this->origin->fetchColumn($query, $parameters);
	}

	public function query(string $query, array $parameters = []): \PDOStatement {
		$this->monitor($query);
		return $this->origin->query($query, $parameters);
	}

	public function exec(string $query): void {
		$this->monitor($query);
		$this->origin->exec($query);
	}

	/**
	 * Monitor the query
	 * @param string $query
	 * @return void
	 */
	private function monitor(string $query): void {
		$this->logs->put(
			new Log\PrettyLog(
				new \Exception($query),
				new Log\PrettySeverity(
					new Log\JustifiedSeverity(Log\Severity::INFO)
				)
			)
		);
	}
}