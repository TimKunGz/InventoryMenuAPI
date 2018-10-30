<?php
namespace korado531m7\InventoryMenuAPI;

use korado531m7\InventoryMenuAPI\event\InventoryMenuCloseEvent;
use korado531m7\InventoryMenuAPI\event\InventoryMenuGenerateEvent;
use korado531m7\InventoryMenuAPI\task\DelayAddWindowTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\item\Item;
use pocketmine\inventory\EnchantInventory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Chest as TileChest;
use pocketmine\tile\Furnace as TileFurnace;
use pocketmine\tile\EnchantTable as TileEnchantTable;
use pocketmine\tile\EnderChest as TileEnderChest;
use pocketmine\tile\Tile;

class InventoryMenuAPI extends PluginBase{
    private static $inventoryMenuVar = [];
    private static $inventory = [];
    private static $pluginbase;
    
    const INVENTORY_TYPE_CHEST = 1;
    const INVENTORY_TYPE_DOUBLE_CHEST = 2;
    const INVENTORY_TYPE_FURNACE = 3;
    const INVENTORY_TYPE_ENCHANTING_TABLE = 4;
    const INVENTORY_TYPE_ENDER_CHEST = 5;
    
    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        self::setPluginBase($this);
    }
    
    /**
     * Send an inventory menu to player
     *
     * @param Player  $player
     * @param Item[]  $items
     * @param string  $inventoryName
     * @param int     $inventoryType
     * @param bool    $isCloseType      Default value is true and if true, the inventory menu will be automatically closed when call InventoryTransactionPacket but if not, won't be closed. so you must call 'closeInventory' funtion to close manually
     */
    public static function sendInventoryMenu(Player $player, array $items, $inventoryName = "Inventory Menu", $inventoryType = self::INVENTORY_TYPE_CHEST, bool $isCloseType = true){
        if(self::isOpeningInventoryMenu($player)) return true;
        $x = ((int)$player->x + mt_rand(-1,1));
        $y = ((int)$player->y + 4);
        $z = ((int)$player->z + mt_rand(-1,1));
        if($player->getLevel()->getTileAt($x,$y,$z) !== null) $y = ((int)$player->y + 3);
        if(count($items) === 0) $maxKey = 0;
        else $maxKey = max(array_keys($items));
        switch($inventoryType){
            case self::INVENTORY_TYPE_FURNACE:
                if($maxKey > 2) throw new \RuntimeException('Invalid key for furnace expected 0, 1 got '.$maxKey);
                self::sendFakeBlock($player,$x,$y,$z,BlockIds::FURNACE);
                $nbt = TileFurnace::createNBT(new Vector3($x,$y,$z), 0, Item::get(0,0), $player);
                $nbt->setString('CustomName',$inventoryName);
                $tile = Tile::createTile(Tile::FURNACE, $player->getLevel(), $nbt);
                $tag = new CompoundTag();
                $tag->setString('id', $tile->getSaveId());
                $tag->setString('CustomName', $inventoryName);
                $inv = $tile->getInventory();
            break;
            
            case self::INVENTORY_TYPE_ENCHANTING_TABLE:    
                if($maxKey > 2) throw new \RuntimeException('Invalid key for furnace expected 0, 1 got '.$maxKey);
                self::sendFakeBlock($player,$x,$y,$z,BlockIds::ENCHANTING_TABLE);
                $nbt = TileEnchantTable::createNBT(new Vector3($x,$y,$z), 0, Item::get(0,0), $player);
                $nbt->setString('CustomName',$inventoryName);
                $tile = Tile::createTile(Tile::ENCHANT_TABLE, $player->getLevel(), $nbt);
                $tag = new CompoundTag();
                $tag->setString('id', $tile->getSaveId());
                $tag->setString('CustomName', $inventoryName);
                $inv = new EnchantInventory(new Position($x,$y,$z,$player->getLevel()));
            break;
            
            case self::INVENTORY_TYPE_ENDER_CHEST:    
                if($maxKey > 27) throw new \RuntimeException('Invalid key for furnace expected between 0 and 26 got '.$maxKey);
                self::sendFakeBlock($player,$x,$y,$z,BlockIds::ENDER_CHEST);
                $nbt = TileEnchantTable::createNBT(new Vector3($x,$y,$z), 0, Item::get(0,0), $player);
                $nbt->setString('CustomName',$inventoryName);
                $tile = Tile::createTile(Tile::ENDER_CHEST, $player->getLevel(), $nbt);
                $player->getEnderChestInventory()->setHolderPosition($tile);
                $tag = new CompoundTag();
                $tag->setString('id', $tile->getSaveId());
                $tag->setString('CustomName', $inventoryName);
                $inv = $player->getEnderChestInventory();
            break;
            
            case self::INVENTORY_TYPE_DOUBLE_CHEST:
                if($maxKey > 54) throw new \RuntimeException('Invalid key for furnace expected between 0 and 53 got '.$maxKey);
                self::sendFakeBlock($player,$x,$y,$z + 1,BlockIds::CHEST);
                $nbt2 = TileChest::createNBT(new Vector3($x,$y,$z + 1), 0, Item::get(0,0), $player);
                $tile2 = Tile::createTile(Tile::CHEST, $player->getLevel(), $nbt2);
                $writer = new NetworkLittleEndianNBTStream();
                $tag = new CompoundTag();
                $tag->setString('id', $tile2->getSaveId());
                $tag->setInt('pairx', $x);
                $tag->setInt('pairz', $z);
                $tag->setString('CustomName', $inventoryName);
                
                $pk = new BlockEntityDataPacket;
                $pk->x = $x;
                $pk->y = $y;
                $pk->z = $z + 1;
                $pk->namedtag = $writer->write($tag);
                $player->dataPacket($pk);
            case self::INVENTORY_TYPE_CHEST:
                if($maxKey > 27) throw new \RuntimeException('Invalid key for furnace expected between 0 and 26 got '.$maxKey);
                self::sendFakeBlock($player,$x,$y,$z,BlockIds::CHEST);
                $nbt = TileChest::createNBT(new Vector3($x,$y,$z), 0, Item::get(0,0), $player);
                $nbt->setString('CustomName',$inventoryName);
                $tile = Tile::createTile(Tile::CHEST, $player->getLevel(), $nbt);
                $tag = new CompoundTag();
                $tag->setString('id', $tile->getSaveId());
                $tag->setString('CustomName', $inventoryName);
                if($inventoryType == self::INVENTORY_TYPE_DOUBLE_CHEST) $tile->pairWith($tile2);
                $inv = $tile->getInventory();
            break;
        }
        
        $writer = new NetworkLittleEndianNBTStream();
        $pk = new BlockEntityDataPacket;
        $pk->x = $x;
        $pk->y = $y;
        $pk->z = $z;
        $pk->namedtag = $writer->write($tag);
        $player->dataPacket($pk);
        
        foreach($items as $itemkey => $item){
            $inv->setItem($itemkey,$item);
        }
        Server::getInstance()->getPluginManager()->callEvent(new InventoryMenuGenerateEvent($player,$items,$tile,$inventoryType));
        self::saveInventory($player);
        switch($inventoryType){
            case self::INVENTORY_TYPE_ENDER_CHEST:
            case self::INVENTORY_TYPE_ENCHANTING_TABLE:
                self::$inventoryMenuVar[$player->getName()] = array($inventoryType,$tile->getSaveId(),$x,$y,$z,$player->getLevel()->getName(),$isCloseType,$inv);
                $player->addWindow($inv);
            break;
            
            case self::INVENTORY_TYPE_FURNACE:
            case self::INVENTORY_TYPE_CHEST:
                self::$inventoryMenuVar[$player->getName()] = array($inventoryType,$tile->getSaveId(),$x,$y,$z,$player->getLevel()->getName(),$isCloseType);
                $player->addWindow($inv);
            break;
            
            case self::INVENTORY_TYPE_DOUBLE_CHEST:
                self::$inventoryMenuVar[$player->getName()] = array(self::INVENTORY_TYPE_DOUBLE_CHEST,$tile->getSaveId(),$x,$y,$z,$player->getLevel()->getName(),$isCloseType);
                self::getPluginBase()->getScheduler()->scheduleDelayedTask(new DelayAddWindowTask($player,$inv), 10);
            break;
        }
    }
    
    /**
     * Change old item for new items in the inventory menu but it must be isCloseType is false
     * Also you can change $isCloseType in this function (default: false)
     *
     * @param Player  $player
     * @param Item[]  $items
     * @param bool    $isCloseType
     */
    public static function fillInventoryMenu(Player $player,array $items,bool $isCloseType = false){
        if(!self::isOpeningInventoryMenu($player)) return false;
        $data = self::getData($player);
        $tile = Server::getInstance()->getLevelByName($data[5])->getTileAt($data[2],$data[3],$data[4]);
        if($tile === \null) return false;
        $inv = $tile->getInventory();
        $inv->clearAll();
        foreach($items as $itemkey => $item){
            $inv->setItem($itemkey,$item);
        }
        $data[6] = $isCloseType;
        self::restoreInventory($player);
        self::$inventoryMenuVar[$player->getName()] = $data;
    }
    
    /**
     * Clear all items from an inventory menu (for development)
     *
     * @param Player  $player
     * @param bool    $isCloseType
     */
    public static function clearInventoryMenu(Player $player,bool $isCloseType = false){
        if(!self::isOpeningInventoryMenu($player)) return false;
        $data = self::getData($player);
        $tile = Server::getInstance()->getLevelByName($data[5])->getTileAt($data[2],$data[3],$data[4]);
        if($tile === \null) return false;
        $inv = $tile->getInventory();
        $inv->clearAll();
        $data[6] = $isCloseType;
        self::$inventoryMenuVar[$player->getName()] = $data;
    }
    
    /**
     * Close an inventory menu if player is opening
     *
     * @param Player $player
     */
    public static function closeInventoryMenu(Player $player){
        if(!self::isOpeningInventoryMenu($player)) return true;
        $data = self::getData($player);
        if(Server::getInstance()->isLevelLoaded($data[5])){
            $level = Server::getInstance()->getLevelByName($data[5]);
            Server::getInstance()->getPluginManager()->callEvent(new InventoryMenuCloseEvent($player, $level->getTile(new Vector3($data[2],$data[3],$data[4]))));
            switch($data[0]){
                case self::INVENTORY_TYPE_ENCHANTING_TABLE:
                case self::INVENTORY_TYPE_ENDER_CHEST:
                    $player->removeWindow($data[7]);
                    self::sendFakeBlock($player,$data[2],$data[3],$data[4],BlockIds::AIR);
                break;
                
                case self::INVENTORY_TYPE_DOUBLE_CHEST:
                    self::sendFakeBlock($player,$data[2],$data[3],$data[4] + 1,BlockIds::AIR);
                    $level->removeTile($level->getTile(new Vector3($data[2],$data[3],$data[4] + 1)));
                case self::INVENTORY_TYPE_CHEST:
                case self::INVENTORY_TYPE_FURNACE:
                    self::sendFakeBlock($player,$data[2],$data[3],$data[4],BlockIds::AIR);
                    $level->removeTile($level->getTile(new Vector3($data[2],$data[3],$data[4])));
                break;
            }
        }
        self::restoreInventory($player, true);
        unset(self::$inventoryMenuVar[$player->getName()]);
    }
    
    /**
     * Check whether player is opening inventory menu
     *
     * @param  Player $player
     * @return bool
     */
    public static function isOpeningInventoryMenu(Player $player) : bool{
        return array_key_exists($player->getName(),self::$inventoryMenuVar);
    }
    
    /**
     * @param Player  $player
     * @return array
     */
    public static function getData(Player $player) : array{
        return self::$inventoryMenuVar[$player->getName()] ?? [];
    }
    
    public static function saveInventory(Player $player){
        self::$inventory[$player->getName()] = $player->getInventory()->getContents();
    }
    
    public static function restoreInventory(Player $player, bool $reset = false){
        $inventory = self::$inventory[$player->getName()] ?? null;
        if($inventory === null) return false;
        $player->getInventory()->setContents($inventory);
        if($reset) unset($inventory[$player->getName()]);
    }
    
    private static function getPluginBase() : PluginBase{
        return self::$pluginbase;
    }
    
    private static function setPluginBase(PluginBase $plugin){
        self::$pluginbase = $plugin;
    }
    
    private static function sendFakeBlock(Player $player,int $x,int $y,int $z,int $blockid){
        $pk = new UpdateBlockPacket();
        $pk->x = $x;
        $pk->y = $y;
        $pk->z = $z;
        $pk->flags = UpdateBlockPacket::FLAG_ALL;
        $pk->blockRuntimeId = BlockFactory::toStaticRuntimeId($blockid);
        $player->dataPacket($pk);
    }
}