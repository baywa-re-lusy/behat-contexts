<?php

namespace BayWaReLusy\BehatContext\Auth0Context;

use Exception;

class UserCredentials
{
    /**
     * @param string $username
     * @param string $password
     */
    public function __construct(
        protected string $username,
        protected string $password
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
}
