<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Exception;

class ConsoleContext implements Context
{
    protected ?int $lastReturnCode = null;

    /**
     * @When I call the console route :route
     * @throws Exception
     */
    public function iCallTheConsoleRoute(string $route): void
    {
        $status = null;
        $output = [];
        exec(getcwd() . '/console ' . $route, $output, $this->lastReturnCode);

        foreach ($output as $outputLine) {
            echo $outputLine . PHP_EOL;
        }

        if (
            str_contains(implode(PHP_EOL, $output), 'Notice:') ||
            str_contains(implode(PHP_EOL, $output), 'Warning:') ||
            str_contains(implode(PHP_EOL, $output), 'error')
        ) {
            throw new Exception("Command triggered a Notice, Warning or Fatal error.");
        }
    }

    /**
     * @Then the last return code should be :expectedReturnCode
     * @throws Exception
     */
    public function theReturnCodeShouldBe(int $expectedReturnCode): void
    {
        if ($this->lastReturnCode !== $expectedReturnCode) {
            throw new Exception(sprintf(
                'Return code is %s, but expected %s.',
                $this->lastReturnCode,
                $expectedReturnCode
            ));
        }
    }
}
