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
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function in_array;

class Tripwire extends Flowable implements HorizontalConnectable{
	protected bool $triggered = false;
	protected bool $suspended = false; //unclear usage, makes hitbox bigger if set
	protected bool $connected = false;
	protected bool $disarmed = false;
	/** @var int[] facing => facing */
	protected array $connections = [];
	private bool $connectionsRecalculated = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->triggered);
		$w->bool($this->suspended);
		$w->bool($this->connected);
		$w->bool($this->disarmed);
		$w->horizontalFacingFlags($this->connections);
	}

	public function isTriggered() : bool{ return $this->triggered; }

	/** @return $this */
	public function setTriggered(bool $triggered) : self{
		$this->triggered = $triggered;
		return $this;
	}

	public function isSuspended() : bool{ return $this->suspended; }

	/** @return $this */
	public function setSuspended(bool $suspended) : self{
		$this->suspended = $suspended;
		return $this;
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isDisarmed() : bool{ return $this->disarmed; }

	/** @return $this */
	public function setDisarmed(bool $disarmed) : self{
		$this->disarmed = $disarmed;
		return $this;
	}

	/** @return int[] */
	public function getConnections() : array{ return $this->connections; }

	public function hasConnection(int $facing) : bool{
		return isset($this->connections[$facing]);
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

	/** @return int[] */
	private function calculateConnections() : array{
		$connections = [];
		foreach(Facing::HORIZONTAL as $facing){
			if($this->getSide($facing) instanceof self){
				$connections[$facing] = $facing;
			}
		}
		return $connections;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();

		$connections = $this->calculateConnections();
		$this->connectionsRecalculated = $connections !== $this->connections;
		$this->connections = $connections;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->connections = $this->calculateConnections();
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$connections = $this->calculateConnections();
		$changed = $this->connectionsRecalculated || $connections !== $this->connections;
		$this->connectionsRecalculated = false;
		if($changed){
			$this->connections = $connections;
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	public function asItem() : Item{
		return VanillaItems::STRING();
	}
}
