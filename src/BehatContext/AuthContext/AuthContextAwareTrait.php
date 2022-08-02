<?php

namespace BayWaReLusy\BehatContext\AuthContext;

use BayWaReLusy\BehatContext\AuthContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait AuthContextAwareTrait
{
    protected AuthContext $auth0Context;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherAuthContext(BeforeScenarioScope $scope): void
    {
        $this->auth0Context = $scope->getEnvironment()->getContext(AuthContext::class);
    }

    public function getAuthContext(): AuthContext
    {
        return $this->auth0Context;
    }
}
