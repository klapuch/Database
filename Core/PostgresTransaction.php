<?php
declare(strict_types = 1);
namespace Klapuch\Storage;

final class PostgresTransaction implements Transaction {
	private $database;

	public function __construct(Database $database) {
		$this->database = $database;
	}

	public function start(\Closure $callback) {
		$this->database->exec('BEGIN TRANSACTION');
		try {
			$result = $callback();
			$this->database->exec('COMMIT TRANSACTION');
			return $result;
		} catch(\Throwable $ex) {
			$this->database->exec('ROLLBACK TRANSACTION');
			if($ex instanceof \PDOException) {
				throw new \RuntimeException(
					'Error on the database side. Rolled back.',
					(int)$ex->getCode(),
					$ex
				);
			}
			throw $ex;
		}
	}
}