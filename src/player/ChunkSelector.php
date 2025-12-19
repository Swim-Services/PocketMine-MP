<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\player;

use pocketmine\world\World;
use function round;
use const M_SQRT1_2;
use const M_SQRT2;

//TODO: turn this into an interface?
final class ChunkSelector{

	/**
	 * @return \Generator|int[]
	 * @phpstan-return \Generator<int, int, void, void>
	 */
	public function selectChunks(int $radius, int $centerX, int $centerZ) : \Generator{
		$radiusSquared = $radius ** 2;
		$nextRadiusSquared = ($radius + 1) ** 2;
		yield 0 => World::chunkHash($centerX, $centerZ);
		for ($x = 1; $x <= $radius; $x++) {
			$distSquared = ($x ** 2);
			yield $distSquared => World::chunkHash($centerX + $x, $centerZ);
			yield $distSquared => World::chunkHash($centerX - $x, $centerZ);
			if ($x !== 0) {
				yield $distSquared => World::chunkHash($centerX, $centerZ + $x);
				yield $distSquared => World::chunkHash($centerX, $centerZ - $x);
			}
		}

		for ($x = 1; $x <= $radius; $x++) {
			$distSquared = ($x * M_SQRT2) ** 2;
			if ($distSquared > $nextRadiusSquared) {
				break;
			}
			yield $distSquared => World::chunkHash($centerX + $x, $centerZ + $x);
			yield $distSquared => World::chunkHash($centerX - $x, $centerZ + $x);
			yield $distSquared => World::chunkHash($centerX + $x, $centerZ - $x);
			yield $distSquared => World::chunkHash($centerX - $x, $centerZ - $x);
		}

		$radiusPart = (int) round($radius * M_SQRT1_2);

		$x = $radius;
		for ($z = 1; $z < $radiusPart; $z++) {
			$zSquared = $z ** 2;
			$distSquared = ($x ** 2) + $zSquared;
			if ($distSquared > $nextRadiusSquared) {
				$x -= 1;
			}
			for ($xx = $x; $xx > $z; $xx--) {
				$distSquared = ($xx ** 2) + $zSquared;

				yield $distSquared => World::chunkHash($centerX - $xx, $centerZ + $z);
				yield $distSquared => World::chunkHash($centerX + $xx, $centerZ + $z);
				yield $distSquared => World::chunkHash($centerX + $xx, $centerZ - $z);
				yield $distSquared => World::chunkHash($centerX - $xx, $centerZ - $z);

				yield $distSquared => World::chunkHash($centerX - $z, $centerZ + $xx);
				yield $distSquared => World::chunkHash($centerX + $z, $centerZ + $xx);
				yield $distSquared => World::chunkHash($centerX + $z, $centerZ - $xx);
				yield $distSquared => World::chunkHash($centerX - $z, $centerZ - $xx);
			}
		}
	}
}
