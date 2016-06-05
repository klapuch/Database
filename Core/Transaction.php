<?php
declare(strict_types = 1);
namespace Klapuch\Storage;

final class Transaction {
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function start(\Closure $callback) {
        try {
            $this->database->exec('START TRANSACTION');
            $result = $callback();
            $this->database->exec('COMMIT');
            return $result;
        } catch(\Throwable $ex) {
            $this->database->exec('ROLLBACK');
            if($ex instanceof \PDOException) {
                throw new \RuntimeException(
                    'Error on the database side. Rolling back.',
                    0,
                    $ex
                );
            }
            throw $ex;
        }
    }
}