<?php

namespace BayWaReLusy\BehatContext\HalContext;

use BayWaReLusy\BehatContext\HalContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface HalContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherHalContext(BeforeScenarioScope $scope): void;

    public function getHalContext(): HalContext;
}
