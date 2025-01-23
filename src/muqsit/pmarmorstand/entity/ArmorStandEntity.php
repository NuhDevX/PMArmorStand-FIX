<?php

declare(strict_types=1);

namespace muqsit\pmarmorstand\entity;

use muqsit\pmarmorstand\entity\ticker\ArmorStandEntityTicker;
use muqsit\pmarmorstand\entity\ticker\WobbleArmorStandEntityTicker;
use muqsit\pmarmorstand\event\ArmorStandMoveEvent;
use muqsit\pmarmorstand\pose\ArmorStandPose;
use muqsit\pmarmorstand\pose\ArmorStandPoseRegistry;
use muqsit\pmarmorstand\vanilla\ExtraVanillaItems;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

class ArmorStandEntity extends Living{

	public const TAG_MAINHAND = "Mainhand";
	public const TAG_OFFHAND = "Offhand";
	public const TAG_POSE_INDEX = "PoseIndex";
	public const TAG_POSE = "Pose";
	public const TAG_ARMOR = "Armor";

	/** @var ArmorStandEntityEquipment */
	protected $equipment;

	public const WIDTH = 0.5;
	public const HEIGHT = 1.975;

	protected const GRAVITY = 0.04;

	protected $vibrateTimer = 0;
        

	public static function getNetworkTypeId() : string{
		return EntityIds::ARMOR_STAND;
	}


	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(self::HEIGHT, self::WIDTH);
	}

	public function getName() : string{
		return "Armor Stand";
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->pose->getNetworkId());
	}

	public function getDrops() : array{
		$drops = $this->getArmorInventory()->getContents();
		if(!$this->item_in_hand->isNull()){
			$drops[] = $this->item_in_hand;
		}
		$drops[] = ExtraVanillaItems::ARMOR_STAND();
		return $drops;
	}

	public function getItemInHand() : Item{
		return $this->item_in_hand;
	}

	public function setItemInHand(Item $item_in_hand) : void{
		$this->item_in_hand = $item_in_hand;
		$packet = MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->getItemInHand())), 0, 0, ContainerIds::INVENTORY);
		foreach($this->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket($packet);
		}
	}

	public function getPose() : ArmorStandPose{
		return $this->pose;
	}

	public function getEquipment() : ArmorStandEntityEquipment{
		return $this->equipment;
	}

	public function setPose(ArmorStandPose $pose) : void{
		$this->pose = $pose;
		$this->networkPropertiesDirty = true;
		$this->scheduleUpdate();
	}
	
	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->getItemInHand())), 0, 0, ContainerIds::INVENTORY));
	}

	protected function addAttributes() : void{
		parent::addAttributes();
		$this->setMaxHealth(6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(6);
		$this->setNoClientPredictions(true);

		parent::initEntity($nbt);

		$this->equipment = new ArmorStandEntityEquipment($this);

		if($nbt->getTag(self::TAG_ARMOR, ListTag::class)){
			$armors = $nbt->getListTag(self::TAG_ARMOR);

			/** @var CompoundTag $armor */
			foreach($armors as $armor){
				$slot = $armor->getByte("Slot", 0);

				$this->armorInventory->setItem($slot, Item::nbtDeserialize($armor));
			}
		}

		if($nbt->getTag(self::TAG_MAINHAND, CompoundTag::class)){
			$this->equipment->setItemInHand(Item::nbtDeserialize($nbt->getCompoundTag(self::TAG_MAINHAND)));
		}
		if($nbt->getTag(self::TAG_OFFHAND, CompoundTag::class)){
			$this->equipment->setOffhandItem(Item::nbtDeserialize(nbt->getCompoundTag(self::TAG_OFFHAND)));
		}

		$this->setPose(min($nbt->getInt(self::TAG_POSE_INDEX, 0), 12));
		
		$this->setPose(($tag_pose = $nbt->getTag(self::TAG_POSE)) instanceof StringTag ?
			ArmorStandPoseRegistry::instance()->get($tag_pose->getValue()) :
			ArmorStandPoseRegistry::instance()->default());
	}
	

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$armor_pieces = [];
		foreach($this->getArmorInventory()->getContents() as $slot => $item){
			$armor_pieces[] = $item->nbtSerialize($slot);
		}
		$nbt->setTag(self::TAG_ARMOR_INVENTORY, new ListTag($armor_pieces, NBT::TAG_Compound));

		if(!$this->item_in_hand->isNull()){
			$nbt->setTag(self::TAG_HELD_ITEM, $this->item_in_hand->nbtSerialize());
		}

		$nbt->setString(self::TAG_POSE, ArmorStandPoseRegistry::instance()->getIdentifier($this->pose));
		return $nbt;
	}

	public function applyDamageModifiers(EntityDamageEvent $source) : void{
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source instanceof EntityDamageByChildEntityEvent && $source->getChild() instanceof Arrow){
			$this->kill();
		}
	}

	public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4) : void{
	}

	public function actuallyKnockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4) : void{
		parent::knockBack($x, $z, $force, $verticalLimit);
	}

	protected function doHitAnimation() : void{
		if(
			$this->lastDamageCause instanceof EntityDamageByEntityEvent &&
			$this->lastDamageCause->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK &&
			$this->lastDamageCause->getDamager() instanceof Player
		){
			$this->addArmorStandEntityTicker("ticker:wobble", new WobbleArmorStandEntityTicker($this));
		}
	}

	protected function startDeathAnimation() : void{
	}

	public function addArmorStandEntityTicker(string $identifier, ArmorStandEntityTicker $ticker) : void{
		$this->armor_stand_entity_tickers[$identifier] = $ticker;
		$this->scheduleUpdate();
	}

	public function removeArmorStandEntityTicker(string $identifier) : void{
		unset($this->armor_stand_entity_tickers[$identifier]);
	}

	public function canBeMovedByCurrents() : bool{
		return $this->can_be_moved_by_currents;
	}

	public function setCanBeMovedByCurrents(bool $can_be_moved_by_currents) : void{
		$this->can_be_moved_by_currents = $can_be_moved_by_currents;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$result = parent::entityBaseTick($tickDiff);

		foreach($this->armor_stand_entity_tickers as $identifier => $ticker){
			if(!$ticker->tick($this)){
				$this->removeArmorStandEntityTicker($identifier);
			}
		}

		return $result || count($this->armor_stand_entity_tickers) > 0;
	}

	protected function move(float $dx, float $dy, float $dz) : void{
		$from = $this->location->asLocation();
		parent::move($dx, $dy, $dz);
		$to = $this->location->asLocation();
		(new ArmorStandMoveEvent($this, $from, $to))->call();
	}
}
