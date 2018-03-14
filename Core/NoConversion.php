<?php
declare(strict_types = 1);

namespace Klapuch\Storage;

final class NoConversion implements Conversion {
	public function value(): void {
		throw new \RuntimeException('End of conversion delegation.');
	}
}