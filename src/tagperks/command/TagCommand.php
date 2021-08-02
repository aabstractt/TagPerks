<?php

declare(strict_types=1);

namespace tagperks\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use tagperks\TagPerks;

class TagCommand extends Command {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . 'Run this command in-game');

            return;
        }

        if (count($args) <= 0) {
            $sender->sendMessage(TextFormat::RED . 'Use: /' . $commandLabel . ' <tag>');

            return;
        }

        $config = TagPerks::getInstance()->getConfig();

        $isRemove = $args[0] === 'remove';

        if (!$isRemove && !isset($config->get('tags', [])[$args[0]])) {
            $sender->sendMessage(TextFormat::RED . 'Tag not found');

            return;
        }

        if (!$isRemove && !$sender->hasPermission('tag.' . $args[0])) {
            $sender->sendMessage(TextFormat::RED . 'You don\'t have permissions to use this tag');

            return;
        }

        TagPerks::getInstance()->setPlayerTag($sender, $isRemove ? null : $args[0]);

        $sender->sendMessage($isRemove ? TextFormat::RED . 'Your tag was removed' : TextFormat::GREEN . 'Your tag is now ' . TextFormat::colorize($config->get('tags')[$args[0]]['format'] ?? ''));
    }
}