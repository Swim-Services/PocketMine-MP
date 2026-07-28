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

namespace pocketmine\entity;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\Limits;
use function array_keys;
use function count;
use function implode;
use function json_encode;
use function sprintf;
use function strlen;
use const JSON_THROW_ON_ERROR;

final class Skin{
	/**
	 * Legacy fixed skin dimensions, kept only as a fallback for callers that don't know their skin's actual
	 * width/height (e.g. loading a flat PNG). Real clients are not limited to these sizes - Mojang has shipped
	 * higher resolutions since (e.g. 256x256), so anything with an explicit width/height is accepted as long
	 * as width*height*4 matches the data length.
	 */
	private const LEGACY_SKIN_DIMENSIONS = [
		64 * 32 * 4 => [64, 32],
		64 * 64 * 4 => [64, 64],
		128 * 128 * 4 => [128, 128],
	];
	private const LEGACY_CAPE_DIMENSIONS = [
		64 * 32 * 4 => [64, 32],
	];

	public const ARM_SIZE_SLIM = 0;
	public const ARM_SIZE_WIDE = 1;

	public const TRUSTED_SKIN_FLAG_UNSET = "Unset";
	public const TRUSTED_SKIN_FLAG_FALSE = "False";
	public const TRUSTED_SKIN_FLAG_TRUE = "True";

	private string $skinId;
	private string $skinData;
	private int $skinImageWidth;
	private int $skinImageHeight;
	private string $capeData;
	private int $capeImageWidth;
	private int $capeImageHeight;
	private string $geometryName;
	private string $geometryData;

	private string $playFabId;
	private string $resourcePatch;
	private string $geometryDataEngineVersion;
	private string $animationData;
	private string $capeId;
	private string $fullSkinId;
	private int $armSize;
	private int $skinColor;

	/** @var PersonaSkinPiece[] */
	private array $personaPieces;
	/** @var PersonaPieceTintColor[] */
	private array $pieceTintColors;
	/** @var SkinAnimation[] */
	private array $animations;

	private bool $premium;
	private bool $persona;
	private bool $personaCapeOnClassic;
	private bool $isPrimaryUser;
	private bool $override;
	private string $trustedSkinFlag;
	private string $profileHash;

	private static function checkLength(string $string, string $name, int $maxLength) : void{
		if(strlen($string) > $maxLength){
			throw new InvalidSkinException("$name must be at most $maxLength bytes, but have " . strlen($string) . " bytes");
		}
	}

	/**
	 * @param array<int, array{int, int}> $legacyDimensions
	 * @phpstan-return array{int, int}
	 */
	private static function resolveImageDimensions(string $data, ?int $width, ?int $height, string $name, array $legacyDimensions) : array{
		if($width !== null && $height !== null){
			$expected = $width * $height * 4;
			if($expected !== strlen($data)){
				throw new InvalidSkinException("$name is declared as {$width}x{$height} (expected $expected bytes) but data is " . strlen($data) . " bytes");
			}
			return [$width, $height];
		}

		return $legacyDimensions[strlen($data)] ??
			throw new InvalidSkinException("Invalid $name size " . strlen($data) . " bytes with no explicit width/height given (allowed implicit sizes: " . implode(", ", array_keys($legacyDimensions)) . ")");
	}

