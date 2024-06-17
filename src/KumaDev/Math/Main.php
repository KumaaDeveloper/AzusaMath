<?php

namespace KumaDev\Math;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Config;
use DaPigGuy\libPiggyEconomy\libPiggyEconomy;
use DaPigGuy\libPiggyEconomy\providers\EconomyProvider;
use DaPigGuy\libPiggyEconomy\exceptions\MissingProviderDependencyException;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\scheduler\Task;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {

    protected $mathEnabled = false;
    protected $taskHandler;
    protected $config;
    protected $currentAnswer = null;
    protected static $economyProvider = null;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Check for required dependencies
        foreach ([
            "libPiggyEconomy" => libPiggyEconomy::class
        ] as $virion => $class) {
            if (!class_exists($class)) {
                $this->getServer()->getLogger()->error("[Math] " . $virion . " virion not found. Please download DeVirion Now!.");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }
        }

        // Initialize economy library
        libPiggyEconomy::init();
        
        // Load and save configuration file
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $economyConfig = $this->config->get("Economy");

        // Ensure economy provider is set in the config
        if ($economyConfig === null || !isset($economyConfig['type'])) {
            $this->getServer()->getLogger()->error("[Math] No economy provider specified in config.yml.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // Initialize economy provider
        try {
            self::$economyProvider = libPiggyEconomy::getProvider([
                'provider' => $economyConfig['type']
            ]);
        } catch (MissingProviderDependencyException $e) {
            $this->getServer()->getLogger()->error("[Math] Dependencies for provider not found: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }

    public function onDisable(): void {
        if ($this->taskHandler !== null) {
            $this->taskHandler->cancel();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "math") {
            if (isset($args[0])) {
                if ($args[0] === "on") {
                    if (!$this->mathEnabled) {
                        $this->mathEnabled = true;
                        $this->scheduleMathTask();
                        $sender->sendMessage("§aAzusaMath questions have been enabled.");
                    }
                } elseif ($args[0] === "off") {
                    $this->mathEnabled = false;
                    if ($this->taskHandler !== null) {
                        $this->taskHandler->cancel();
                        $sender->sendMessage("§cAzusaMath questions have been disabled.");
                    }
                }
            }
            return true;
        }
        return false;
    }

    public function isMathEnabled(): bool {
        return $this->mathEnabled;
    }

    public static function getEconomyProvider(): ?EconomyProvider {
        return self::$economyProvider;
    }

    public function getConfigData(): Config {
        return $this->config;
    }

    public function setCurrentAnswer(?int $answer): void {
        $this->currentAnswer = $answer;
    }

    public function getCurrentAnswer(): ?int {
        return $this->currentAnswer;
    }

    public function getTaskHandler(): ?TaskHandler {
        return $this->taskHandler;
    }

    public function setTaskHandler(?TaskHandler $handler): void {
        $this->taskHandler = $handler;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        if ($this->mathEnabled && $this->taskHandler === null) {
            $this->scheduleMathTask();
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        if (count($this->getServer()->getOnlinePlayers()) === 1 && $this->taskHandler !== null) {
            $this->taskHandler->cancel();
            $this->taskHandler = null;
        }
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        if ($this->currentAnswer !== null) {
            $message = $event->getMessage();
            if (is_numeric($message) && (int)$message === $this->currentAnswer) {
                $player = $event->getPlayer();
                $reward = rand($this->config->get("prize_min"), $this->config->get("prize_max"));
                $this->addMoney($player, $reward);
                $this->getServer()->broadcastMessage(str_replace("{player}", $player->getName(), str_replace("{money}", $reward, $this->config->get("maths_completion_message"))));
                $this->currentAnswer = null;
                $event->cancel();

                if ($this->taskHandler !== null) {
                    $this->taskHandler->cancel();
                }

                $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
                    private $plugin;

                    public function __construct(Main $plugin) {
                        $this->plugin = $plugin;
                    }

                    public function onRun(): void {
                        if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                            $this->plugin->scheduleMathTask();
                        }
                    }
                }, 20 * $this->getConfigData()->get("maths_delay_solved"));
            }
        }
    }

    private function addMoney(Player $player, int $amount): void {
        $provider = self::getEconomyProvider();
        if ($provider !== null) {
            $provider->giveMoney($player, $amount); // Correct method to add money
        }
    }

    public function scheduleMathTask(): void {
        if ($this->mathEnabled && count($this->getServer()->getOnlinePlayers()) > 0) {
            $task = new MathTask($this);
            $this->setTaskHandler($this->getScheduler()->scheduleDelayedRepeatingTask($task, 0, 20 * $this->getConfigData()->get("problem_interval")));
        }
    }
}
