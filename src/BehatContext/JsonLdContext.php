<?php

namespace BayWaReLusy\BehatContext;

use GuzzleHttp\Exception\GuzzleException;
use Behat\Gherkin\Node\TableNode;
use Exception;
use stdClass;

class JsonLdContext extends AbstractApiResponseContext
{
    /**
     * @Then response should be a Hydra error
     */
    public function responseShouldBeAHydraError(): void
    {
        $contentType = $this->getLastResponse()->getHeader('Content-Type');

        if ('application/ld+json' !== $contentType[0]) {
            throw new \Exception(sprintf('Expected JSON-LD Hydra content type, but got %s.', $contentType[0]));
        }
    }

    public function errorMessageOnFieldShouldBe(string $expectedField, string $expectedErrorType): void
    {
        throw new \Exception('Not implemented yet.');
    }

    /**
     * @Then the response should contain exactly :nbEntries entries
     * @throws Exception
     */
    public function theResponseShouldContainExactlyEntries(string $nbEntries): void
    {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        if (count($response->member) !== (int)$nbEntries) {
            throw new \Exception("The entry count doesn't match: " . count($response->member));
        }
    }

    /**
     * @Then the response collection should contain the resource:
     * @Then the response collection should contain the resource on position :position:
     * @throws Exception
     */
    public function theResponseCollectionShouldContainTheResource(
        TableNode $expectedResource,
        ?int $position = null
    ): void {
        $this->findResourceInCollection('member', $expectedResource, $position);
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
     * @Then the resource :id in collection :collectionName should contain the embedded resource :embeddedResourceName:
     * @throws Exception
     */
    public function theResourceInCollectionShouldContainTheEmbeddedResource(
        string $id,
        string $collectionName,
        string $embeddedResourceName,
        TableNode $embeddedResource
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        if (!property_exists($response->_embedded, $collectionName)) {
            throw new \Exception("Response collection doesn't exist.");
        }

        foreach ($response->_embedded->$collectionName as $resource) {
            if ($resource->id === $id) {
                if (!property_exists($resource->_embedded, $embeddedResourceName)) {
                    throw new \Exception("Resource doesn't contain the expected embedded resource.");
                }

                foreach ($embeddedResource->getRowsHash() as $key => $value) {
                    if (
                        !property_exists($resource->_embedded->$embeddedResourceName, $key) ||
                        $resource->_embedded->$embeddedResourceName->$key != $value
                    ) {
                        throw new \Exception("Embedded Resource doesn't match the required values.");
                    }
                }

                return;
            }
        }

        throw new \Exception('Resource in response collection not found.');
    }

    /**
     * @When I send a :method request to :url
     * @When I send a :method request to :url with JSON body :body
     * @throws GuzzleException
     * @throws Exception
     */
    public function iSendARequestToWithJsonBody(string $method, string $url, ?string $body = null): void
    {
        // Replace placeholders in URL
        $url = $this->replacePlaceholdersInUrl($url);

        $headers =
            [
                'Accept'       => 'application/ld+json',
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
        if (!is_null($body)) {
            if (str_starts_with($body, 'file://')) {
                $body = file_get_contents($this->getJsonFilesPath() . DIRECTORY_SEPARATOR . substr($body, 7));
            }

            $params['body'] = $body;
        }

        $this->setLastResponse($this->getHttpClient()->request(strtoupper($method), $url, $params));
    }
}
