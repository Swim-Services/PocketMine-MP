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

use function strlen;

/**
 * One animated overlay frame-set for a skin (e.g. an animated face, animated cloak overlay, etc).
 *
 * Mirrors pocketmine\network\mcpe\protocol\types\skin\SkinAnimation without depending on the network layer.
 * Unlike the flat skin/cape textures, animation frames aren't restricted to a fixed set of sizes, so width and
 * height are stored explicitly instead of being inferred from the data length.
 */
final class SkinAnimation{

	public function __construct(
		private int $imageWidth,
		private int $imageHeight,
		private string $imageData,
		private int $animationType,
		private float $frames,
		private int $expressionType
	){
		if($imageWidth < 0 || $imageHeight < 0){
			throw new InvalidSkinException("Animation image width and height cannot be negative");
		}
		$expected = $imageWidth * $imageHeight * 4;
		if(strlen($imageData) !== $expected){
			throw new InvalidSkinException("Animation image data should be exactly $expected bytes, got " . strlen($imageData) . " bytes");
		}
	}

	public function getImageWidth() : int{
		return $this->imageWidth;
	}

	public function getImageHeight() : int{
		return $this->imageHeight;
	}

	public function getImageData() : string{
		return $this->imageData;
	}

	public function getAnimationType() : int{
		return $this->animationType;
	}

	public function getFrames() : float{
		return $this->frames;
	}

	public function getExpressionType() : int{
		return $this->expressionType;
	}
}
