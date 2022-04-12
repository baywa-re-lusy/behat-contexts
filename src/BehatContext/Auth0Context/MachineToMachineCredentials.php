<?php

namespace BayWaReLusy\BehatContext\Auth0Context;

use Exception;

class MachineToMachineCredentials
{
    /**
     * @param string $clientName
     * @param string $clientId
     * @param string $clientSecret
     * @throws Exception
     */
    public function __construct(
        protected string $clientName,
        protected string $clientId,
        protected string $clientSecret
    ) {
        if (!preg_match('/^[A-Za-z0-9]+$/', $clientName)) {
            throw new Exception('Client name must have the format /^[A-Za-z0-9]+$/.');
        }
    }

    /**
     * @return string
     */
    public function getClientName(): string
    {
        return $this->clientName;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }
}
