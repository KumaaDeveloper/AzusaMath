<?php

namespace KumaDev\Math;

class MathTask {

    public static function generateProblem($plugin): array {
        $operators = ["+", "-", "*", "/"];
        $operator = $operators[array_rand($operators)];
        $maxNumber = $plugin->getConfigData()->get("number_max");
        $number1 = rand(1, $maxNumber);
        $number2 = rand(1, $maxNumber);

        if ($operator === "-" || $operator === "/") {
            if ($number1 < $number2) {
                $temp = $number1;
                $number1 = $number2;
                $number2 = $temp;
            }
        }

        $answer = match ($operator) {
            "+" => $number1 + $number2,
            "-" => $number1 - $number2,
            "*" => $number1 * $number2,
            "/" => round($number1 / $number2, 1),
        };

        return [
            'number1' => $number1,
            'number2' => $number2,
            'operator' => $operator,
            'answer' => $answer
        ];
    }
}
