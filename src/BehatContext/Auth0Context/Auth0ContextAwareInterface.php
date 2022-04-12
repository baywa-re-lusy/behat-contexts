<?php

namespace BayWaReLusy\BehatContext\Auth0Context;

use BayWaReLusy\BehatContext\Auth0Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface Auth0ContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherAuth0Context(BeforeScenarioScope $scope): void;

    public function getAuth0Context(): Auth0Context;
}
