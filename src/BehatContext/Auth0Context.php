<?php

namespace BayWaReLusy\BehatContext;

use BayWaReLusy\BehatContext\Auth0Context\MachineToMachineCredentials;
use Behat\Behat\Context\Context;
use Exception;
use CurlHandle;

class Auth0Context implements Context
{
    /** @var HalContext */
    protected HalContext $halContext;

    /** @var string|null Password of the Auth0 test user */
    protected ?string $testUserPassword = null;

    /** @var string|null Auth0 Token Endpoint */
    protected ?string $auth0TokenEndpoint = null;

    /** @var string|null Auth0 Audience */
    protected ?string $auth0Audience = null;

    /** @var string|null Auth0 Client ID */
    protected ?string $auth0ClientId = null;

    /** @var MachineToMachineCredentials[] */
    protected array $machineToMachineCredentials = [];

    protected function addMachineToMachineCredentials(
        MachineToMachineCredentials $machineToMachineCredentials
    ): Auth0Context {
        $this->machineToMachineCredentials[] = $machineToMachineCredentials;
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
    public function getTestUserPassword(): ?string
    {
        return $this->testUserPassword;
    }

    /**
     * @param string|null $testUserPassword
     * @return Auth0Context
     */
    public function setTestUserPassword(?string $testUserPassword): Auth0Context
    {
        $this->testUserPassword = $testUserPassword;
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
     * @return string|null
     */
    public function getAuth0ClientId(): ?string
    {
        return $this->auth0ClientId;
    }

    /**
     * @param string|null $auth0ClientId
     * @return Auth0Context
     */
    public function setAuth0ClientId(?string $auth0ClientId): Auth0Context
    {
        $this->auth0ClientId = $auth0ClientId;
        return $this;
    }

    /**
     * @Given I am authenticated as user :username
     * @throws Exception
     */
    public function iAmAuthenticatedAsUser(string $username)
    {
        $usernameHashKey = 'AUTH0_ACCESS_TOKEN_' . strtoupper(md5($username));

        if (!getenv($usernameHashKey)) {
            $curl     = $this->getAccessToken($username);
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
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://dev-jc40e-zf.eu.auth0.com/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS =>
                    "grant_type=client_credentials" .
                    "&audience=" . $this->getAuth0Audience() .
                    "&client_id=" . $machineToMachineCredentials->getClientId() .
                    "&client_secret=" . $machineToMachineCredentials->getClientSecret(),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json"
                ),
            ));

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
     * @param string $username
     * @return CurlHandle|bool
     */
    protected function getAccessToken(string $username): CurlHandle|bool
    {
        $postFields =
            'grant_type=password' .
            '&username=' . urlencode($username) .
            '&password=' . $this->getTestUserPassword() .
            '&audience=' . $this->getAuth0Audience() .
            '&client_id=' . $this->getAuth0ClientId();

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => $this->getAuth0TokenEndpoint(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
            ]
        );

        return $curl;
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
}
