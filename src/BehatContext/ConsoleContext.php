<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Exception;

class ConsoleContext implements Context
{
    protected ?int $lastReturnCode = null;
    protected ?array $lastOutput   = [];

    /**
     * @When I call the console route :route
     * @throws Exception
     */
    public function iCallTheConsoleRoute(string $route): void
    {
        $status = null;
        exec(getcwd() . '/console ' . $route, $this->lastOutput, $this->lastReturnCode);

        foreach ($this->lastOutput as $outputLine) {
            echo $outputLine . PHP_EOL;
        }

        if (
            str_contains(implode(PHP_EOL, $this->lastOutput), 'Notice:') ||
            str_contains(implode(PHP_EOL, $this->lastOutput), 'Warning:') ||
            str_contains(implode(PHP_EOL, $this->lastOutput), 'error')
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

    /**
     * @Then the output should contain :expectedOutput
     * @throws Exception
     */
    public function theOutputShouldContain(string $expectedOutput): void
    {
        if (!str_contains(implode(PHP_EOL, $this->lastOutput), $expectedOutput)) {
            throw new Exception("Output doesn't contain the expected output.");
        }
    }

    /**
     * @Then the command should have updated the last execution timestamp in :timestampFile
     * @throws Exception
     */
    public function theCommandShouldHaveUpdatedTheLastExecutionTimestampIn(string $fileName): void
    {
        $timestamp = file_get_contents(getcwd() . '/' . $fileName);
        $timestamp = \DateTime::createFromFormat('U', $timestamp);

        if (!$timestamp || $timestamp->getTimestamp() < time() - 5) {
            throw new \Exception('Timestamp is invalid or too old.');
        }
    }
}
