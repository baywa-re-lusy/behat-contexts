<?php

namespace BayWaReLusy\BehatContext\ConsoleContext;

use BayWaReLusy\BehatContext\ConsoleContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface ConsoleContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherConsoleContext(BeforeScenarioScope $scope): void;

    public function getConsoleContext(): ConsoleContext;
}
