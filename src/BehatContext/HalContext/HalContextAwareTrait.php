<?php

namespace BayWaReLusy\BehatContext\HalContext;

use BayWaReLusy\BehatContext\HalContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait HalContextAwareTrait
{
    protected HalContext $halContext;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherHalContext(BeforeScenarioScope $scope): void
    {
        $this->halContext = $scope->getEnvironment()->getContext(HalContext::class);
    }

    public function getHalContext(): HalContext
    {
        return $this->halContext;
    }
}
