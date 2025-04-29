<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractApiResponseContext implements Context
{
    protected ?HttpClient $httpClient = null;
    protected ?ResponseInterface $lastResponse = null;

    /**
     * URL of the APIs webserver.
     * @var string|null
     */
    protected ?string $baseUrl = null;

    /**
     * Path to the directory with example JSON files.
     * @var string
     */
    protected string $jsonFilesPath;

    /**
     * The headers to add to outgoing requests.
     * @var string[]
     */
    protected array $headers = [];

    /**
     * The API Bearer token used for Authentication/Authorization.
     * @var string|null
     */
    protected ?string $bearerToken = null;

    /**
     * The query string to add (in URI Template format).
     * @var string[]
     */
    protected array $queryString = [];

    /**
     * List of placeholder key/value pairs to replace in URL.
     * @var string[]
     */
    protected array $placeholders = [];

    /**
     * @return string|null
     */
    public function getBearerToken(): ?string
    {
        return $this->bearerToken;
    }

    /**
     * @param string|null $bearerToken
     * @return AbstractApiResponseContext
     */
    public function setBearerToken(?string $bearerToken): AbstractApiResponseContext
    {
        $this->bearerToken = $bearerToken;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     * @return AbstractApiResponseContext
     */
    public function setBaseUrl(string $baseUrl): AbstractApiResponseContext
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return ResponseInterface|null
     * @throws \Exception
     */
    public function getLastResponse(): ?ResponseInterface
    {
        if (null === $this->lastResponse) {
            throw new \Exception('No request sent yet.');
        }

        return $this->lastResponse;
    }

    /**
     * @param ResponseInterface|null $lastResponse
     * @return AbstractApiResponseContext
     */
    public function setLastResponse(?ResponseInterface $lastResponse): AbstractApiResponseContext
    {
        $this->lastResponse = $lastResponse;
        return $this;
    }

    /**
     * @return string
     */
    public function getJsonFilesPath(): string
    {
        return rtrim($this->jsonFilesPath, '/');
    }

    /**
     * @param string $jsonFilesPath
     * @return AbstractApiResponseContext
     */
    public function setJsonFilesPath(string $jsonFilesPath): AbstractApiResponseContext
    {
        $this->jsonFilesPath = $jsonFilesPath;
        return $this;
    }

    /**
     * @Then echo last response
     */
    public function echoLastResponse(): void
    {
        $this->printDebug($this->getLastResponse()->getBody());
    }

    /**
     * Prints beautified debug string.
     *
     * @param string $string debug string
     */
    protected function printDebug(string $string): void
    {
        echo "\n\033[36m|  " . strtr($string, ["\n" => "\n|  "]) . "\033[0m";
    }
}