	/**
	 * @param PersonaSkinPiece[]      $personaPieces
	 * @param PersonaPieceTintColor[] $pieceTintColors
	 * @param SkinAnimation[]         $animations
	 */
	public function __construct(
		string $skinId,
		string $skinData,
		string $capeData = "",
		string $geometryName = "",
		string $geometryData = "",
		string $playFabId = "",
		?string $resourcePatch = null,
		string $geometryDataEngineVersion = ProtocolInfo::MINECRAFT_VERSION_NETWORK,
		string $animationData = "",
		string $capeId = "",
		?string $fullSkinId = null,
		int $armSize = self::ARM_SIZE_WIDE,
		int $skinColor = 0,
		array $personaPieces = [],
		array $pieceTintColors = [],
		array $animations = [],
		bool $premium = false,
		bool $persona = false,
		bool $personaCapeOnClassic = false,
		bool $isPrimaryUser = true,
		bool $override = true,
		string $trustedSkinFlag = self::TRUSTED_SKIN_FLAG_TRUE,
		string $profileHash = "",
		?int $skinImageWidth = null,
		?int $skinImageHeight = null,
		?int $capeImageWidth = null,
		?int $capeImageHeight = null
	){
		self::checkLength($skinId, "Skin ID", Limits::INT16_MAX);
		self::checkLength($geometryName, "Geometry name", Limits::INT16_MAX);
		self::checkLength($geometryData, "Geometry data", Limits::INT32_MAX);

		if($skinId === ""){
			throw new InvalidSkinException("Skin ID must not be empty");
		}
		[$skinImageWidth, $skinImageHeight] = self::resolveImageDimensions($skinData, $skinImageWidth, $skinImageHeight, "Skin data", self::LEGACY_SKIN_DIMENSIONS);
		if($capeData !== ""){
			[$capeImageWidth, $capeImageHeight] = self::resolveImageDimensions($capeData, $capeImageWidth, $capeImageHeight, "Cape data", self::LEGACY_CAPE_DIMENSIONS);
		}else{
			$capeImageWidth = 0;
			$capeImageHeight = 0;
		}

		if($geometryData !== ""){
			try{
				$decodedGeometry = (new CommentedJsonDecoder())->decode($geometryData);
			}catch(\RuntimeException $e){
				throw new InvalidSkinException("Invalid geometry data: " . $e->getMessage(), 0, $e);
			}

			/*
			 * Hack to cut down on network overhead due to skins, by un-pretty-printing geometry JSON.
			 *
			 * Mojang, some stupid reason, send every single model for every single skin in the selected skin-pack.
			 * Not only that, they are pretty-printed.
			 * TODO: find out what model crap can be safely dropped from the packet (unless it gets fixed first)
			 */
			$geometryData = json_encode($decodedGeometry, JSON_THROW_ON_ERROR);
		}

		foreach($personaPieces as $piece){
			if(!($piece instanceof PersonaSkinPiece)){
				throw new InvalidSkinException("Persona pieces must all be instances of " . PersonaSkinPiece::class);
			}
		}
		foreach($pieceTintColors as $tint){
			if(!($tint instanceof PersonaPieceTintColor)){
				throw new InvalidSkinException("Piece tint colors must all be instances of " . PersonaPieceTintColor::class);
			}
		}
		foreach($animations as $animation){
			if(!($animation instanceof SkinAnimation)){
				throw new InvalidSkinException("Animations must all be instances of " . SkinAnimation::class);
			}
		}

		$this->skinId = $skinId;
		$this->skinData = $skinData;
		$this->skinImageWidth = $skinImageWidth;
		$this->skinImageHeight = $skinImageHeight;
		$this->capeData = $capeData;
		$this->capeImageWidth = $capeImageWidth;
		$this->capeImageHeight = $capeImageHeight;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;

		$this->playFabId = $playFabId;
		$this->resourcePatch = $resourcePatch ?? json_encode(["geometry" => ["default" => $geometryName !== "" ? $geometryName : "geometry.humanoid.custom"]], JSON_THROW_ON_ERROR);
		$this->geometryDataEngineVersion = $geometryDataEngineVersion;
		$this->animationData = $animationData;
		$this->capeId = $capeId;
		//Real clients always send FullID == ID for a given skin, so mirror that when nothing more authoritative
		//is available, rather than inventing an unrelated value (a previous version of this hashed the skin's
		//own content instead, but that doesn't match what real clients actually do and appears to get persona
		//skins rejected in some cases).
		$this->fullSkinId = $fullSkinId ?? $skinId;
		$this->armSize = $armSize;
		$this->skinColor = $skinColor;
		$this->personaPieces = $personaPieces;
		$this->pieceTintColors = $pieceTintColors;
		$this->animations = $animations;
		$this->premium = $premium;
		$this->persona = $persona;
		$this->personaCapeOnClassic = $personaCapeOnClassic;
		$this->isPrimaryUser = $isPrimaryUser;
		$this->override = $override;
		$this->trustedSkinFlag = $trustedSkinFlag;
		$this->profileHash = $profileHash;
	}

	public function getSkinId() : string{
		return $this->skinId;
	}

	public function getSkinData() : string{
		return $this->skinData;
	}

	public function getSkinImageWidth() : int{
		return $this->skinImageWidth;
	}

	public function getSkinImageHeight() : int{
		return $this->skinImageHeight;
	}

