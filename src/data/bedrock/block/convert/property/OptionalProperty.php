<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock\block\convert\property;

use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;

/**
 * Retains the block's default for a visual property absent in older saved worlds.
 * @phpstan-template TBlock of object
 * @phpstan-implements Property<TBlock>
 */
final class OptionalProperty implements Property{
	/** @phpstan-param Property<TBlock> $property */
	public function __construct(private Property $property){}

	public function getName() : string{ return $this->property->getName(); }

	public function deserialize(object $block, BlockStateReader $in) : void{
		if($in->hasState($this->getName())){
			$this->property->deserialize($block, $in);
		}
	}

	public function serialize(object $block, BlockStateWriter $out) : void{
		$this->property->serialize($block, $out);
	}
}
