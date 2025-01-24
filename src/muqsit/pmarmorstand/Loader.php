<?php

declare(strict_types=1);

namespace muqsit\pmarmorstand;

use muqsit\pmarmorstand\behaviour\ArmorStandBehaviourRegistry;
use muqsit\pmarmorstand\entity\ArmorStandEntity;
use muqsit\pmarmorstand\event\PlayerChangeArmorStandPoseEvent;
use muqsit\pmarmorstand\pose\ArmorStandPoseRegistry;
use muqsit\pmarmorstand\vanilla\ExtraVanillaData;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

final class Loader extends PluginBase{

	private ArmorStandBehaviourRegistry $behaviour_registry;

	protected function onLoad() : void{
		$this->behaviour_registry = new ArmorStandBehaviourRegistry();

		EntityFactory::getInstance()->register(ArmorStandEntity::class, function(World $world, CompoundTag $nbt) : ArmorStandEntity{
			return new ArmorStandEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["PMArmorStand"]);

		ExtraVanillaData::registerOnAllThreads($this->getServer()->getAsyncPool());
	}

	protected function onEnable() : void{
		$this->getLogger("ambatukammm ah\nAmbatukammm");
	}

	public function getBehaviourRegistry() : ArmorStandBehaviourRegistry{
		return $this->behaviour_registry;
	}
}
