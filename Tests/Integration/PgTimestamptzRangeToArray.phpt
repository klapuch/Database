<?php
declare(strict_types = 1);
/**
 * @testCase
 * @phpVersion > 7.1
 */
namespace Klapuch\Storage\Integration;

use Klapuch\Storage;
use Klapuch\Storage\TestCase;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

final class PgTimestamptzRangeToArray extends TestCase\PostgresDatabase {
	public function testConvertingToArray() {
		$ranges = (new Storage\PgTimestamptzRangeToArray(
			$this->database,
			'[2004-10-19 10:23:54.20+02,2005-10-19 10:23:54.20+02)'
		))->value();
		[$from, $to, $left, $right] = $ranges;
		Assert::same('2004-10-19 08:23:54.200000+0000', (string) $from);
		Assert::same('2005-10-19 08:23:54.200000+0000', (string) $to);
		Assert::same('[', $left);
		Assert::same(')', $right);
	}

	public function testAllowingNull() {
		Assert::null((new Storage\PgTimestamptzRangeToArray($this->database, null))->value());
	}
}

(new PgTimestamptzRangeToArray())->run();