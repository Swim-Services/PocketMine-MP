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

/**
 * One piece of a persona (custom-model) skin, e.g. hair, eyes, arms, a cosmetic pack item, etc.
 *
 * This mirrors pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece, but lives outside the network
 * layer so that plugins and world storage don't need to depend on the protocol library just to describe a skin.
 */
final class PersonaSkinPiece{

	public function __construct(
		private string $pieceId,
		private int $pieceType,
		private string $packId,
		private bool $isDefaultPiece,
		private string $productId
	){}

	public function getPieceId() : string{
		return $this->pieceId;
	}

	public function getPieceType() : int{
		return $this->pieceType;
	}

	public function getPackId() : string{
		return $this->packId;
	}

	public function isDefaultPiece() : bool{
		return $this->isDefaultPiece;
	}

	public function getProductId() : string{
		return $this->productId;
	}
}
