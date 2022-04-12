<?php

namespace BayWaReLusy\BehatContext\Auth0Context;

class UserCredentials
{
    /**
     * @param string $username
     * @param string $password
     * @param string $clientId
     */
    public function __construct(
        protected string $username,
        protected string $password,
        protected string $clientId
    ) {
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }
}
