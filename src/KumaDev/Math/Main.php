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
    protected $lastNoAnswerTime = 0;
    protected $currentQuestionStartTime = 0;

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
                        $this->scheduleFirstMathQuestion();
                        $sender->sendMessage("§aAzusaMath questions have been enabled.");
                    } else {
                        $sender->sendMessage("§cAzusaMath is already enabled.");
                    }
                } elseif ($args[0] === "off") {
                    if ($this->mathEnabled) {
                        $this->mathEnabled = false;
                        if ($this->taskHandler !== null) {
                            $this->taskHandler->cancel();
                            $this->taskHandler = null;
                        }
                        $sender->sendMessage("§cAzusaMath questions have been disabled.");
                    } else {
                        $sender->sendMessage("§cAzusaMath is already disabled.");
                    }
                } else {
                    $sender->sendMessage("§eUsage: /math [on/off]");
                }
            } else {
                $sender->sendMessage("§eUsage: /math [on/off]");
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

    public function setCurrentAnswer(?float $answer): void {
        $this->currentAnswer = $answer;
    }

    public function getCurrentAnswer(): ?float {
        return $this->currentAnswer;
    }

    public function getTaskHandler(): ?TaskHandler {
        return $this->taskHandler;
    }

    public function setTaskHandler(?TaskHandler $handler): void {
        $this->taskHandler = $handler;
    }

    public function getLastNoAnswerTime(): int {
        return $this->lastNoAnswerTime;
    }

    public function setLastNoAnswerTime(int $time): void {
        $this->lastNoAnswerTime = $time;
    }

    public function getCurrentQuestionStartTime(): int {
        return $this->currentQuestionStartTime;
    }

    public function setCurrentQuestionStartTime(int $time): void {
        $this->currentQuestionStartTime = $time;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        if ($this->mathEnabled && $this->taskHandler === null) {
            $this->scheduleInitialNoAnswerMessageTask();
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
            if (is_numeric($message) && round((float)$message, 1) === round($this->currentAnswer, 1)) {
                $player = $event->getPlayer();
                $reward = rand($this->config->get("prize_min"), $this->config->get("prize_max"));
                $this->addMoney($player, $reward);
                $this->getServer()->broadcastMessage(str_replace("{player}", $player->getName(), str_replace("{money}", $reward, $this->config->get("maths_completion_message"))));
                $this->currentAnswer = null;
                $event->cancel();

                $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
                    private $plugin;

                    public function __construct(Main $plugin) {
                        $this->plugin = $plugin;
                    }

                    public function onRun(): void {
                        if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                            $this->plugin->broadcastMathQuestion();
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

    public function broadcastMathQuestion(): void {
        if ($this->mathEnabled && count($this->getServer()->getOnlinePlayers()) > 0) {
            $problem = $this->generateProblem();
            $this->getServer()->broadcastMessage("Define §f{$problem['number1']} §e{$problem['operator']} §f{$problem['number2']} §e= §f?");
            $this->setCurrentAnswer($problem['answer']);
            $this->setCurrentQuestionStartTime(time());
        }
    }

    public function generateProblem(): array {
        return MathTask::generateProblem($this);
    }

    public function scheduleFirstMathQuestion(): void {
        $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
            private $plugin;

            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }

            public function onRun(): void {
                if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                    $this->plugin->broadcastMathQuestion();
                    $this->plugin->scheduleInitialNoAnswerMessageTask();
                }
            }
        }, 20); // 1 second delay
    }

    public function scheduleInitialNoAnswerMessageTask(): void {
        $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
            private $plugin;

            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }

            public function onRun(): void {
                if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                    $this->plugin->scheduleNoAnswerMessageTask();
                }
            }
        }, 20 * $this->getConfigData()->get("math_interval"));
    }

    public function scheduleNoAnswerMessageTask(): void {
        $this->setTaskHandler($this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private $plugin;

            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }

            public function onRun(): void {
                if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                    $currentTime = time();
                    if ($currentTime - $this->plugin->getLastNoAnswerTime() >= $this->plugin->getConfigData()->get("math_interval")) {
                        $this->plugin->getServer()->broadcastMessage($this->plugin->getConfigData()->get("no_answer_message"));
                        $this->plugin->setLastNoAnswerTime($currentTime);

                        $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin) extends Task {
                            private $plugin;

                            public function __construct(Main $plugin) {
                                $this->plugin = $plugin;
                            }

                            public function onRun(): void {
                                if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                                    $this->plugin->broadcastMathQuestion();
                                }
                            }
                        }, 20 * $this->plugin->getConfigData()->get("maths_delay_solved"));
                    }
                } else {
                    // Stop task if no players online
                    $this->plugin->taskHandler->cancel();
                    $this->plugin->taskHandler = null;
                }
            }
        }, 20 * $this->getConfigData()->get("math_interval")));
    }
}
