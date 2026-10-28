<?php

declare(strict_types=1);

// Run with the PocketMine PHP runtime: php tests/integration/connected-block-states.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\RedstoneWireConnection as Connection;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\item\ItemBlock;
use pocketmine\math\Facing;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\network\mcpe\convert\ConnectedBlockStateUpgrade;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

function check(bool $condition, string $message) : void{
	if(!$condition){ throw new RuntimeException($message); }
}

// Real block classes with a small in-memory neighbour grid; no services or worlds
// on disk are touched by the topology tests.
final class ConnectionTestWorld extends World{
	/** @var array<string, Block> */
	public array $blocks = [];
	public function __construct(){}
	public function isLoaded() : bool{ return true; }
	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block{
		$block = clone ($this->blocks["$x:$y:$z"] ?? VanillaBlocks::AIR());
		$block->position($this, $x, $y, $z);
		return $block;
	}
}

$world = new ConnectionTestWorld();
$fire = VanillaBlocks::FIRE()->setAge(7);
$fire->position($world, 0, 1, 0);
$world->blocks['0:0:0'] = VanillaBlocks::STONE();
$world->blocks['1:1:0'] = VanillaBlocks::OAK_PLANKS();
$fire->recalculateConnections();
check(!$fire->hasConnection(Facing::EAST), 'Ground fire must not attach to an adjacent wall');
unset($world->blocks['0:0:0']);
check($fire->recalculateConnections() && $fire->hasConnection(Facing::EAST), 'Unsupported fire must attach to flammable wall');
$world->blocks['0:2:0'] = VanillaBlocks::OAK_PLANKS();
$fire->recalculateConnections();
check($fire->hasConnection(Facing::UP), 'Fire must attach to flammable ceiling');
unset($world->blocks['1:1:0'], $world->blocks['0:2:0']);
$fire->recalculateConnections();
check(!$fire->hasConnection(Facing::EAST) && !$fire->hasConnection(Facing::UP), 'Removed fire attachments must clear');

$wire = VanillaBlocks::REDSTONE_WIRE()->setOutputSignalStrength(9);
$wire->position($world, 0, 1, 0);
$wire->recalculateConnections();
check($wire->getConnection(Facing::NORTH) === Connection::NONE, 'Isolated redstone must be a dot');
$world->blocks['1:1:0'] = VanillaBlocks::REDSTONE_WIRE();
$wire->recalculateConnections();
check($wire->getConnection(Facing::EAST) === Connection::SIDE && $wire->getConnection(Facing::WEST) === Connection::SIDE, 'Single neighbour must form a straight wire');
$world->blocks['0:1:-1'] = VanillaBlocks::REDSTONE_WIRE();
$wire->recalculateConnections();
check($wire->getConnection(Facing::NORTH) === Connection::SIDE && $wire->getConnection(Facing::WEST) === Connection::NONE, 'Corner must not keep stale straight extension');
$world->blocks = ['1:1:0' => VanillaBlocks::STONE(), '1:2:0' => VanillaBlocks::REDSTONE_WIRE()];
$wire->recalculateConnections();
check($wire->getConnection(Facing::EAST) === Connection::UP, 'Redstone must climb a supported step');
$world->blocks['0:2:0'] = VanillaBlocks::STONE();
$wire->recalculateConnections();
check($wire->getConnection(Facing::EAST) === Connection::NONE, 'Ceiling must block upward redstone connection');
$world->blocks = ['1:0:0' => VanillaBlocks::REDSTONE_WIRE()];
$wire->recalculateConnections();
check($wire->getConnection(Facing::EAST) === Connection::SIDE, 'Redstone must connect down a step');
$world->blocks = [];
$wire->recalculateConnections();
check($wire->getConnection(Facing::EAST) === Connection::NONE, 'Removed redstone neighbour must clear connection');

