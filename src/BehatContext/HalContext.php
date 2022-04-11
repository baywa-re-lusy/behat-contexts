<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client as HttpClient;
use Exception;
use stdClass;

class HalContext implements Context
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
     * @var array
     */
    protected array $headers = [];

    /**
     * The API Bearer token used for Authentication/Authorization.
     * @var string|null
     */
    protected ?string $bearerToken = null;

    /**
     * The query string to add (in URI Template format).
     * @var array
     */
    protected array $queryString = [];

    /**
     * @return string|null
     */
    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     * @return HalContext
     */
    public function setBaseUrl(string $baseUrl): HalContext
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return ResponseInterface|null
     * @throws Exception
     */
    public function getLastResponse(): ?ResponseInterface
    {
        if (null === $this->lastResponse) {
            throw new Exception('No request sent yet.');
        }

        return $this->lastResponse;
    }

    /**
     * @param ResponseInterface|null $lastResponse
     * @return HalContext
     */
    public function setLastResponse(?ResponseInterface $lastResponse): HalContext
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
     * @return HalContext
     */
    public function setJsonFilesPath(string $jsonFilesPath): HalContext
    {
        $this->jsonFilesPath = $jsonFilesPath;
        return $this;
    }

    /**
     * @Then response should be an ApiProblem
     */
    public function responseShouldBeAnApiProblem(): void
    {
        $contentType = $this->getLastResponse()->getHeader('Content-Type');

        if ('application/problem+json' !== $contentType[0]) {
            throw new \Exception(sprintf('Expected ApiProblem content type, but got %s.', $contentType[0]));
        }
    }

    /**
     * @Then response status code should be :statusCode
     * @throws Exception
     */
    public function responseStatusCodeShouldBe(string $statusCode): void
    {
        if ((string)$this->getLastResponse()->getStatusCode() !== $statusCode) {
            throw new \Exception(
                'HTTP code does not match ' . $statusCode .
                ' (actual: ' . $this->getLastResponse()->getStatusCode() . ')' . PHP_EOL
                . $this->getLastResponse()->getBody()
            );
        }
    }

    /**
     * @Then the response should contain exactly :nbEntries :typeEntries
     */
    public function theResponseShouldContainExactly(string $nbEntries, string $typeEntries): void
    {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        if (count($response->_embedded->$typeEntries) !== (int)$nbEntries) {
            throw new \Exception("The entry count doesn't match: " . count($response->_embedded->$typeEntries));
        }
    }

    /**
     * @Then echo last response
     */
    public function echoLastResponse(): void
    {
        $this->printDebug($this->getLastResponse()->getBody());
    }

    /**
     * @Then the response collection :collectionName should contain the resource:
     * @throws Exception
     */
    public function theResponseCollectionShouldContainTheResource(
        string $collectionName,
        TableNode $expectedResource
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        $collection = $response->_embedded->$collectionName;

        if (!$this->collectionContainsResource($collection, $expectedResource)) {
            throw new \Exception('Resource not found.');
        }
    }

    /**
     * @Then the response collection :collectionName should not contain the resource:
     * @throws Exception
     */
    public function theResponseCollectionShouldNotContainTheResource(
        string $collectionName,
        TableNode $expectedResource
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        $collection = $response->_embedded->$collectionName;

        if ($this->collectionContainsResource($collection, $expectedResource)) {
            throw new \Exception("Resource shouldn't have been found.");
        }
    }

    /**
     * @Then the response should be a JSON object containing:
     * @throws Exception
     */
    public function theResponseShouldBeAJsonObjectContaining(TableNode $expectedObject): void
    {
        /** @var string[] $response */
        $response = $this->getLastResponseJsonData(true);

        foreach ($expectedObject->getRows() as $row) {
            if (!array_key_exists($row[0], $response)) {
                throw new \Exception(sprintf("Key %s not found.", $row[0]));
            }

            $this->checkValue($row[0], $row[1], $response[$row[0]]);
        }
    }

    /**
     * @Then the response should be a JSON object matching :json
     * @throws Exception
     */
    public function theResponseShouldBeAJsonObjectMatching(string $json): void
    {
        if (str_starts_with($json, 'file://')) {
            $fileName = $this->getJsonFilesPath() . DIRECTORY_SEPARATOR . str_replace('file://', '', $json);
            $json     = file_get_contents($fileName);

            if (!$json) {
                throw new Exception(sprintf("File %s not found.", $fileName));
            }
        }

        if ($this->getLastResponseJsonData(true) !== json_decode($json, true)) {
            throw new Exception('Invalid answer.');
        }
    }

    /**
     * @Then response should contain an embedded collection of :number :collectionName with the following entries:
     * @throws Exception
     */
    public function responseShouldContainAnEmbeddedCollectionOfWithTheFollowingEntries(
        string $number,
        string $collectionName,
        TableNode $expectedCollectionEntries
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        foreach ($expectedCollectionEntries->getRowsHash() as $expectedCollectionKey => $expectedCollectionValue) {
            $found = false;
            foreach ($response->_embedded->$collectionName as $collectionEntry) {
                if ($collectionEntry->$expectedCollectionKey === $expectedCollectionValue) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("$expectedCollectionKey => $expectedCollectionValue not found in collection.");
            }
        }

        if (count($response->_embedded->$collectionName) !== (int)$number) {
            throw new \Exception(sprintf(
                "Collection contains %s elements instead of %s",
                count($response->_embedded->$collectionName),
                $number
            ));
        }
    }

    /**
     * @Then response should contain an embedded resource :resource with property :property and value :value
     * @throws Exception
     */
    public function responseShouldContainAnEmbeddedResourceWithPropertyAndValue(
        string $resource,
        string $property,
        string $value
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        if ($response->_embedded->$resource->$property != $value) {
            throw new \Exception('Invalid embedded resource value.');
        }
    }

    /**
     * @Then the resource :id in collection :collection should not contain the property :property
     * @throws Exception
     */
    public function theResourceInCollectionShouldNotContainTheProperty(
        string $id,
        string $collectionName,
        string $property
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        foreach ($response->_embedded->$collectionName as $resource) {
            if ($resource->id === $id && property_exists($resource, $property)) {
                throw new \Exception("Property shouldn't have been found.");
            }
        }
    }

    /**
     * @When I send a :method request to :url
     * @When I send a :method request to :url with JSON body :body
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public function iSendARequestToWithJsonBody(string $method, string $url, string $body)
    {
        $headers =
            [
                'Accept'       => 'application/hal+json',
                'Content-Type' => 'application/json',
            ];

        // Check if custom headers have been added
        if (!empty($this->headers)) {
            $headers = array_merge($headers, $this->headers);
        }

        // Check if there is a token to add
        if ($this->bearerToken) {
            $headers['Authorization'] = 'Bearer ' . $this->bearerToken;
        }

        $params = [
            'headers'     => $headers,
            'verify'      => false,
            'http_errors' => false,
            'query'       => $this->getQueryString(),
        ];

        // Add data to http body
        if (str_starts_with($body, 'file://')) {
            $body = file_get_contents(__DIR__ . '/../_files/' . substr($body, 7));
        }

        $params['body'] = $body;

        $this->setLastResponse($this->getHttpClient()->request(strtoupper($method), $url, $params));
    }

    /**
     * @return string[]
     */
    public function getQueryString(): array
    {
        return $this->queryString;
    }

    /**
     * @throws Exception
     */
    protected function getHttpClient(): HttpClient
    {
        if (!$this->httpClient) {
            if (!$this->getBaseUrl()) {
                throw new Exception('Base URL of the APIs webserver needs to be set first.');
            }

            $this->httpClient = new HttpClient(
                [
                    'base_uri' => $this->getBaseUrl(),
                    'verify'   => false,
                ]
            );
        }

        return $this->httpClient;
    }

    /**
     * Return true if the given collection contains the expected resource, false otherwise.
     *
     * @param stdClass[] $collection
     * @param TableNode $expectedResource
     * @return bool
     */
    protected function collectionContainsResource(array $collection, TableNode $expectedResource): bool
    {
        foreach ($collection as $receivedResource) {
            if ($this->resourceMatch($expectedResource, $receivedResource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param TableNode $expectedResource
     * @param stdClass $receivedResource
     * @return bool
     */
    protected function resourceMatch(TableNode $expectedResource, stdClass $receivedResource): bool
    {
        $expectedResource = $expectedResource->getRowsHash();
        $resourceFound    = true;

        foreach ($expectedResource as $key => $val) {
            // Check if value is a boolean or a link to a file
            $val = $this->getOrCastValue($val);

            if (
                (!property_exists($receivedResource, $key) || $val != $receivedResource->$key) &&
                (
                    !property_exists($receivedResource, '_embedded') ||
                    !property_exists($receivedResource->_embedded, $key) ||
                    $receivedResource->_embedded->$key->id != $val
                )
            ) {
                $resourceFound = false;
                break;
            }
        }

        return $resourceFound;
    }

    /**
     * @param bool $returnAsAssociativeArray
     * @return stdClass|string[]
     * @throws Exception
     */
    protected function getLastResponseJsonData(bool $returnAsAssociativeArray = false): array|stdClass
    {
        $responseBody = $this->getLastResponse()->getBody();
        $data         = json_decode($responseBody, $returnAsAssociativeArray);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception(sprintf('Invalid json body: %s', $responseBody));
        }

        return $data;
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

    /**
     * Transform the given value into the correct type/content.
     *
     * @param string $value
     * @return mixed
     */
    protected function getOrCastValue(string $value): mixed
    {
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (str_starts_with($value, 'file://')) {
            $value = file_get_contents($this->getJsonFilesPath() . DIRECTORY_SEPARATOR . substr($value, 7));
        }

        // Check if value is JSON
        if (is_string($value)) {
            $json = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE && !preg_match('/^\d+$/', $value)) {
                $value = $json;
            }
        }

        return $value;
    }

    /**
     * @param string $key
     * @param mixed $expectedValue
     * @param mixed $actualValue
     * @return void
     * @throws Exception
     */
    protected function checkValue(string $key, mixed $expectedValue, mixed $actualValue): void
    {
        if (str_starts_with($expectedValue, 'file://')) {
            $fileName      = $this->getJsonFilesPath() . DIRECTORY_SEPARATOR . substr($expectedValue, 7);
            $expectedValue = file_get_contents($fileName);

            if (!$expectedValue) {
                throw new Exception(sprintf("File %s not found.", $fileName));
            }
        }

        json_decode($expectedValue);
        if (json_last_error() == JSON_ERROR_NONE) {
            $expectedValue = json_decode((string)$expectedValue, true);
        }

        if ($expectedValue === '') {
            $expectedValue = null;
        }

        if (is_string($expectedValue) && str_starts_with($expectedValue, 'match://')) {
            if (!preg_match(substr($expectedValue, 8), $actualValue)) {
                throw new \Exception(sprintf("Value %s doesn't match regexp %s.", $actualValue, $expectedValue));
            }
        } elseif ($actualValue != $expectedValue) {
            throw new \Exception(sprintf(
                "Wrong value %s for key %s",
                var_export($actualValue, true),
                $key
            ));
        }
    }
}
