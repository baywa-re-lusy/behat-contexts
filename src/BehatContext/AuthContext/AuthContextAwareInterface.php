<?php

namespace BayWaReLusy\BehatContext\AuthContext;

use BayWaReLusy\BehatContext\AuthContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface AuthContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherAuth0Context(BeforeScenarioScope $scope): void;

    public function getAuth0Context(): AuthContext;
}
