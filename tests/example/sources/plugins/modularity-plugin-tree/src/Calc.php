<?php

declare(strict_types=1);

namespace Inpsyde\App\Tests\Project\ModularityPlugin3;

class Calc
{
    /**
     * @return Calc
     */
    public static function new(): Calc
    {
        return new self();
    }

    /**
     */
    private function __construct()
    {
    }

    /**
     * @param string $left
     * @param string $op
     * @param string $right
     * @return int
     */
    public function calculate(string $left, string $op, string $right): int
    {
        assert(is_numeric($left));
        assert(is_numeric($right));

        switch ($op) {
            case '+':
                return (int)$left + (int)$right;
            case '-':
                return (int)$left - (int)$right;
            case '*':
                return (int)$left * (int)$right;
            case '/':
                return (int)$left / (int)$right;
        }

        throw new \Error("Invalid operation: ({$left} {$op} {$right})");
    }
}
