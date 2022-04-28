<?php

namespace BayWaReLusy\BehatContext\ConsoleContext;

use BayWaReLusy\BehatContext\ConsoleContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait ConsoleContextAwareTrait
{
    protected ConsoleContext $consoleContext;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherConsoleContext(BeforeScenarioScope $scope): void
    {
        $this->consoleContext = $scope->getEnvironment()->getContext(ConsoleContext::class);
    }

    public function getConsoleContext(): ConsoleContext
    {
        return $this->consoleContext;
    }
}
