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
namespace pocketmine\entity\utils;

use InvalidArgumentException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataTypes;
use pocketmine\network\mcpe\protocol\types\entity\IntegerishMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use pocketmine\network\mcpe\protocol\types\GetTypeIdFromConstTrait;
use pmmp\encoding\ByteBufferWriter;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class UnlimitedIntMetadataProperty implements MetadataProperty {
	use GetTypeIdFromConstTrait;
	use IntegerishMetadataProperty;

	public const ID = EntityMetadataTypes::INT;
	protected function min() : int{
		return PHP_INT_MIN;
	}

	protected function max() : int{
		return PHP_INT_MAX;
	}

	public static function read(PacketSerializer $in) : self{
		return new self($in->getVarInt());
	}

	/* Outdated due to ext-encoding MetadataProperty now requiring ByteBufferWriter for write(..)
	public function write(PacketSerializer $out) : void{
		$out->putVarInt($this->value);
	}
	*/

	public function write(ByteBufferWriter $out) : void{
		// Zigzag-encode 32-bit signed int, then write as unsigned VarInt (max 5 bytes)
		$v = $this->value;
		$u = (($v << 1) ^ ($v >> 31));     // zigzag to unsigned
		$remaining = $u & 0xffffffff;      // constrain to 32-bit like Binary::writeUnsignedVarInt()

		for($i = 0; $i < 5; ++$i){
			if(($remaining >> 7) !== 0){
				// write low 7 bits with continuation flag
				$out->writeByteArray(chr(($remaining & 0xFF) | 0x80));
			}else{
				// last byte, no continuation
				$out->writeByteArray(chr($remaining & 0x7F));
				return;
			}
			// logical right shift by 7; PHP has only arithmetic >>, so mask
			$remaining = (($remaining >> 7) & (PHP_INT_MAX >> 6));
		}

		// Should never happen for 32-bit values
		throw new InvalidArgumentException("Value too large to be encoded as a VarInt");
	}

}
