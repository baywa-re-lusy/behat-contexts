<?php

namespace BayWaReLusy\BehatContext;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Exception;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use stdClass;

abstract class AbstractApiResponseContext implements Context
{
    protected ?HttpClient $httpClient          = null;
    protected ?ResponseInterface $lastResponse = null;
    protected ?string $lastResponseBody        = null;

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
     * @throws Exception
     * @deprecated Should become protected in a next release because the response body can only be read once (stream).
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
     * @return AbstractApiResponseContext
     */
    public function setLastResponse(?ResponseInterface $lastResponse): AbstractApiResponseContext
    {
        $this->lastResponse     = $lastResponse;
        $this->lastResponseBody = $lastResponse->getBody()->getContents();

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
     * Add a placeholder to replace later in URL.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function addPlaceholder(string $key, string $value): AbstractApiResponseContext
    {
        $this->placeholders[$key] = $value;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getQueryString(): array
    {
        return $this->queryString;
    }

    /**
     * @Given header :name with value :value
     */
    public function headerWithValue(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * @Given query string parameter :name with value :value
     */
    public function queryStringParameterWithValue(string $name, string $value): void
    {
        $this->queryString[$name] = $value;
    }

    /**
     * @Then response status code should be :statusCode
     * @throws Exception
     */
    public function responseStatusCodeShouldBe(string $statusCode): void
    {
        if ((string)$this->getLastResponse()->getStatusCode() !== $statusCode) {
            throw new Exception(
                'HTTP code does not match ' . $statusCode .
                ' (actual: ' . $this->getLastResponse()->getStatusCode() . ')' . PHP_EOL
                . $this->getLastResponse()->getBody()
            );
        }
    }

    /**
     * @Then error message on field :expectedField should be of type :expectedErrorType
     */
    abstract public function errorMessageOnFieldShouldBe(string $expectedField, string $expectedErrorType): void;

    /**
     * @Then the response array should contain :number entries
     * @throws Exception
     */
    public function theResponseArrayShouldContainEntries(int $number): void
    {
        $response = $this->getLastResponseJsonDataAsArray();

        if (!array_is_list($response)) {
            throw new Exception('Response is not an array.');
        }

        if (count($response) !== $number) {
            throw new Exception("Response array doesn't have the correct size.");
        }
    }

    /**
     * @Then the response array should contain the entry:
     * @throws Exception
     */
    public function theResponseArrayShouldContainTheEntry(TableNode $expectedEntry): void
    {
        $response = $this->getLastResponseJsonDataAsArray();

        if (!array_is_list($response)) {
            throw new Exception('Response is not an array.');
        }

        $expectedEntry = $expectedEntry->getRowsHash();

        // Normalize booleans
        foreach ($expectedEntry as $key => &$value) {
            if (in_array($value, ['true', 'false'], true)) {
                $value = $value === 'true';
            }
        }

        $entryFound = false;

        foreach ($response as $entry) {
            // check if all expected key/value pairs exist in this entry
            $matches = true;

            foreach ($expectedEntry as $key => $expectedValue) {
                if (!array_key_exists($key, $entry)) {
                    $matches = false;
                    break;
                }

                $actualValue = $entry[$key];

                // If the expected value is a JSON string, decode it
                if (is_string($expectedValue)) {
                    $expectedDecoded = json_decode($expectedValue, true);
                    if ($expectedDecoded !== null) {
                        $expectedValue = $expectedDecoded;
                    }
                }

                if ($expectedValue === '<UUID>') {
                    // UUID validation
                    if (!Uuid::isValid($actualValue)) {
                        $matches = false;
                        break;
                    }
                } elseif ($actualValue != $expectedValue) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $entryFound = true;
                break;
            }
        }

        if (!$entryFound) {
            throw new Exception("Response array doesn't contain expected entry.");
        }
    }

    /**
     * @Then the response should be a JSON object containing:
     * @throws Exception
     */
    public function theResponseShouldBeAJsonObjectContaining(TableNode $expectedObject): void
    {
        /** @var string[] $response */
        $response = $this->getLastResponseJsonDataAsArray();

        foreach ($expectedObject->getRows() as $row) {
            if (!array_key_exists($row[0], $response)) {
                throw new Exception(sprintf("Key %s not found.", $row[0]));
            }

            $this->checkValue($row[0], $row[1], $response[$row[0]]);
        }
    }

    /**
     * @When I send a :method request to :url
     * @When I send a :method request to :url with JSON body :body
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @return void
     */
    abstract public function iSendARequestToWithJsonBody(string $method, string $url, ?string $body = null): void;

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

        if ($this->getLastResponseJsonDataAsArray() !== json_decode($json, true)) {
            throw new Exception('Invalid answer.');
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param string|null $body
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendRequestWithJsonBody(string $method, string $url, array $headers, ?string $body = null): void
    {
        // Replace placeholders in URL
        $url = $this->replacePlaceholdersInUrl($url);

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
        if (!is_null($body)) {
            if (str_starts_with($body, 'file://')) {
                $body = file_get_contents($this->getJsonFilesPath() . DIRECTORY_SEPARATOR . substr($body, 7));
            }

            $params['body'] = $body;
        }

        $this->setLastResponse($this->getHttpClient()->request(strtoupper($method), $url, $params));
    }

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
                throw new Exception(sprintf("Value %s doesn't match regexp %s.", $actualValue, $expectedValue));
            }
        } elseif ($actualValue != $expectedValue) {
            throw new Exception(sprintf(
                "Wrong value %s for key %s",
                var_export($actualValue, true),
                $key
            ));
        }
    }

    protected function replacePlaceholdersInUrl(string $url): string
    {
        preg_match('/{([A-Z_0-9]+)}/', $url, $placeholders);

        if (count($placeholders) > 1) {
            array_shift($placeholders);
            foreach ($placeholders as $placeholder) {
                $url = str_replace('{' . $placeholder . '}', $this->placeholders[$placeholder], $url);
            }
        }

        return $url;
    }

    protected function resourceMatch(TableNode $expectedResource, stdClass $receivedResource): bool
    {
        $expectedResource = $expectedResource->getRowsHash();
        $resourceFound    = true;

        foreach ($expectedResource as $key => $val) {
            // Check if value is a boolean or a link to a file
            if (is_string($val)) {
                $val = $this->getOrCastValue($val);
            }

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
     * @return array<string|int, mixed>
     * @throws Exception
     */
    public function getLastResponseJsonDataAsArray(): array
    {
        $data = json_decode($this->lastResponseBody, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception(sprintf('Invalid json body: %s', $this->lastResponseBody));
        }

        return $data;
    }

    /**
     * @return stdClass
     * @throws Exception
     */
    public function getLastResponseJsonDataAsObject(): stdClass
    {
        $data = json_decode($this->lastResponseBody, false);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception(sprintf('Invalid json body: %s', $this->lastResponseBody));
        }

        return $data;
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
     * Return true if the given collection contains the expected resource, false otherwise. Optionally, the position
     * must match.
     *
     * @param stdClass[] $collection
     * @param TableNode $expectedResource
     * @param int|null $position
     * @return bool
     */
    protected function collectionContainsResource(
        array $collection,
        TableNode $expectedResource,
        ?int $position = null
    ): bool {
        if (is_null($position)) {
            foreach ($collection as $receivedResource) {
                if ($this->resourceMatch($expectedResource, $receivedResource)) {
                    return true;
                }
            }
        } elseif ($this->resourceMatch($expectedResource, $collection[$position - 1])) {
            return true;
        }

        return false;
    }
}
