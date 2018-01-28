<?php
declare(strict_types = 1);

namespace Klapuch\Storage;

use PDO;
use Predis;

/**
 * PDO with meta info
 */
class MetaPDO extends \PDO {
	private const NAMESPACE = 'postgres:type:meta:';
	private static $cache = [];
	private $origin;
	private $redis;

	public function __construct(\PDO $origin, Predis\ClientInterface $redis) {
		$this->origin = $origin;
		$this->redis = $redis;
	}

	public function beginTransaction(): bool {
		return $this->origin->beginTransaction();
	}

	public function commit(): bool {
		return $this->origin->commit();
	}

	public function rollBack(): bool {
		return $this->origin->rollBack();
	}

	public function prepare($statement, $options = []): \PDOStatement {
		return new class(
			$this->origin->prepare($statement, $options),
			$statement,
			$this->redis,
			static::$cache
		) extends \PDOStatement {
			private const NAMESPACE = 'postgres:column:meta';
			private $origin;
			private $redis;
			private $statement;
			private $cache;

			public function __construct(
				\PDOStatement $origin,
				string $statement,
				Predis\ClientInterface $redis,
				array &$cache
			) {
				$this->origin = $origin;
				$this->redis = $redis;
				$this->statement = $statement;
				$this->cache = &$cache;
			}

			public function execute($inputParameters = null): bool {
				return $this->origin->execute(...func_get_args());
			}

			public function fetch(
				$fetchStyle = null,
				$cursorOrientation = \PDO::FETCH_ORI_NEXT,
				$cursorOffset = 0
			): array {
				return $this->origin->fetch(...func_get_args()) ?: [];
			}

			public function fetchAll(
				$fetchStyle = null,
				$fetchArgument = null,
				$ctorArgs = null
			): array {
				return $this->origin->fetchAll(...func_get_args());
			}

			/**
			 * @param int $columnNumber
			 * @return mixed
			 */
			public function fetchColumn($columnNumber = 0) {
				return $this->origin->fetchColumn(...func_get_args());
			}

			public function columnCount(): int {
				return $this->origin->columnCount();
			}

			public function getColumnMeta($column): array {
				$key = self::NAMESPACE . md5($this->statement);
				if (isset($this->cache['column'][$key][$column]))
					return $this->cache['column'][$key][$column];
				if (!$this->redis->hexists($key, $column)) {
					$this->redis->hset($key, $column, json_encode($this->origin->getColumnMeta($column)));
					$this->redis->persist($key);
				}
				return $this->cache['column'][$key][$column] = json_decode($this->redis->hget($key, $column), true);
			}
		};
	}

	/**
	 * @param string $statement
	 * @return int|bool
	 */
	public function exec($statement) {
		return $this->origin->exec(...func_get_args());
	}

	public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = []): \PDOStatement {
		return $this->origin->query(...func_get_args());
	}

	public function meta(string $type): array {
		if (isset(static::$cache['type'][$type]))
			return static::$cache['type'][$type];
		$key = self::NAMESPACE . $type;
		if (!$this->redis->exists($key)) {
			$statement = $this->origin->prepare(
				"SELECT attribute_name,
				types.data_type,
				ordinal_position,
				COALESCE(native_type, types.data_type) AS native_type
				FROM (
		 			SELECT attribute_name,
		 			CASE WHEN data_type = 'USER-DEFINED' THEN attribute_udt_name ELSE data_type END,
		 			ordinal_position
		 			FROM information_schema.attributes
					WHERE udt_name = lower(:type)
					UNION ALL
					SELECT
					column_name AS attribute_name,
					CASE WHEN data_type = 'USER-DEFINED' THEN udt_name ELSE data_type END,
					ordinal_position
					FROM information_schema.columns
					WHERE table_name = lower(:type)
					ORDER BY ordinal_position
				) types
				LEFT JOIN (
					SELECT data_type, native_type
					FROM (
						VALUES
						('integer', 'integer'),
						('character varying', 'string'),
						('text', 'string'),
						('character', 'string'),
						('numeric', 'double'),
						('bigint', 'integer'),
						('smallint', 'integer'),
						('boolean', 'boolean')
					) AS t (data_type, native_type)
				) native_types
				ON native_types.data_type = types.data_type"
			);
			$statement->execute(['type' => $type]);
			$this->redis->set($key, json_encode($statement->fetchAll()));
			$this->redis->persist($key);
		}
		return static::$cache['type'][$type] = json_decode($this->redis->get($key), true);
	}
}