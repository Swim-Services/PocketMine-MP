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

namespace pocketmine\network\mcpe\convert;

use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\PersonaPieceTintColor as EntityPersonaPieceTintColor;
use pocketmine\entity\PersonaSkinPiece as EntityPersonaSkinPiece;
use pocketmine\entity\Skin;
use pocketmine\entity\SkinAnimation as EntitySkinAnimation;
use pocketmine\network\mcpe\protocol\serializer\LegacySkinDataConverter;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use Ramsey\Uuid\Uuid;
use function array_map;
use function is_array;
use function is_string;
use function json_decode;

class LegacySkinAdapter implements SkinAdapter{

	public function toSkinData(Skin $skin) : SkinData{
		$capeData = $skin->getCapeData();
		$capeImage = $capeData === "" ? new SkinImage(0, 0, "") : new SkinImage(32, 64, $capeData);

		$animations = array_map(
			static fn(EntitySkinAnimation $animation) : SkinAnimation => new SkinAnimation(
				new SkinImage($animation->getImageHeight(), $animation->getImageWidth(), $animation->getImageData()),
				$animation->getAnimationType(),
				$animation->getFrames(),
				$animation->getExpressionType()
			),
			$skin->getAnimations()
		);

		$personaPieces = array_map(
			static fn(EntityPersonaSkinPiece $piece) : PersonaSkinPiece => new PersonaSkinPiece(
				$piece->getPieceId(),
				$piece->getPieceType(),
				Uuid::fromString($piece->getPackId() !== "" ? $piece->getPackId() : Uuid::NIL),
				$piece->isDefaultPiece(),
				$piece->getProductId()
			),
			$skin->getPersonaPieces()
		);

		$pieceTintColors = array_map(
			static fn(EntityPersonaPieceTintColor $tint) : PersonaPieceTintColor => new PersonaPieceTintColor(
				LegacySkinDataConverter::personaPieceTypeToString($tint->getPieceType()),
				$tint->getColors()
			),
			$skin->getPieceTintColors()
		);

		return new SkinData(
			$skin->getSkinId(),
			$skin->getPlayFabId(),
			$skin->getResourcePatch(),
			SkinImage::fromLegacy($skin->getSkinData()),
			$animations,
			$capeImage,
			$skin->getGeometryData(),
			$skin->getGeometryDataEngineVersion(),
			$skin->getAnimationData(),
			$skin->getCapeId(),
			$skin->getFullSkinId(),
			$skin->getArmSize(),
			$skin->getSkinColor(),
			$personaPieces,
			$pieceTintColors,
			true,
			$skin->isPremium(),
			$skin->isPersona(),
			$skin->isPersonaCapeOnClassic(),
			$skin->isPrimaryUser(),
			$skin->isOverride(),
			$skin->getTrustedSkinFlag(),
			$skin->getProfileHash()
		);
	}

	public function fromSkinData(SkinData $data) : Skin{
		$capeData = $data->isPersonaCapeOnClassic() ? "" : $data->getCapeImage()->getData();

		$resourcePatch = json_decode($data->getResourcePatch(), true);
		if(is_array($resourcePatch) && isset($resourcePatch["geometry"]["default"]) && is_string($resourcePatch["geometry"]["default"])){
			$geometryName = $resourcePatch["geometry"]["default"];
		}else{
			throw new InvalidSkinException("Missing geometry name field");
		}

		$animations = array_map(
			static fn(SkinAnimation $animation) : EntitySkinAnimation => new EntitySkinAnimation(
				$animation->getImage()->getWidth(),
				$animation->getImage()->getHeight(),
				$animation->getImage()->getData(),
				$animation->getType(),
				$animation->getFrames(),
				$animation->getExpressionType()
			),
			$data->getAnimations()
		);

		$personaPieces = array_map(
			static fn(PersonaSkinPiece $piece) : EntityPersonaSkinPiece => new EntityPersonaSkinPiece(
				$piece->getPieceId(),
				$piece->getPieceType(),
				$piece->getPackId()->toString(),
				$piece->isDefaultPiece(),
				$piece->getProductId()
			),
			$data->getPersonaPieces()
		);

		$pieceTintColors = array_map(
			static fn(PersonaPieceTintColor $tint) : EntityPersonaPieceTintColor => new EntityPersonaPieceTintColor(
				LegacySkinDataConverter::personaPieceTypeFromString($tint->getPieceType()),
				$tint->getColors()
			),
			$data->getPieceTintColors()
		);

		return new Skin(
			$data->getSkinId(),
			$data->getSkinImage()->getData(),
			$capeData,
			$geometryName,
			$data->getGeometryData(),
			$data->getPlayFabId(),
			$data->getResourcePatch(),
			$data->getGeometryDataEngineVersion(),
			$data->getAnimationData(),
			$data->getCapeId(),
			$data->getFullSkinId(),
			$data->getArmSize(),
			$data->getSkinColor(),
			$personaPieces,
			$pieceTintColors,
			$animations,
			$data->isPremium(),
			$data->isPersona(),
			$data->isPersonaCapeOnClassic(),
			$data->isPrimaryUser(),
			$data->isOverride(),
			$data->getTrustedSkinFlag(),
			$data->getProfileHash()
		);
	}
}
