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

namespace pocketmine\entity;

use function count;

/**
 * Tint color(s) applied to a single persona skin piece (e.g. hair, eyes, mouth).
 *
 * Mirrors pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor without depending on the network layer.
 */
final class PersonaPieceTintColor{

	public const COLOR_COUNT = 4;

	/**
	 * @param int[] $colors
	 * @phpstan-param list<int> $colors
	 */
	public function __construct(
		private int $pieceType,
		private array $colors
	){
		if(count($colors) !== self::COLOR_COUNT){
			throw new InvalidSkinException("Expected exactly " . self::COLOR_COUNT . " tint colors, got " . count($colors));
		}
	}

	public function getPieceType() : int{
		return $this->pieceType;
	}

	/**
	 * @return int[]
	 * @phpstan-return list<int>
	 */
	public function getColors() : array{
		return $this->colors;
	}
}
