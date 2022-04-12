<?php

namespace BayWaReLusy\BehatContext;

use BayWaReLusy\BehatContext\Auth0Context\MachineToMachineCredentials;
use BayWaReLusy\BehatContext\Auth0Context\UserCredentials;
use Behat\Behat\Context\Context;
use Exception;
use CurlHandle;

class Auth0Context implements Context
{
    /** @var HalContext */
    protected HalContext $halContext;

    /** @var string|null Auth0 Token Endpoint */
    protected ?string $auth0TokenEndpoint = null;

    /** @var string|null Auth0 Audience */
    protected ?string $auth0Audience = null;

    /** @var MachineToMachineCredentials[] */
    protected array $machineToMachineCredentials = [];

    /** @var UserCredentials[] */
    protected array $userCredentials = [];

    /**
     * Add credentials for a machine-to-machine connection to Auth0.
     *
     * @param MachineToMachineCredentials $machineToMachineCredentials
     * @return Auth0Context
     */
    public function addMachineToMachineCredentials(
        MachineToMachineCredentials $machineToMachineCredentials
    ): Auth0Context {
        $this->machineToMachineCredentials[] = $machineToMachineCredentials;
        return $this;
    }

    /**
     * Add credentials for a Login/Password connection to Auth0.
     *
     * @param UserCredentials $userCredentials
     * @return Auth0Context
     */
    public function addUserCredentials(UserCredentials $userCredentials): Auth0Context {
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
            throw new Exception('HalContext must be injected into Auth0Context before proceeding.');
        }

        return $this->halContext;
    }

    /**
     * @param HalContext $halContext
     * @return Auth0Context
     */
    public function setHalContext(HalContext $halContext): Auth0Context
    {
        $this->halContext = $halContext;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuth0TokenEndpoint(): ?string
    {
        return $this->auth0TokenEndpoint;
    }

    /**
     * @param string|null $auth0TokenEndpoint
     * @return Auth0Context
     */
    public function setAuth0TokenEndpoint(?string $auth0TokenEndpoint): Auth0Context
    {
        $this->auth0TokenEndpoint = $auth0TokenEndpoint;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuth0Audience(): ?string
    {
        return $this->auth0Audience;
    }

    /**
     * @param string|null $auth0Audience
     * @return Auth0Context
     */
    public function setAuth0Audience(?string $auth0Audience): Auth0Context
    {
        $this->auth0Audience = $auth0Audience;
        return $this;
    }

    /**
     * @Given I am authenticated as user :username
     * @throws Exception
     */
    public function iAmAuthenticatedAsUser(string $username)
    {
        $userCredentials = $this->getUserCredentials($username);
        $usernameHashKey = 'AUTH0_ACCESS_TOKEN_' . strtoupper(md5($username));

        if (!getenv($usernameHashKey)) {
            $curl     = $this->getAccessTokenForUser($userCredentials);
            $response = curl_exec($curl);
            $err      = curl_error($curl);
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
    public function iAmAuthenticatedAsMachineToMachineClient(string $machineToMachineClientName)
    {
        $machineToMachineCredentials = $this->getMachineToMachineCredentials($machineToMachineClientName);
        $usernameHashKey             = 'AUTH0_ACCESS_TOKEN_' . strtoupper($machineToMachineClientName);

        if (!getenv($usernameHashKey)) {
            $curl     = $this->getAccessTokenForMachineToMachineClient($machineToMachineCredentials);
            $response = curl_exec($curl);
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
                'audience'   => $this->getAuth0Audience(),
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
                'audience'      => $this->getAuth0Audience(),
                'client_id'     => $machineToMachineCredentials->getClientId(),
                'client_secret' => $machineToMachineCredentials->getClientSecret(),
            ];

        $curl = curl_init();
        curl_setopt_array($curl, $this->getCurlOptions($postFields));

        return $curl;
    }

    protected function getCurlOptions(array $postFields): array
    {
        return
            [
                CURLOPT_URL            => $this->getAuth0TokenEndpoint(),
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
