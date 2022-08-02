<?php

namespace BayWaReLusy\BehatContext;

use BayWaReLusy\BehatContext\Authentication\MachineToMachineCredentials;
use BayWaReLusy\BehatContext\Authentication\UserCredentials;
use Behat\Behat\Context\Context;
use Exception;
use CurlHandle;

class AuthContext implements Context
{
    /** @var HalContext */
    protected HalContext $halContext;

    /** @var string Token Endpoint */
    protected string $tokenEndpoint;

    /** @var MachineToMachineCredentials[] */
    protected array $machineToMachineCredentials = [];

    /** @var UserCredentials[] */
    protected array $userCredentials = [];

    /**
     * Add credentials for a machine-to-machine connection.
     *
     * @param MachineToMachineCredentials $machineToMachineCredentials
     * @return AuthContext
     */
    public function addMachineToMachineCredentials(
        MachineToMachineCredentials $machineToMachineCredentials
    ): AuthContext {
        $this->machineToMachineCredentials[] = $machineToMachineCredentials;
        return $this;
    }

    /**
     * Add credentials for a Login/Password connection.
     *
     * @param UserCredentials $userCredentials
     * @return AuthContext
     */
    public function addUserCredentials(UserCredentials $userCredentials): AuthContext
    {
        $this->userCredentials[] = $userCredentials;
        return $this;
    }

    /**
     * @return HalContext
     * @throws Exception
     */
    public function getHalContext(): HalContext
    {
        if (!isset($this->halContext)) {
            throw new Exception('HalContext must be injected into AuthContext before proceeding.');
        }

        return $this->halContext;
    }

    /**
     * @param HalContext $halContext
     * @return AuthContext
     */
    public function setHalContext(HalContext $halContext): AuthContext
    {
        $this->halContext = $halContext;
        return $this;
    }

    /**
     * @return string
     */
    public function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * @param string|null $tokenEndpoint
     * @return AuthContext
     */
    public function setTokenEndpoint(?string $tokenEndpoint): AuthContext
    {
        $this->tokenEndpoint = $tokenEndpoint;
        return $this;
    }

    /**
     * @Given I am authenticated as user :username
     * @throws Exception
     */
    public function iAmAuthenticatedAsUser(string $username): void
    {
        $userCredentials = $this->getUserCredentials($username);
        $usernameHashKey = 'AUTH_ACCESS_TOKEN_' . strtoupper(md5($username));

        if (!getenv($usernameHashKey)) {
            $curl = $this->getAccessTokenForUser($userCredentials);

            if (!$curl instanceof CurlHandle) {
                throw new Exception("Couldn't fetch Bearer Token.");
            }

            $response = curl_exec($curl);

            if (!is_string($response)) {
                throw new Exception(sprintf('Invalid curl response: %s', var_export($response, true)));
            }

            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                throw new Exception(sprintf("cURL Error #:%s", $err));
            } else {
                $response = json_decode($response, true);
                $this->getHalContext()->setBearerToken($response['access_token']);
                putenv($usernameHashKey . '=' . $response['access_token']);
            }
        } else {
            $this->getHalContext()->setBearerToken(getenv($usernameHashKey));
        }
    }

    /**
     * @Given I am authenticated as Machine-to-Machine Client :machineToMachineClientName
     * @throws Exception
     */
    public function iAmAuthenticatedAsMachineToMachineClient(string $machineToMachineClientName): void
    {
        $machineToMachineCredentials = $this->getMachineToMachineCredentials($machineToMachineClientName);
        $usernameHashKey             = 'AUTH_ACCESS_TOKEN_' . strtoupper($machineToMachineClientName);

        if (!getenv($usernameHashKey)) {
            $curl = $this->getAccessTokenForMachineToMachineClient($machineToMachineCredentials);

            if (!$curl instanceof CurlHandle) {
                throw new Exception("Couldn't fetch Bearer Token.");
            }

            $response = curl_exec($curl);

            if (!is_string($response)) {
                throw new Exception(sprintf('Invalid curl response: %s', var_export($response, true)));
            }

            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                throw new Exception(sprintf("cURL Error #:%s", $err));
            } else {
                $response = json_decode($response, true);
                putenv($usernameHashKey . '=' . $response['access_token']);
                $this->getHalContext()->setBearerToken($response['access_token']);
            }
        } else {
            $this->getHalContext()->setBearerToken(getenv($usernameHashKey));
        }
    }

    /**
     * @param UserCredentials $userCredentials
     * @return CurlHandle|bool
     */
    protected function getAccessTokenForUser(UserCredentials $userCredentials): CurlHandle|bool
    {
        $postFields =
            [
                'grant_type' => 'password',
                'username'   => $userCredentials->getUsername(),
                'password'   => $userCredentials->getPassword(),
                'client_id'  => $userCredentials->getClientId(),
            ];

        $curl = curl_init();
        curl_setopt_array($curl, $this->getCurlOptions($postFields));

        return $curl;
    }

    /**
     * @param MachineToMachineCredentials $machineToMachineCredentials
     * @return CurlHandle|bool
     */
    protected function getAccessTokenForMachineToMachineClient(
        MachineToMachineCredentials $machineToMachineCredentials
    ): CurlHandle|bool {
        $postFields =
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $machineToMachineCredentials->getClientId(),
                'client_secret' => $machineToMachineCredentials->getClientSecret(),
            ];

        $curl = curl_init();
        curl_setopt_array($curl, $this->getCurlOptions($postFields));

        return $curl;
    }

    /**
     * @param string[] $postFields
     * @return array<int, array<int, string>|bool|int|string|null>
     */
    protected function getCurlOptions(array $postFields): array
    {
        return
            [
                CURLOPT_URL            => $this->getTokenEndpoint(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($postFields),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json']
            ];
    }

    /**
     * @throws Exception
     */
    protected function getMachineToMachineCredentials(string $machineToMachineClientName): MachineToMachineCredentials
    {
        foreach ($this->machineToMachineCredentials as $machineToMachineCredentials) {
            if ($machineToMachineCredentials->getClientName() === $machineToMachineClientName) {
                return $machineToMachineCredentials;
            }
        }

        throw new Exception(sprintf("No M2M credentials found with name '%s'", $machineToMachineClientName));
    }

    /**
     * @throws Exception
     */
    protected function getUserCredentials(string $username): UserCredentials
    {
        foreach ($this->userCredentials as $userCredentials) {
            if ($userCredentials->getUsername() === $username) {
                return $userCredentials;
            }
        }

        throw new Exception(sprintf("No User credentials found with username '%s'", $username));
    }
}
