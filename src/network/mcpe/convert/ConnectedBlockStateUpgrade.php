<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\StringTag;

/**
 * Network-only expansion of the pinned palette for the 1.26.60 client.
 * @see https://github.com/Mojang/bedrock-protocol-docs/blob/main/developer_notes/r26u5/us_1636001.md
 * @see https://github.com/Mojang/bedrock-protocol-docs/blob/main/developer_notes/r26u6/us_1614728.md
 */
final class ConnectedBlockStateUpgrade{
	/** @return list<string> */
	private static function propertyNames(string $name) : array{
		return match($name){
			BlockTypeNames::FIRE => ["minecraft:connection_north", "minecraft:connection_east", "minecraft:connection_south", "minecraft:connection_west", "minecraft:connection_up"],
			BlockTypeNames::REDSTONE_WIRE => ["redstone_north", "redstone_east", "redstone_south", "redstone_west"],
			default => []
		};
	}

	public static function stripConnectionProperties(BlockStateData $data) : BlockStateData{
		$properties = self::propertyNames($data->getName());
		if($properties === []){ return $data; }
		$states = $data->getStates();
		foreach($properties as $name){ unset($states[$name]); }
		return new BlockStateData($data->getName(), $states, $data->getVersion());
	}

	/** @return \Generator<int, BlockStateData> */
	public static function generatePermutations(BlockStateData $data) : \Generator{
		$properties = self::propertyNames($data->getName());
		if($properties === [] || $data->getState($properties[0]) !== null){
			yield $data;
			return;
		}
		$values = $data->getName() === BlockTypeNames::FIRE ? [new ByteTag(0), new ByteTag(1)] : [new StringTag("none"), new StringTag("side"), new StringTag("up")];
		$radix = count($values);
		for($i = 0, $count = $radix ** count($properties); $i < $count; ++$i){
			$states = $data->getStates();
			$permutation = $i;
			foreach($properties as $name){
				$states[$name] = $values[$permutation % $radix];
				$permutation = intdiv($permutation, $radix);
			}
			yield new BlockStateData($data->getName(), $states, $data->getVersion());
		}
	}
}
