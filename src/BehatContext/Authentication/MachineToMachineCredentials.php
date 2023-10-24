<?php

namespace BayWaReLusy\BehatContext\Authentication;

use Exception;

class MachineToMachineCredentials
{
    /**
     * @param string $clientName
     * @param string $clientId
     * @param string $clientSecret
     * @param string|null $audience
     * @throws Exception
     */
    public function __construct(
        protected string $clientName,
        protected string $clientId,
        protected string $clientSecret,
        protected ?string $audience = null
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

    /**
     * @return string|null
     */
    public function getAudience(): ?string
    {
        return $this->audience;
    }

    /**
     * @param string|null $audience
     * @return $this
     */
    public function setAudience(?string $audience): MachineToMachineCredentials
    {
        $this->audience = $audience;
        return $this;
    }
}
