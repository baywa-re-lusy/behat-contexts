<?php

namespace BayWaReLusy\BehatContext;

use Behat\Gherkin\Node\TableNode;
use Exception;
use stdClass;

class HalContext extends AbstractApiResponseContext
{
    /**
     * @Then response should be an ApiProblem
     * @Then response should be an ApiProblem with error message :errorMessage
     */
    public function responseShouldBeAnApiProblem(?string $expectedErrorMessage = null): void
    {
        $contentType = $this->getLastResponse()->getHeader('Content-Type');

        if ('application/problem+json' !== $contentType[0]) {
            throw new \Exception(sprintf('Expected ApiProblem content type, but got %s.', $contentType[0]));
        }

        $response = $this->getLastResponseJsonDataAsArray();

        if (!is_null($expectedErrorMessage) && $expectedErrorMessage !== $response['detail']) {
            throw new \Exception('Expected a different error message.');
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

        $errors = $this->getLastResponseJsonDataAsArray();

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
        $response = $this->getLastResponseJsonDataAsObject();

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
        $response = $this->getLastResponseJsonDataAsObject();

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
        $response = $this->getLastResponseJsonDataAsObject();

        $collection = $response->_embedded->$collectionName;

        if ($this->collectionContainsResource($collection, $expectedResource)) {
            throw new \Exception(sprintf(
                "Resource shouldn't have been found -> %s : %s",
                $expectedResource->getRow(0)[0],
                $expectedResource->getRow(0)[1],
            ));
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
        $response = $this->getLastResponseJsonDataAsObject();

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
     * @Then response collection entry with ID :mainCollectionEntryId in collection :mainCollectionName should contain an embedded collection of :number :subCollectionName with the following entries:
     */
    public function responseCollectionEntryWithIdShouldContainAnEmbeddedCollectionOfWithTheFollowingEntries(
        string $mainCollectionName,
        string $mainCollectionEntryId,
        string $number,
        string $subCollectionName,
        TableNode $expectedCollectionEntries
    ): void {
        $response = $this->getLastResponseJsonDataAsObject();

        // --- Find the main entry by ID ---
        $mainCollectionEntry = null;
        foreach ($response->_embedded->$mainCollectionName as $entry) {
            if ($entry->id === $mainCollectionEntryId) {
                $mainCollectionEntry = $entry;
                break;
            }
        }

        if ($mainCollectionEntry === null) {
            throw new \RuntimeException("Entry with id $mainCollectionEntryId not found in $mainCollectionName");
        }

        $subCollection = $mainCollectionEntry->_embedded->$subCollectionName ?? [];

        if (!is_iterable($subCollection)) {
            throw new \RuntimeException("Sub collection '$subCollectionName' not found or not iterable.");
        }

        // --- Match each expected row against at least one entry in the sub collection ---
        foreach ($expectedCollectionEntries->getHash() as $expectedRow) {
            $found = false;

            foreach ($subCollection as $collectionEntry) {
                $matches = true;
                foreach ($expectedRow as $field => $expectedValue) {
                    $actualValue = $collectionEntry->$field ?? null;
                    if ($actualValue != $expectedValue) { // loose compare: "123" == 123
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new \RuntimeException(
                    'No entry found in sub-collection matching: ' . json_encode($expectedRow, JSON_UNESCAPED_SLASHES)
                );
            }
        }

        // --- Verify total count ---
        if (iterator_count($subCollection) !== (int)$number) {
            throw new \RuntimeException(sprintf(
                "Sub-collection '%s' contains %d elements instead of expected %d",
                $subCollectionName,
                iterator_count($subCollection),
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
        $response = $this->getLastResponseJsonDataAsObject();

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
        $response = $this->getLastResponseJsonDataAsObject();

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
        $response = $this->getLastResponseJsonDataAsObject();

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

    /**
     * @Then response should contain the following entries:
     */
    public function responseShouldContainTheFollowingEntry(TableNode $entries): void
    {
        $entries  = $entries->getRowsHash();
        $response = $this->getLastResponseJsonDataAsArray();

        foreach ($entries as $path => $value) {
            $searchResult = (string)\JmesPath\Env::search($path, $response);

            if ($searchResult !== $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                throw new \Exception(sprintf("Entry '%s' not found or didn't match value '%s'.", $path, $value));
            }
        }
    }

    protected function resourceMatch(TableNode $expectedResource, stdClass $receivedResource): bool
    {
        $expectedResource = $expectedResource->getRowsHash();

        foreach ($expectedResource as $key => $val) {
            if (is_string($val)) {
                $val = $this->getOrCastValue($val);
            }

            // direct property match
            if (property_exists($receivedResource, $key)) {
                try {
                    $this->assertMatchesSubset(
                        $val,
                        $receivedResource->$key,
                        $key
                    );
                    continue;
                } catch (\RuntimeException) {
                    // fall through to embedded check
                }
            }
            // embedded resource match (HAL-style)
            if (
                property_exists($receivedResource, '_embedded') &&
                property_exists($receivedResource->_embedded, $key)
            ) {
                $embedded = $receivedResource->_embedded->$key;

                // common HAL case: compare against embedded.id
                if (is_object($embedded) && property_exists($embedded, 'id')) {
                    try {
                        $this->assertMatchesSubset(
                            $val,
                            $embedded->id,
                            "_embedded.$key.id"
                        );
                        continue;
                    } catch (\RuntimeException) {
                        // fall through
                    }
                }
            }

            // nothing matched
            return false;
        }

        return true;
    }
}
