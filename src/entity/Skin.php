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
use pocketmine\utils\Limits;
use function bin2hex;
use function count;
use function hash;
use function implode;
use function in_array;
use function json_encode;
use function sprintf;
use function strlen;
use const JSON_THROW_ON_ERROR;

final class Skin{
	public const ACCEPTED_SKIN_SIZES = [
		64 * 32 * 4,
		64 * 64 * 4,
		128 * 128 * 4
	];

	public const ARM_SIZE_SLIM = 0;
	public const ARM_SIZE_WIDE = 1;

	public const TRUSTED_SKIN_FLAG_UNSET = "Unset";
	public const TRUSTED_SKIN_FLAG_FALSE = "False";
	public const TRUSTED_SKIN_FLAG_TRUE = "True";

	private string $skinId;
	private string $skinData;
	private string $capeData;
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
		string $geometryDataEngineVersion = "",
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
		string $trustedSkinFlag = self::TRUSTED_SKIN_FLAG_UNSET,
		string $profileHash = ""
	){
		self::checkLength($skinId, "Skin ID", Limits::INT16_MAX);
		self::checkLength($geometryName, "Geometry name", Limits::INT16_MAX);
		self::checkLength($geometryData, "Geometry data", Limits::INT32_MAX);

		if($skinId === ""){
			throw new InvalidSkinException("Skin ID must not be empty");
		}
		$len = strlen($skinData);
		if(!in_array($len, self::ACCEPTED_SKIN_SIZES, true)){
			throw new InvalidSkinException("Invalid skin data size $len bytes (allowed sizes: " . implode(", ", self::ACCEPTED_SKIN_SIZES) . ")");
		}
		if($capeData !== "" && strlen($capeData) !== 8192){
			throw new InvalidSkinException("Invalid cape data size " . strlen($capeData) . " bytes (must be exactly 8192 bytes)");
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
		$this->capeData = $capeData;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;

		$this->playFabId = $playFabId;
		$this->resourcePatch = $resourcePatch ?? json_encode(["geometry" => ["default" => $geometryName !== "" ? $geometryName : "geometry.humanoid.custom"]], JSON_THROW_ON_ERROR);
		$this->geometryDataEngineVersion = $geometryDataEngineVersion;
		$this->animationData = $animationData;
		$this->capeId = $capeId;
		//This has to be unique per distinct skin, or clients may get confused about which skin belongs to whom,
		//but it also has to be *stable* for a given skin, or every re-broadcast of the same skin (e.g. to each
		//viewer added to a player) looks like a brand new skin to the client. Derive it from the skin's own
		//content by default so it satisfies both constraints without needing an authoritative source for it.
		$this->fullSkinId = $fullSkinId ?? bin2hex(hash("sha256", $skinId . "\x00" . $skinData . "\x00" . $capeData . "\x00" . $geometryData . "\x00" . $this->resourcePatch, true));
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

	public function getCapeData() : string{
		return $this->capeData;
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
		return sprintf(
			"skinId=%s persona=%s armSize=%d skinColor=%d capeId=%s fullSkinId=%s trustedSkinFlag=%s profileHash=%s ".
			"playFabId=%s geometryName=%s resourcePatch=%s skinDataLen=%d capeDataLen=%d geometryDataLen=%d ".
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
			strlen($this->skinData),
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
	}
}
