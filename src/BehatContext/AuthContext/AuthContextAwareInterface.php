<?php

namespace BayWaReLusy\BehatContext\AuthContext;

use BayWaReLusy\BehatContext\AuthContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

interface AuthContextAwareInterface
{
    /**
     * @BeforeScenario
     */
    public function gatherAuthContext(BeforeScenarioScope $scope): void;

    public function getAuthContext(): AuthContext;
}
