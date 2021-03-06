<?php
declare(strict_types = 1);

namespace Klapuch\Storage;

/**
 * Simple native query without changes
 */
final class PDOConnection implements Connection {
	private \PDO $database;

	public function __construct(\PDO $database) {
		$this->database = $database;
	}

	public function prepare(string $statement): \PDOStatement {
		return $this->database->prepare($statement);
	}

	public function exec(string $statement): void {
		$this->database->exec($statement);
	}
}
