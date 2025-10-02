<?php

namespace BayWaReLusy\BehatContext;

use Behat\Gherkin\Node\TableNode;
use Exception;
use stdClass;

class HalContext extends AbstractApiResponseContext
{
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
     * @param string $expectedField
     * @param string $expectedErrorType
     * @return void
     * @throws Exception
     */
    public function errorMessageOnFieldShouldBe(string $expectedField, string $expectedErrorType): void
    {
        // Don't check anything if no specific erroneous field is expected in the response
        if (empty($expectedField) && empty($expectedErrorType)) {
            return;
        }

        $errors = json_decode($this->getLastResponse()->getBody()->getContents(), true);

        // Check if the response contains validation messages
        if (!array_key_exists('validation_messages', $errors)) {
            throw new \Exception("No validation messages found.");
        }

        // If the request was on a single resource, "validation_messages" is a hash table with
        // "fieldName => arrayOfMessages".
        // If the request was on a collection, "validation_messages" is a list of hash tables with
        // "fieldName => arrayOfMessages".
        try {
            if (!array_is_list($errors['validation_messages'])) {
                $this->validateErrorFieldAndType($errors['validation_messages'], $expectedField, $expectedErrorType);
            } else {
                foreach ($errors['validation_messages'] as $errorMessagesForResource) {
                    $this->validateErrorFieldAndType($errorMessagesForResource, $expectedField, $expectedErrorType);
                }
            }
        } catch (\Exception $e) {
            var_dump($errors);
            throw $e;
        }
    }

    /**
     * @param array<string, array<string, string>> $errorMessagesForResource
     * @param string $expectedField
     * @param string $expectedErrorType
     * @return void
     * @throws Exception
     */
    protected function validateErrorFieldAndType(
        array $errorMessagesForResource,
        string $expectedField,
        string $expectedErrorType
    ): void {
        if (count($errorMessagesForResource) > 1) {
            throw new \Exception(sprintf(
                "The input caused errors on more than one field : %s.",
                implode(' ,', array_keys($errorMessagesForResource))
            ));
        }

        if (array_key_first($errorMessagesForResource) !== $expectedField) {
            throw new \Exception(sprintf("The expected error field '%s' hasn't been found.", $expectedField));
        }

        if (count($errorMessagesForResource[$expectedField]) > 1) {
            throw new \Exception(sprintf(
                "The input caused more than one error on field '%s' => %s",
                $expectedField,
                implode(' ,', array_keys($errorMessagesForResource[$expectedField]))
            ));
        }

        if (array_key_first($errorMessagesForResource[$expectedField]) !== $expectedErrorType) {
            throw new \Exception(sprintf("The expected error type '%s' hasn't been found.", $expectedErrorType));
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
     * @Then the response collection :collectionName should contain the resource:
     * @Then the response collection :collectionName should contain the resource on position :position:
     * @throws Exception
     */
    public function theResponseCollectionShouldContainTheResource(
        string $collectionName,
        TableNode $expectedResource,
        ?int $position = null
    ): void {
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

        if (!$this->collectionContainsResource($response->_embedded->$collectionName, $expectedResource, $position)) {
            throw new \Exception("Resource should have been found.");
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
                throw new \Exception(
                    "$expectedCollectionKey => " . var_export($expectedCollectionValue, true) .
                    " not found in collection."
                );
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
     * @inheritDoc
     */
    public function iSendARequestToWithJsonBody(string $method, string $url, ?string $body = null): void
    {
        $headers =
            [
                'Accept'       => 'application/hal+json',
                'Content-Type' => 'application/json',
            ];

        $this->sendRequestWithJsonBody($method, $url, $headers, $body);
    }
}
