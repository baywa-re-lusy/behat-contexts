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

    /** @var string Server Address */
    protected string $serverAddress;

    /** @var string Token Endpoint */
    protected string $tokenEndpoint;

    /** @var MachineToMachineCredentials[] */
    protected array $machineToMachineCredentials = [];

    /** @var UserCredentials[] */
    protected array $userCredentials = [];

    /** @var array<string, array<mixed>> */
    protected array $claims = [];

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
    public function getServerAddress(): string
    {
        return $this->serverAddress;
    }

    /**
     * @param string $serverAddress
     * @return AuthContext
     */
    public function setServerAddress(string $serverAddress): AuthContext
    {
        $this->serverAddress = $serverAddress;
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
     * @Given I am authenticated as a user :username
     * @throws Exception
     */
    public function iAmAuthenticatedAsAUser(string $username): void
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

                if (!is_array($response) || !array_key_exists('access_token', $response)) {
                    throw new Exception(sprintf('Invalid Auth Server Response: %s', var_export($response, true)));
                }

                $this->getHalContext()->setBearerToken($response['access_token']);
                putenv($usernameHashKey . '=' . $response['access_token']);
            }
        } else {
            $this->getHalContext()->setBearerToken(getenv($usernameHashKey));
        }
    }

    /**
     * @Given a claim :claimName with values :values
     */
    public function withClaims(string $claimName, string $values): void
    {
        $claimValues = [];
        foreach (explode(',', $values) as $value) {
            $claimValues[] = $value;
        }
        $this->claims = [$claimName => $claimValues];
    }

    /**
     * @Given I am authenticated as a Machine-to-Machine Client :machineToMachineClientName
     * @throws Exception
     */
    public function iAmAuthenticatedAsAMachineToMachineClient(string $machineToMachineClientName): void
    {
        $machineToMachineCredentials = $this->getMachineToMachineCredentials($machineToMachineClientName);
        $usernameHashKey             = 'AUTH_ACCESS_TOKEN_' . strtoupper($machineToMachineClientName);

        if (!getenv($usernameHashKey)) {
            if ($this->claims) {
                $curl = $this->getAccessTokenForMachineToMachineClientWithClaims(
                    $machineToMachineCredentials,
                    $this->getClaims()
                );
            } else {
                $curl = $this->getAccessTokenForMachineToMachineClient($machineToMachineCredentials);
            }

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
     * @param MachineToMachineCredentials $machineToMachineCredentials
     * @param array $claims
     * @return CurlHandle|bool
     * @throws Exception
     */
    protected function getAccessTokenForMachineToMachineClientWithClaims(
        MachineToMachineCredentials $machineToMachineCredentials,
        array $claims
    ): CurlHandle|bool {
        if (!$machineToMachineCredentials->getAudience()) {
            throw new Exception("An audience has to be set on the credentials for the claims to work");
        }
        $encodedClaims = base64_encode(json_encode($claims));
        error_log($encodedClaims);
        $postFields =
            [
                'grant_type'    => 'urn:ietf:params:oauth:grant-type:uma-ticket',
                'client_id'     => $machineToMachineCredentials->getClientId(),
                'client_secret' => $machineToMachineCredentials->getClientSecret(),
                'claim_token_format' => 'urn:ietf:params:oauth:token-type:jwt',
                'claim_token' => $encodedClaims,
                'audience' => $machineToMachineCredentials->getAudience()
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
        $postFieldsEncoded = [];
        foreach ($postFields as $key => $value) {
            $postFieldsEncoded[] = sprintf('%s=%s', $key, $value);
        }
        return
            [
                CURLOPT_URL            => rtrim($this->getServerAddress(), '/')
                    . '/'
                    . ltrim($this->getTokenEndpoint(), '/'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => implode('&', $postFieldsEncoded),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
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

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function setClaims(array $claims): AuthContext
    {
        $this->claims = $claims;
        return $this;
    }

}
