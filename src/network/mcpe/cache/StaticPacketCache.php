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

namespace pocketmine\network\mcpe\cache;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemRegistryPacketEntry;
use pocketmine\utils\Filesystem;
use pocketmine\utils\ProtocolSingletonTrait;

class StaticPacketCache{
	private const ITEM_VERSION = 241;
	use ProtocolSingletonTrait;

	/**
	 * @phpstan-return CacheableNbt<\pocketmine\nbt\tag\CompoundTag>
	 */
	private static function loadCompoundFromFile(string $filePath) : CacheableNbt{
		return new CacheableNbt((new NetworkNbtSerializer())->read(Filesystem::fileGetContents($filePath))->mustGetCompoundTag());
	}

	private static function make(int $protocolId) : self{
		$itemRegistryEntries = [];
		foreach (TypeConverter::getInstance($protocolId)->getItemTypeDictionary()->getEntries() as $entry) {
			$itemRegistryEntries[] = new ItemRegistryPacketEntry($entry->getStringId(), $entry->getNumericId(), $entry->isComponentBased(), self::ITEM_VERSION, new CacheableNbt(new CompoundTag()));
		}
		return new self(
			BiomeDefinitionListPacket::create(self::loadCompoundFromFile(BedrockDataFiles::BIOME_DEFINITIONS_NBT)),
			AvailableActorIdentifiersPacket::create(self::loadCompoundFromFile(BedrockDataFiles::ENTITY_IDENTIFIERS_NBT)),
			ItemRegistryPacket::create($itemRegistryEntries)
		);
	}

	public function __construct(
		private BiomeDefinitionListPacket $biomeDefs,
		private AvailableActorIdentifiersPacket $availableActorIdentifiers,
		private ItemRegistryPacket $itemRegistry
	){}

	public function getBiomeDefs() : BiomeDefinitionListPacket{
		return $this->biomeDefs;
	}

	public function getAvailableActorIdentifiers() : AvailableActorIdentifiersPacket{
		return $this->availableActorIdentifiers;
	}

	public function getItemRegistry() : ItemRegistryPacket{
		return $this->itemRegistry;
	}
}
