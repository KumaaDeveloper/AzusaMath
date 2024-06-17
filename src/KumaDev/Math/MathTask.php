<?php

namespace KumaDev\Math;

use pocketmine\scheduler\Task;

class MathTask extends Task {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        if (!$this->plugin->isMathEnabled() || count($this->plugin->getServer()->getOnlinePlayers()) == 0) {
            return;
        }

        $problem = $this->generateProblem();
        $this->plugin->getServer()->broadcastMessage("Define §f{$problem['number1']} §e{$problem['operator']} §f{$problem['number2']} §e= §f?");
        $this->plugin->setCurrentAnswer($problem['answer']);
        
        $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin) extends Task {
            private $plugin;
            
            public function __construct(Main $plugin) {
                $this->plugin = $plugin;
            }
            
            public function onRun(): void {
                if ($this->plugin->getCurrentAnswer() !== null && $this->plugin->isMathEnabled()) {
                    $this->plugin->getServer()->broadcastMessage($this->plugin->getConfigData()->get("no_answer_message"));
                    $this->plugin->setCurrentAnswer(null);

                    if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                        if ($this->plugin->getTaskHandler() !== null) {
                            $this->plugin->getTaskHandler()->cancel();
                        }

                        $this->plugin->getScheduler()->scheduleDelayedTask(new class($this->plugin) extends Task {
                            private $plugin;

                            public function __construct(Main $plugin) {
                                $this->plugin = $plugin;
                            }

                            public function onRun(): void {
                                if ($this->plugin->isMathEnabled() && count($this->plugin->getServer()->getOnlinePlayers()) > 0) {
                                    $this->plugin->scheduleMathTask();
                                }
                            }
                        }, 20 * $this->plugin->getConfigData()->get("maths_delay_solved"));
                    }
                }
            }
        }, 20 * 60); // Delay of 60 seconds for the next problem
    }

    private function generateProblem() {
        $operators = ["+", "-", "*", "/"];
        $operator = $operators[array_rand($operators)];
        $maxNumber = $this->plugin->getConfigData()->get("number_max");
        $number1 = rand(1, $maxNumber);
        $number2 = rand(1, $maxNumber);

        if ($operator === "-") {
            if ($number1 < $number2) {
                $temp = $number1;
                $number1 = $number2;
                $number2 = $temp;
            }
        } elseif ($operator === "/") {
            while ($number2 == 0 || $number1 % $number2 != 0) {
                $number2 = rand(1, $maxNumber);
            }
        }

        $question = "$number1 $operator $number2";
        $answer = eval("return $number1 $operator $number2;");

        return ['number1' => $number1, 'number2' => $number2, 'operator' => $operator, 'answer' => $answer];
    }
}
