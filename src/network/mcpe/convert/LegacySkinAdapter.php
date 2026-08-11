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
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\LegacySkinDataConverter;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\utils\Filesystem;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Path;
use function array_map;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

class LegacySkinAdapter implements SkinAdapter{
	/**
	 * Vanilla player geometries that clients reference without ever sending a definition for them.
	 * Both the wide (Steve) and the slim (Alex) variant have to be covered - a slim skin references
	 * geometry.humanoid.customSlim and is just as empty as a wide one.
	 */
	private const DEFAULT_GEOMETRY_NAMES = [
		"geometry.humanoid.custom" => true,
		"geometry.humanoid.customSlim" => true,
	];

	/**
	 * @var array<string, string>|null
	 * @phpstan-var array<string, string>|null
	 */
	private static ?array $defaultGeometryData = null;

	public function __construct(
		private int $protocolId = ProtocolInfo::CURRENT_PROTOCOL
	){}

	/**
	 * Returns a standalone geometry document containing only the requested definition, so that a skin
	 * never carries geometries it doesn't reference.
	 */
	private static function defaultGeometryFor(string $geometryName) : ?string{
		if(self::$defaultGeometryData === null){
			$decoded = json_decode(Filesystem::fileGetContents(
				Path::join(\pocketmine\RESOURCE_PATH, "default_skin_geometry.json")
			), true);
			$formatVersion = is_array($decoded) && is_string($decoded["format_version"] ?? null) ? $decoded["format_version"] : "1.21.0";
			$geometries = is_array($decoded) && is_array($decoded["minecraft:geometry"] ?? null) ? $decoded["minecraft:geometry"] : [];

			$result = [];
			foreach($geometries as $geometry){
				$identifier = is_array($geometry) ? ($geometry["description"]["identifier"] ?? null) : null;
				if(!is_string($identifier)){
					continue;
				}
				$encoded = json_encode([
					"format_version" => $formatVersion,
					"minecraft:geometry" => [$geometry],
				]);
				if($encoded !== false){
					$result[$identifier] = $encoded;
				}
			}
			self::$defaultGeometryData = $result;
		}

		return self::$defaultGeometryData[$geometryName] ?? null;
	}

	/**
	 * Since 1.26.40 the client drops the connection when a skin names a geometry it doesn't ship a
	 * definition for. Default skins do exactly that, so the definition has to be filled in for them.
	 */
	private function geometryDataFor(Skin $skin) : string{
		$geometryData = $skin->getGeometryData();
		if($geometryData !== "" || $this->protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			return $geometryData;
		}

		$resourcePatch = json_decode($skin->getResourcePatch(), true);
		$geometryName = is_array($resourcePatch) ? ($resourcePatch["geometry"]["default"] ?? null) : null;
		if(!is_string($geometryName) || !isset(self::DEFAULT_GEOMETRY_NAMES[$geometryName])){
			return $geometryData;
		}

		return self::defaultGeometryFor($geometryName) ?? $geometryData;
	}

	public function toSkinData(Skin $skin) : SkinData{
		$capeData = $skin->getCapeData();
		$capeImage = $capeData === "" ? new SkinImage(0, 0, "") : new SkinImage($skin->getCapeImageHeight(), $skin->getCapeImageWidth(), $capeData);

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
			new SkinImage($skin->getSkinImageHeight(), $skin->getSkinImageWidth(), $skin->getSkinData()),
			$animations,
			$capeImage,
			$this->geometryDataFor($skin),
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
			skinId: $data->getSkinId(),
			skinData: $data->getSkinImage()->getData(),
			capeData: $capeData,
			geometryName: $geometryName,
			geometryData: $data->getGeometryData(),
			playFabId: $data->getPlayFabId(),
			resourcePatch: $data->getResourcePatch(),
			geometryDataEngineVersion: $data->getGeometryDataEngineVersion(),
			animationData: $data->getAnimationData(),
			capeId: $data->getCapeId(),
			fullSkinId: $data->getFullSkinId(),
			armSize: $data->getArmSize(),
			skinColor: $data->getSkinColor(),
			personaPieces: $personaPieces,
			pieceTintColors: $pieceTintColors,
			animations: $animations,
			premium: $data->isPremium(),
			persona: $data->isPersona(),
			personaCapeOnClassic: $data->isPersonaCapeOnClassic(),
			isPrimaryUser: $data->isPrimaryUser(),
			override: $data->isOverride(),
			trustedSkinFlag: $data->getTrustedSkinFlag(),
			profileHash: $data->getProfileHash(),
			skinImageWidth: $data->getSkinImage()->getWidth(),
			skinImageHeight: $data->getSkinImage()->getHeight(),
			capeImageWidth: $data->getCapeImage()->getWidth(),
			capeImageHeight: $data->getCapeImage()->getHeight(),
		);
	}
}
