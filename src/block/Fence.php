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

use pocketmine\block\utils\HorizontalConnectable;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function count;
use function in_array;

class Fence extends Transparent implements HorizontalConnectable{
	/** @var int[] facing => facing */
	protected array $connections = [];
	private bool $connectionsRecalculated = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacingFlags($this->connections);
	}

	/** @return int[] */
	public function getConnections() : array{ return $this->connections; }

	public function hasConnection(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	/**
	 * @param int[] $connections
	 * @return $this
	 */
	public function setConnections(array $connections) : self{
		$result = [];
		foreach($connections as $facing){
			if(!in_array($facing, Facing::HORIZONTAL, true)){
				throw new \InvalidArgumentException("Facing must be horizontal");
			}
			$result[$facing] = $facing;
		}
		$this->connections = $result;
		return $this;
	}

	/** @return $this */
	public function setConnection(int $facing, bool $connected) : self{
		if(!in_array($facing, Facing::HORIZONTAL, true)){
			throw new \InvalidArgumentException("Facing must be horizontal");
		}
		if($connected){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
		return $this;
	}

	public function getThickness() : float{
		return 0.25;
	}

	protected function recalculateConnections() : bool{
		$oldConnections = $this->connections;
		foreach(Facing::HORIZONTAL as $facing){
			$block = $this->getSide($facing);
			if($block instanceof static || $block instanceof FenceGate || $block->getSupportType(Facing::opposite($facing)) === SupportType::FULL){
				$this->connections[$facing] = $facing;
			}else{
				unset($this->connections[$facing]);
			}
		}
		return $this->connections !== $oldConnections;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		$this->connectionsRecalculated = $this->recalculateConnections();
		$this->collisionBoxes = null;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->recalculateConnections();
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$changed = $this->connectionsRecalculated;
		$this->connectionsRecalculated = false;
		if($this->recalculateConnections() || $changed){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	protected function recalculateCollisionBoxes() : array{
		$inset = 0.5 - $this->getThickness() / 2;

		$bbs = [];

		$connectWest = isset($this->connections[Facing::WEST]);
		$connectEast = isset($this->connections[Facing::EAST]);

		if($connectWest || $connectEast){
			//X axis (west/east)
			$bbs[] = AxisAlignedBB::one()
				->squash(Axis::Z, $inset)
				->extend(Facing::UP, 0.5)
				->trim(Facing::WEST, $connectWest ? 0 : $inset)
				->trim(Facing::EAST, $connectEast ? 0 : $inset);
		}

		$connectNorth = isset($this->connections[Facing::NORTH]);
		$connectSouth = isset($this->connections[Facing::SOUTH]);

		if($connectNorth || $connectSouth){
			//Z axis (north/south)
			$bbs[] = AxisAlignedBB::one()
				->squash(Axis::X, $inset)
				->extend(Facing::UP, 0.5)
				->trim(Facing::NORTH, $connectNorth ? 0 : $inset)
				->trim(Facing::SOUTH, $connectSouth ? 0 : $inset);
		}

		if(count($bbs) === 0){
			//centre post AABB (only needed if not connected on any axis - other BBs overlapping will do this if any connections are made)
			return [
				AxisAlignedBB::one()
					->extend(Facing::UP, 0.5)
					->contract($inset, 0, $inset)
			];
		}

		return $bbs;
	}

	public function getSupportType(int $facing) : SupportType{
		return Facing::axis($facing) === Axis::Y ? SupportType::CENTER : SupportType::NONE;
	}
}
