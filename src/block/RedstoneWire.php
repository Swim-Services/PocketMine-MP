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

namespace pocketmine\block;

use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\ConnectedBlock;
use pocketmine\block\utils\RedstoneWireConnection;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function intdiv;

class RedstoneWire extends Flowable implements AnalogRedstoneSignalEmitter, ConnectedBlock{
	use AnalogRedstoneSignalEmitterTrait;
	use StaticSupportTrait { onNearbyBlockChange as private checkSupport; }

	/** @var array<int, RedstoneWireConnection> */
	private array $connections = [
		Facing::NORTH => RedstoneWireConnection::NONE,
		Facing::EAST => RedstoneWireConnection::NONE,
		Facing::SOUTH => RedstoneWireConnection::NONE,
		Facing::WEST => RedstoneWireConnection::NONE
	];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 15, $this->signalStrength);
		// Four ternary connections fit in 7 bits; four separate enums would
		// exceed the engine's 11-bit block-state limit once signal is included.
		$packed = 0;
		$multiplier = 1;
		$cases = RedstoneWireConnection::cases();
		foreach($this->connections as $connection){
			$packed += match($connection){
				RedstoneWireConnection::NONE => 0,
				RedstoneWireConnection::SIDE => 1,
				RedstoneWireConnection::UP => 2
			} * $multiplier;
			$multiplier *= 3;
		}
		$w->boundedIntAuto(0, 80, $packed);
		foreach($this->connections as &$connection){
			$connection = $cases[$packed % 3];
			$packed = intdiv($packed, 3);
		}
	}

	public function getConnection(int $facing) : RedstoneWireConnection{
		return $this->connections[$facing] ?? throw new \InvalidArgumentException("Facing must be horizontal");
	}

	/** @return $this */
	public function setConnection(int $facing, RedstoneWireConnection $connection) : self{
		if(!isset($this->connections[$facing])){
			throw new \InvalidArgumentException("Facing must be horizontal");
		}
		$this->connections[$facing] = $connection;
		return $this;
	}

	private function connectsTo(Block $block, int $facing) : bool{
		if($block instanceof RedstoneRepeater){
			return Facing::axis($block->getFacing()) === Facing::axis($facing);
		}
		return $block instanceof self || $block instanceof RedstoneComparator ||
			$block instanceof RedstoneTorch || $block instanceof Redstone ||
			$block instanceof Lever || $block instanceof Button || $block instanceof PressurePlate ||
			$block instanceof DaylightSensor || $block instanceof TrappedChest;
	}

	public function recalculateConnections() : bool{
		$old = $this->connections;
		$canClimb = $this->getSide(Facing::UP)->getSupportType(Facing::DOWN) !== SupportType::FULL;
		foreach(Facing::HORIZONTAL as $face){
			$side = $this->getSide($face);
			$connection = RedstoneWireConnection::NONE;
			if($this->connectsTo($side, $face)){
				$connection = RedstoneWireConnection::SIDE;
			}elseif($canClimb && $side->getSupportType(Facing::UP)->hasCenterSupport() && $side->getSide(Facing::UP) instanceof self){
				$connection = RedstoneWireConnection::UP;
			}elseif($side->getSupportType(Facing::opposite($face)) !== SupportType::FULL && $side->getSide(Facing::DOWN) instanceof self){
				$connection = RedstoneWireConnection::SIDE;
			}
			$this->connections[$face] = $connection;
		}
		// A single connection renders as a straight wire, rather than a half stub.
		$northSouth = $this->connections[Facing::NORTH] !== RedstoneWireConnection::NONE || $this->connections[Facing::SOUTH] !== RedstoneWireConnection::NONE;
		$eastWest = $this->connections[Facing::EAST] !== RedstoneWireConnection::NONE || $this->connections[Facing::WEST] !== RedstoneWireConnection::NONE;
		if($northSouth && !$eastWest){
			foreach([Facing::NORTH, Facing::SOUTH] as $face){
				if($this->connections[$face] === RedstoneWireConnection::NONE){
					$this->connections[$face] = RedstoneWireConnection::SIDE;
				}
			}
		}elseif($eastWest && !$northSouth){
			foreach([Facing::EAST, Facing::WEST] as $face){
				if($this->connections[$face] === RedstoneWireConnection::NONE){
					$this->connections[$face] = RedstoneWireConnection::SIDE;
				}
			}
		}
		return $old !== $this->connections;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->recalculateConnections();
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->checkSupport();
		}elseif($this->recalculateConnections()){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}
}
