<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Exception;

class ConsoleContext implements Context
{
    /**
     * @When I call the console route :arg1
     * @When I call the console route :arg1 with argument :argument1
     * @When I call the console route :arg1 with argument :argument1 and :argument2
     * @throws Exception
     */
    public function iCallTheConsoleRoute(string $route, string $argument1 = null, string $argument2 = null): void
    {
        $status = null;
        $output = [];
        $argumentString = "";
        if ($argument1) {
            $argumentString = $argumentString . " " . $argument1;
        }
        if ($argument2) {
            $argumentString = $argumentString . " " . $argument2;
        }
        exec(getcwd() . '/console ' . $route . $argumentString, $output, $status);

        foreach ($output as $outputLine) {
            echo $outputLine . PHP_EOL;
        }

        if ($status > 0) {
            throw new \Exception("Command returned a status > 0: " . $status);
        }

        if (
            str_contains(implode(PHP_EOL, $output), 'Notice:') ||
            str_contains(implode(PHP_EOL, $output), 'Warning:') ||
            str_contains(implode(PHP_EOL, $output), 'error')
        ) {
            throw new Exception("Command triggered a Notice, Warning or Fatal error.");
        }
    }
}
