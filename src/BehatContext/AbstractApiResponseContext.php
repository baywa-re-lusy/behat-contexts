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
     * List of [name, value] query string pairs to add to the request (in insertion order, duplicates allowed).
     * @var array<int, array{0: string, 1: string}>
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
     * @return array<int, array{0: string, 1: string}>
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
        $this->queryString[] = [$name, $value];
    }

    /**
     * Builds a raw query string, preserving duplicate keys (e.g. status[]=a&status[]=b),
     * which Guzzle's associative-array query building can't express.
     */
    protected function buildQueryString(): string
    {
        $parts = [];

        foreach ($this->queryString as [$name, $value]) {
            $parts[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        return implode('&', $parts);
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

        // Normalize booleans + decode JSON
        foreach ($expectedEntry as $key => &$value) {
            if (in_array($value, ['true', 'false'], true)) {
                $value = $value === 'true';
                continue;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }
        }

        foreach ($response as $entry) {
            try {
                foreach ($expectedEntry as $key => $expectedValue) {
                    if (!array_key_exists($key, $entry)) {
                        throw new \RuntimeException(sprintf("Missing key '%s'", $key));
                    }

                    $this->assertMatchesSubset(
                        $expectedValue,
                        $entry[$key],
                        $key
                    );
                }

                return;
            } catch (\RuntimeException $e) {
                continue;
            }
        }

        throw new Exception("Response array doesn't contain expected entry.");
    }

    protected function assertMatchesSubset(mixed $expected, mixed $actual, string $path): void
    {
        // UUID placeholder
        if ($expected === '<UUID>') {
            if (!Uuid::isValid((string)$actual)) {
                throw new \RuntimeException(sprintf("Expected UUID at '%s'", $path));
            }
            return;
        }

        // Normalize objects -> arrays for comparison
        if (is_object($actual)) {
            $actual = $this->objectToArray($actual);
        }

        if (is_object($expected)) {
            $expected = $this->objectToArray($expected);
        }

        // Scalar comparison
        if (!is_array($expected)) {
            if ($expected != $actual) {
                throw new \RuntimeException(sprintf(
                    "Mismatch at '%s': expected %s, got %s",
                    $path,
                    json_encode($expected),
                    json_encode($actual)
                ));
            }
            return;
        }

        // Expected is array -> actual must be array
        if (!is_array($actual)) {
            throw new \RuntimeException(sprintf("Expected array/object at '%s'", $path));
        }

        foreach ($expected as $key => $expectedValue) {
            if (!array_key_exists($key, $actual)) {
                throw new \RuntimeException(sprintf("Missing key '%s.%s'", $path, $key));
            }

            $this->assertMatchesSubset(
                $expectedValue,
                $actual[$key],
                $path . $key
            );
        }
    }

    /**
     * @param object $object
     * @return array<string, mixed>
     */
    private function objectToArray(object $object): array
    {
        if ($object instanceof \JsonSerializable) {
            return $object->jsonSerialize();
        }

        // stdClass or generic object
        return get_object_vars($object);
    }

    /**
     * @Then the response should be a JSON object containing:
     * @throws Exception
     */
    public function theResponseShouldBeAJsonObjectContaining(TableNode $expectedObject): void
    {
        /** @var string[] $response */
        $response = $this->getLastResponseJsonDataAsArray();

        foreach ($expectedObject->getRowsHash() as $key => $val) {
            if (is_string($val)) {
                $val = $this->getOrCastValue($val);
            }
            $searchResult = \JmesPath\Env::search($key, $response);

            if ($searchResult !== $val) {
                throw new Exception(sprintf("Key %s not found.", $val));
            }
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
            'query'       => $this->buildQueryString(),
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


    abstract protected function resourceMatch(TableNode $expectedResource, stdClass $receivedResource): bool;

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
     * @return string
     * @throws Exception
     */
    public function getLastResponseJsonDataRaw(): string
    {
        return $this->lastResponseBody;
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
        } elseif ($value === 'null') {
            $value = null;
        } elseif (is_numeric($value)) {
            $value = str_contains($value, '.') ? (float)$value : (int)$value;
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
