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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Facing;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\world\World;
use function array_reverse;
use function count;
use function microtime;
use function round;

final class UpgradeWorldsCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct("upgradeworlds", "Loads and saves every chunk to upgrade its block states");
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_PERFORM);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}
		if(count($args) !== 0){
			return false;
		}

		$start = microtime(true);
		$totalChunks = 0;
		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$chunkCoordinates = [];
			foreach($world->getProvider()->getAllChunks(true, $world->getLogger()) as $coordinates => $_){
				[$chunkX, $chunkZ] = $coordinates;
				$chunkCoordinates[World::chunkHash($chunkX, $chunkZ)] = [$chunkX, $chunkZ];
			}

			$sender->sendMessage("Upgrading world \"" . $world->getFolderName() . "\" (" . count($chunkCoordinates) . " chunks)...");
			$initiallyLoadedChunks = $world->getLoadedChunks();
			$previousAutoSave = $world->getAutoSave();
			$world->setAutoSave(true);
			try{
				foreach($chunkCoordinates as [$chunkX, $chunkZ]){
					$loadedForUpgrade = [];
					$chunksToLoad = [[$chunkX, $chunkZ]];
					foreach(Facing::HORIZONTAL as $facing){
						[$offsetX, , $offsetZ] = Facing::OFFSET[$facing];
						$chunksToLoad[] = [$chunkX + $offsetX, $chunkZ + $offsetZ];
					}

					foreach($chunksToLoad as [$loadX, $loadZ]){
						$chunkHash = World::chunkHash($loadX, $loadZ);
						if(!isset($chunkCoordinates[$chunkHash]) || $world->isChunkLoaded($loadX, $loadZ)){
							continue;
						}
						if($world->loadChunk($loadX, $loadZ) !== null){
							$loadedForUpgrade[$chunkHash] = [$loadX, $loadZ];
						}
					}

					foreach(array_reverse($loadedForUpgrade, true) as $chunkHash => [$loadX, $loadZ]){
						if(!isset($initiallyLoadedChunks[$chunkHash])){
							$world->unloadChunk($loadX, $loadZ, false, true);
						}
					}
					++$totalChunks;
				}
				$world->save(true);
			}finally{
				$world->setAutoSave($previousAutoSave);
			}
		}

		Command::broadcastCommandMessage($sender, "Upgraded $totalChunks chunks in " . round(microtime(true) - $start, 3) . " seconds");
		return true;
	}
}
