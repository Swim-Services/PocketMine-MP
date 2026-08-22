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

/**
 * Thin blocks behave like glass panes. They connect to full-cube blocks horizontally adjacent to them if possible.
 */
class Thin extends Transparent implements HorizontalConnectable{
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

	public function recalculateConnections() : bool{
		$oldConnections = $this->connections;
		foreach(Facing::HORIZONTAL as $facing){
			$side = $this->getSide($facing);
			if($side instanceof Thin || $side instanceof Wall || $side->getSupportType(Facing::opposite($facing)) === SupportType::FULL){
				$this->connections[$facing] = $facing;
			}else{
				unset($this->connections[$facing]);
			}
		}
		return $this->connections !== $oldConnections;
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
		$inset = 7 / 16;

		$bbs = [];

		if(isset($this->connections[Facing::WEST]) || isset($this->connections[Facing::EAST])){
			$bb = AxisAlignedBB::one()->squash(Axis::Z, $inset);

			if(!isset($this->connections[Facing::WEST])){
				$bb->trim(Facing::WEST, $inset);
			}elseif(!isset($this->connections[Facing::EAST])){
				$bb->trim(Facing::EAST, $inset);
			}
			$bbs[] = $bb;
		}

		if(isset($this->connections[Facing::NORTH]) || isset($this->connections[Facing::SOUTH])){
			$bb = AxisAlignedBB::one()->squash(Axis::X, $inset);

			if(!isset($this->connections[Facing::NORTH])){
				$bb->trim(Facing::NORTH, $inset);
			}elseif(!isset($this->connections[Facing::SOUTH])){
				$bb->trim(Facing::SOUTH, $inset);
			}
			$bbs[] = $bb;
		}

		if(count($bbs) === 0){
			//centre post AABB (only needed if not connected on any axis - other BBs overlapping will do this if any connections are made)
			return [
				AxisAlignedBB::one()->contract($inset, 0, $inset)
			];
		}

		return $bbs;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}
}
