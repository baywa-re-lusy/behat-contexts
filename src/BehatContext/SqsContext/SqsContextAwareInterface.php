<?php

namespace BayWaReLusy\BehatContext\SqsContext;

use BayWaReLusy\BehatContext\SqsContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface SqsContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherSqsContext(BeforeScenarioScope $scope): void;

    public function getSqsContext(): SqsContext;
}
