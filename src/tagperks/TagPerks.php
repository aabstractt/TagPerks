<?php

declare(strict_types=1);

namespace tagperks;

use _64FF00\PureChat\PureChat;
use _64FF00\PurePerms\event\PPGroupChangedEvent;
use _64FF00\PurePerms\PurePerms;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\PermissionManager;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use tagperks\command\TagCommand;

class TagPerks extends PluginBase implements Listener {

    /** @var TagPerks */
    private static $instance;
    /** @var PureChat */
    private $purechat;
    /** @var PurePerms */
    private $pureperms;
    /** @var Config */
    private $playersConfig;

    /**
     * @return TagPerks
     */
    public static function getInstance(): TagPerks {
        if (self::$instance === null) {
            throw new PluginException("Plugin never initialized");
        }

        return self::$instance;
    }

    public function onEnable(): void {
        self::$instance = $this;

        $this->purechat = $this->getServer()->getPluginManager()->getPlugin('PureChat');
        $this->pureperms = $this->getServer()->getPluginManager()->getPlugin('PurePerms');

        $this->saveDefaultConfig();

        $this->playersConfig = new Config($this->getDataFolder() . 'players.yml');

        foreach (array_keys($this->getConfig()->get('tags')) as $tag) {
            PermissionManager::getInstance()->addPermission(new Permission('tag.' . $tag, "", Permission::DEFAULT_TRUE));
        }

        $this->getServer()->getCommandMap()->register(TagCommand::class, new TagCommand('tag', 'Tag command'));
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param Player      $player
     * @param string|null $tagName
     */
    public function setPlayerTag(Player $player, ?string $tagName): void {
        $config = $this->playersConfig;

        $uuid = $player->getUniqueId();

        $this->pureperms->updatePermissions($player);

        if (is_null($tagName)) {
            $config->remove($uuid->toString());
        } else {
            $config->set($uuid->toString(), $tagName);

            $this->givePermissions($player);
        }

        $config->save();
    }

    /**
     * @param UUID $uuid
     *
     * @return string|null
     */
    public function getPlayerTag(UUID $uuid): ?string {
        return $this->playersConfig->get($uuid->toString(), null);
    }

    /**
     * @param PlayerChatEvent $ev
     *
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onPlayerChatEvent(PlayerChatEvent $ev): void {
        $player = $ev->getPlayer();

        if ($this->purechat === null) {
            return;
        }

        $tag = $this->getPlayerTag($player->getUniqueId());

        if ($tag !== null && !$player->hasPermission('tag.' . $tag)) {
            return;
        }

        $message = $ev->getMessage();

        $levelName = $this->purechat->getConfig()->get("enable-multiworld-chat") ? $player->getLevel()->getName() : null;

        $chatFormat = $this->purechat->getChatFormat($player, $message, $levelName);

        $tagData = $this->getConfig()->get('tags', [])[$tag] ?? [];

        $ev->setFormat(str_replace('{$TAG_PREFIX}', $tag !== null ? TextFormat::colorize($tagData['format']) : '', $chatFormat));
    }

    /**
     * @param PPGroupChangedEvent $ev
     *
     * @priority MONITOR
     */
    public function onGroupChanged(PPGroupChangedEvent $ev) {
        /** @var Player $player */
        $player = $ev->getPlayer();

        $this->givePermissions($player);
    }

    /**
     * @param Player $player
     */
    public function givePermissions(Player $player): void {
        if ($this->pureperms === null) {
            return;
        }

        $tag = $this->getPlayerTag($player->getUniqueId());

        if ($tag === null) {
            return;
        }

        if (!$player->hasPermission('tag.' . $tag)) {
            return;
        }

        $tagData = $this->getConfig()->get('tags', [])[$tag] ?? [];

        if (empty($tagData) || empty($tagData['permissions'])) {
            return;
        }

        $permissions = [];

        /** @var string $permission */
        foreach ($tagData['permissions'] as $permission) {
            if ($permission === '*') {
                foreach (PermissionManager::getInstance()->getPermissions() as $tmp) {
                    $permissions[$tmp->getName()] = true;
                }
            } else {
                $isNegative = substr($permission, 0, 1) === "-";

                if ($isNegative) {
                    $permission = substr($permission, 1);
                }

                $permissions[$permission] = !$isNegative;
            }
        }

        $permissions[PurePerms::CORE_PERM] = true;

        /** @var PermissionAttachment $attachment */
        $attachment = $this->pureperms->getAttachment($player);

        $attachment->setPermissions($permissions);
    }
}