<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

enum RedstoneWireConnection : string{
	case NONE = "none";
	case SIDE = "side";
	case UP = "up";
}
