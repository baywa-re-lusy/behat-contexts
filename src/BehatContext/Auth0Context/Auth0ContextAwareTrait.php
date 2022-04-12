<?php

namespace BayWaReLusy\BehatContext\Auth0Context;

use BayWaReLusy\BehatContext\Auth0Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait Auth0ContextAwareTrait
{
    protected Auth0Context $auth0Context;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherAuth0Context(BeforeScenarioScope $scope): void
    {
        $this->auth0Context = $scope->getEnvironment()->getContext(Auth0Context::class);
    }

    public function getAuth0Context(): Auth0Context
    {
        return $this->auth0Context;
    }
}
