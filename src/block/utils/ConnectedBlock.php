<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

/** A block whose visual state depends on neighbouring blocks. */
interface ConnectedBlock{

	public function recalculateConnections() : bool;
}
