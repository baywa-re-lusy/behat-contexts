<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Exception;

class ConsoleContext implements Context
{
    /**
     * @When I call the console route :arg1
     * @throws Exception
     */
    public function iCallTheConsoleRoute(string $route): void
    {
        $status = null;
        $output = [];
        exec(getcwd() . '/console ' . $route, $output, $status);

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