$serializer = GlobalBlockStateHandlers::getSerializer();
$deserializer = GlobalBlockStateHandlers::getDeserializer();
$translator = BlockTranslator::loadFromProtocolId(ProtocolInfo::PROTOCOL_1_26_60);
$legacy = BlockTranslator::loadFromProtocolId(ProtocolInfo::PROTOCOL_1_26_50);
$registry = RuntimeBlockStateRegistry::getInstance();
// FortunePillars can give fire as an ItemBlock. Equipment/inventory broadcasts
// use ItemTranslator directly, bypassing BlockTranslator's world-block path.
foreach([ProtocolInfo::PROTOCOL_1_26_50 => $legacy, ProtocolInfo::PROTOCOL_1_26_60 => $translator] as $protocol => $blocks){
	$items = ItemTypeDictionaryFromDataHelper::loadFromProtocolId($protocol);
	$downgrader = new ItemIdMetaDowngrader($items, ItemTranslator::getItemSchemaId($protocol));
	$itemTranslator = new ItemTranslator(
		$items, $blocks->getBlockStateDictionary(),
		GlobalItemDataHandlers::getSerializer(), GlobalItemDataHandlers::getDeserializer(),
		BlockItemIdMap::getInstance(), $downgrader
	);
	foreach([VanillaBlocks::FIRE(), VanillaBlocks::FIRE()->setAge(7)->setConnection(Facing::EAST, true), VanillaBlocks::REDSTONE_WIRE()->setConnection(Facing::EAST, Connection::UP)] as $block){
		$item = new ItemBlock($block);
		$result = $itemTranslator->toNetworkId($item);
		check($itemTranslator->toNetworkIdQuiet($item) === $result, 'Quiet item conversion must also serialize connected blocks');
		check($result[2] !== null, 'Connected block item must have a runtime ID');
		$state = $blocks->getBlockStateDictionary()->generateDataFromStateId($result[2]);
		check($state !== null && $state->getName() === $serializer->serialize($block->getStateId())->getName(), 'Connected item must retain its block type, not use a fallback');
		$savedBlock = GlobalItemDataHandlers::getSerializer()->serializeType($item, $downgrader)->getBlock();
		check($savedBlock !== null, 'Regression item must serialize a real blockstate');
		$expected = $protocol === ProtocolInfo::PROTOCOL_1_26_60 ? $savedBlock : ConnectedBlockStateUpgrade::stripConnectionProperties($savedBlock);
		check(BlockStateDictionaryEntry::encodeStateProperties($state->getStates()) === BlockStateDictionaryEntry::encodeStateProperties($expected->getStates()), 'Item must use the recipient palette: legacy states for 1.26.50, connections retained for 1.26.60');
	}
}
check($legacy->getBlockStateDictionary()->lookupStateIdFromData(BlockStateData::current('minecraft:fire', ['age' => new IntTag(16)])) === null, 'Invalid fire age must still be rejected');
foreach(['blockStateRegistry' => $registry, 'logger' => new class extends SimpleLogger{
	public function log($level, $message){}
}, 'minY' => 0, 'maxY' => 16] as $property => $value){
	(new ReflectionProperty(World::class, $property))->setValue($world, $value);
}
$chunk = new Chunk([], true);
$chunk->setBlockStateId(0, 1, 0, VanillaBlocks::FIRE()->getStateId());
$chunk->setBlockStateId(4, 1, 4, VanillaBlocks::REDSTONE_WIRE()->getStateId());
$world->blocks = ['1:1:0' => VanillaBlocks::OAK_PLANKS(), '5:1:4' => VanillaBlocks::REDSTONE_WIRE()];
$migration = new ReflectionMethod(World::class, 'recalculateChunkBlockStatePass');
[$candidates, $changes] = $migration->invoke($world, 0, 0, $chunk, null, false, false);
check($candidates === 2 && $changes === 2, 'Loaded chunk connection pass must upgrade fire and redstone');
check($registry->fromStateId($chunk->getBlockStateId(0, 1, 0))->hasConnection(Facing::EAST), 'Chunk migration must preserve fire wall attachment');
check($registry->fromStateId($chunk->getBlockStateId(4, 1, 4))->getConnection(Facing::EAST) === Connection::SIDE, 'Chunk migration must store redstone connection');
$edgeChunk = new Chunk([], true);
$edgeChunk->setBlockStateId(15, 1, 0, VanillaBlocks::REDSTONE_WIRE()->getStateId());
$world->blocks = ['16:1:0' => VanillaBlocks::REDSTONE_WIRE()];
[$candidates, $changes] = $migration->invoke($world, 0, 0, $edgeChunk, Facing::EAST, false, false);
check($candidates === 1 && $changes === 1, 'Chunk edge reconciliation must connect across chunk boundaries');

$count = 0;
foreach([
	BlockStateData::current('minecraft:fire', ['age' => new IntTag(0)]),
	BlockStateData::current('minecraft:redstone_wire', ['redstone_signal' => new IntTag(0)])
] as $base){
	// Old disk states must still deserialize, even though they have no connection fields.
	$deserializer->deserialize($base);
	foreach(ConnectedBlockStateUpgrade::generatePermutations($base) as $permutation){
		for($level = 0; $level <= 15; ++$level){
			$states = $permutation->getStates();
			$states[$base->getName() === 'minecraft:fire' ? 'age' : 'redstone_signal'] = new IntTag($level);
			$data = BlockStateData::current($base->getName(), $states);
			$stateId = $deserializer->deserialize($data);
			check($registry->hasStateId($stateId), 'Connection permutation must have a registered runtime state');
			check($serializer->serialize($stateId)->equals($data), 'Age/signal and connections must survive disk roundtrip');
			$id = $translator->internalIdToNetworkId($stateId);
			$networkData = $translator->internalIdToNetworkStateData($stateId);
			check($networkData->equals($data), 'Network lookup must retain exact connected state');
			check($networkData->getState('minecraft:connection_down') === null, 'Fire must never hash a connection_down property');
			ksort($states, SORT_STRING);
			$stateTag = CompoundTag::create();
			foreach($states as $name => $value){ $stateTag->setTag($name, $value); }
			$tag = CompoundTag::create()->setString('name', $data->getName())->setTag('states', $stateTag);
			$expectedHash = hexdec(hash('fnv1a32', (new LittleEndianNbtSerializer())->write(new TreeRoot($tag))));
			check($id === $expectedHash, 'Network ID must hash the transmitted state, not a legacy or fallback state');
			$legacyData = $legacy->internalIdToNetworkStateData($stateId);
			check($legacyData->getName() === $base->getName(), '1.26.50 client must retain the original block type');
			++$count;
		}
	}
}
check(ProtocolInfo::CURRENT_PROTOCOL === ProtocolInfo::PROTOCOL_1_26_50, 'Current protocol must remain 1.26.50');
check(ProtocolInfo::MINECRAFT_VERSION_NETWORK === '1.26.50', 'Advertised base version must remain 1.26.50');
check(in_array(ProtocolInfo::PROTOCOL_1_26_60, ProtocolInfo::ACCEPTED_PROTOCOL, true), '1.26.60 must remain accepted through forward multiversion');
check(in_array(ProtocolInfo::PROTOCOL_1_26_50, ProtocolInfo::ACCEPTED_PROTOCOL, true), '1.26.50 must remain accepted');
echo "Passed topology and chunk migration/boundary checks, plus $count connected state/hash/disk/legacy roundtrips\n";
