<?php

namespace Laith98Dev\NoPVP;

/*  
 *  A plugin for PocketMine-MP.
 *  
 *	 _           _ _   _    ___   ___  _____             
 *	| |         (_) | | |  / _ \ / _ \|  __ \            
 *	| |     __ _ _| |_| |_| (_) | (_) | |  | | _____   __
 *	| |    / _` | | __| '_ \__, |> _ <| |  | |/ _ \ \ / /
 *	| |___| (_| | | |_| | | |/ /| (_) | |__| |  __/\ V / 
 *	|______\__,_|_|\__|_| |_/_/  \___/|_____/ \___| \_/  
 *	
 *	Copyright (C) 2021 Laith98Dev
 *  
 *	Youtube: Laith Youtuber
 *	Discord: Laith98Dev#0695
 *	Gihhub: Laith98Dev
 *	Email: help@laithdev.tk
 *	Donate: https://paypal.me/Laith113
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 	
 */

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\level\Level;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\Player;
use pocketmine\utils\{Config, TextFormat as TF};

use pocketmine\command\{Command, CommandSender};

use pocketmine\scheduler\Task;

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;

class Main extends PluginBase implements Listener 
{
	public $manageSession = [];
	
	public $unsetTasks = [];
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		
		(new Config($this->getDataFolder() . "data.yml", Config::YAML, [
			"attack-msg" => "&cPVP now allowed here!",
			"worlds" => []
		]));
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onCommand(CommandSender $sender, Command $cmd, string $cmdLabel, array $args): bool{
		switch ($cmd->getName()){
			case "nopvp":
			case "np":
				if(!($sender instanceof Player))
					return false;
				if(!$cmd->testPermission($sender))
					return false;
				
				$this->OpenMainForm($sender);
			break;
		}
		return true;
	}
	
	public function OpenMainForm(Player $player){
		$form = new SimpleForm(function (Player $player, int $data = null){
			if($data === null)
				return false;
			
			if(!isset($this->manageSession[$player->getName()])){
				$player->sendMessage(TF::RED . "Session timed out try again.");
				return false;
			}
			
			$worlds = $this->manageSession[$player->getName()];
			
			for ($i = 0; $i <= count($worlds); $i++){
				if(isset($worlds[$i])){
					if($data === $i){
						$this->OpenManageForm($player, $worlds[$i]);
					}
				}
			}
			
		});
		
		$form->setTitle("NoPVP");
		
		$worlds = [];
		foreach ($this->getServer()->getLevels() as $level){
			if(!($level instanceof Level))
				continue;
			$worlds[] = $level->getFolderName();
			$form->addButton($level->getFolderName());
		}
		
		if(count($worlds) === 0){
			$form->setContent("Sorry you don't have any loaded worlds!");
			$form->addButton("Exit");
		} else {
			$form->setContent("Select world to manage:");
			$this->manageSession[$player->getName()] = $worlds;
			$task = new unSetArrayTask($this, $player->getName());
			$this->getScheduler()->scheduleDelayedTask($task, 10 * 20);
			$this->unsetTasks[$player->getName()] = $task;
		}
		
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function OpenManageForm(Player $player, string $world){
		if(isset($this->unsetTasks[$player->getName()])){
			$task = $this->unsetTasks[$player->getName()];
			if($task->getHandler() !== null)
				$task->getHandler()->cancel();
		} else {
			$task = new unSetArrayTask($this, $player->getName());
			$this->getScheduler()->scheduleDelayedTask($task, 10 * 20);
			$this->unsetTasks[$player->getName()] = $task;
		}
		
		$this->manageSession[$player->getName()] = $world;
		
		$form = new SimpleForm(function (Player $player, int $data = null){
			if($data === null)
				return false;
			
			if(!isset($this->manageSession[$player->getName()])){
				$player->sendMessage(TF::RED . "Session timed out try again.");
				return false;
			}
			
			$world = $this->manageSession[$player->getName()];
			
			$value = $data === 0 ? true : false;
			
			$value_str = $value == true ? "on" : "off";
			
			if($this->setWorld($world, $value)){
				$player->sendMessage(TF::YELLOW . "PVP has been turned " . $value_str . " in world '" . $world . "'");
			} else {
				$player->sendMessage(TF::RED . "PVP already turned " . $value_str . " in this world!");
			}
			
			if(isset($this->manageSession[$player->getName()]))
				unset($this->manageSession[$player->getName()]);
		});
		
		$form->setTitle("Manage " . $world);
		
		$form->addButton("ON");
		$form->addButton("OFF");
		
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function setWorld(string $world, bool $value = true): bool{
		$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
		$worlds = $data->get("worlds", []);
		
		if(!isset($worlds[$world]))
			$worlds[$world] = ["pvp" => true];
		
		if($worlds[$world]["pvp"] === $value)
			return false;
		
		$worlds[$world]["pvp"] = $value;
		
		$data->set("worlds", $worlds);
		$data->save();
		return true;
	}
	
	public function onDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			if($event instanceof EntityDamageByEntityEvent && ($damager = $event->getDamager()) instanceof Player){
				$data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
				if($data->get("worlds")){
					if(($level = $entity->getLevel()) instanceof Level){
						$worlds = $data->get("worlds");
						if(isset($worlds[$level->getFolderName()]) && $worlds[$level->getFolderName()]["pvp"] === false){
							if($data->get("attack-msg") && $data->get("attack-msg") !== "")
								$damager->sendMessage(str_replace("&", TF::ESCAPE, $data->get("attack-msg")));
							$event->setCancelled();
						}
					}
				}
			}
		}
	}
}