	public function getCapeData() : string{
		return $this->capeData;
	}

	public function getCapeImageWidth() : int{
		return $this->capeImageWidth;
	}

	public function getCapeImageHeight() : int{
		return $this->capeImageHeight;
	}

	public function getGeometryName() : string{
		return $this->geometryName;
	}

	public function getGeometryData() : string{
		return $this->geometryData;
	}

	public function getPlayFabId() : string{
		return $this->playFabId;
	}

	public function getResourcePatch() : string{
		return $this->resourcePatch;
	}

	public function getGeometryDataEngineVersion() : string{
		return $this->geometryDataEngineVersion;
	}

	public function getAnimationData() : string{
		return $this->animationData;
	}

	public function getCapeId() : string{
		return $this->capeId;
	}

	public function getFullSkinId() : string{
		return $this->fullSkinId;
	}

	public function getArmSize() : int{
		return $this->armSize;
	}

	public function getSkinColor() : int{
		return $this->skinColor;
	}

	/**
	 * @return PersonaSkinPiece[]
	 */
	public function getPersonaPieces() : array{
		return $this->personaPieces;
	}

	/**
	 * @return PersonaPieceTintColor[]
	 */
	public function getPieceTintColors() : array{
		return $this->pieceTintColors;
	}

	/**
	 * @return SkinAnimation[]
	 */
	public function getAnimations() : array{
		return $this->animations;
	}

	public function isPremium() : bool{
		return $this->premium;
	}

	public function isPersona() : bool{
		return $this->persona;
	}

	public function isPersonaCapeOnClassic() : bool{
		return $this->personaCapeOnClassic;
	}

	public function isPrimaryUser() : bool{
		return $this->isPrimaryUser;
	}

	public function isOverride() : bool{
		return $this->override;
	}

	public function getTrustedSkinFlag() : string{
		return $this->trustedSkinFlag;
	}

	public function getProfileHash() : string{
		return $this->profileHash;
	}

	/**
	 * Dumps the fields that determine how this skin looks/behaves on the wire, for diagnosing skin-appearance bugs.
	 * Deliberately omits the raw pixel/geometry payloads themselves (just their lengths), so this is safe to log.
	 */
	public function describeForDebug() : string{
		$base = sprintf(
			"skinId=%s persona=%s armSize=%d skinColor=%d capeId=%s fullSkinId=%s trustedSkinFlag=%s profileHash=%s " .
			"playFabId=%s geometryName=%s resourcePatch=%s skinImage=%dx%d(%d bytes) capeImage=%dx%d(%d bytes) geometryDataLen=%d " .
			"personaPieces=%d pieceTintColors=%d animations=%d premium=%s personaCapeOnClassic=%s isPrimaryUser=%s override=%s",
			$this->skinId,
			$this->persona ? "true" : "false",
			$this->armSize,
			$this->skinColor,
			$this->capeId,
			$this->fullSkinId,
			$this->trustedSkinFlag,
			$this->profileHash,
			$this->playFabId,
			$this->geometryName,
			$this->resourcePatch,
			$this->skinImageWidth,
			$this->skinImageHeight,
			strlen($this->skinData),
			$this->capeImageWidth,
			$this->capeImageHeight,
			strlen($this->capeData),
			strlen($this->geometryData),
			count($this->personaPieces),
			count($this->pieceTintColors),
			count($this->animations),
			$this->premium ? "true" : "false",
			$this->personaCapeOnClassic ? "true" : "false",
			$this->isPrimaryUser ? "true" : "false",
			$this->override ? "true" : "false",
		);

		$pieces = [];
		foreach($this->personaPieces as $piece){
			$pieces[] = sprintf(
				"{id=%s type=%d packId=%s default=%s product=%s}",
				$piece->getPieceId(),
				$piece->getPieceType(),
				$piece->getPackId(),
				$piece->isDefaultPiece() ? "true" : "false",
				$piece->getProductId()
			);
		}

		$tints = [];
		foreach($this->pieceTintColors as $tint){
			$tints[] = sprintf("{type=%d colors=[%s]}", $tint->getPieceType(), implode(",", $tint->getColors()));
		}

		return $base . " personaPieceDetail=[" . implode(", ", $pieces) . "] pieceTintColorDetail=[" . implode(", ", $tints) . "]";
	}
}
