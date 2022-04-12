<?php

namespace BayWaReLusy\BehatContext\SqsContext;

use BayWaReLusy\BehatContext\SqsContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait SqsContextAwareTrait
{
    protected SqsContext $sqsContext;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherSqsContext(BeforeScenarioScope $scope): void
    {
        $this->sqsContext = $scope->getEnvironment()->getContext(SqsContext::class);
    }

    public function getSqsContext(): SqsContext
    {
        return $this->sqsContext;
    }
}
