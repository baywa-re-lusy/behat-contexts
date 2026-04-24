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

        if (!str_starts_with($contentType[0], 'application/problem+json')) {
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
        $response = $this->getLastResponseJsonDataAsObject();

        if (count($response->member) !== (int)$nbEntries) {
            throw new \Exception("The entry count doesn't match: " . count($response->member));
        }
    }

    /**
     * @Then the response collection should contain the resource:
     * @Then the response collection should contain the resource on position :position:
     */
    public function theResponseCollectionShouldContainTheResource(
        TableNode $expectedResource,
        ?int $position = null
    ): void {
        $response = $this->getLastResponseJsonDataAsObject();

        if (!$this->collectionContainsResource($response->member, $expectedResource, $position)) {
            throw new \Exception("Resource should have been found.");
        }
    }

    /**
     * @Then the response collection should not contain the resource:
     * @throws Exception
     */
    public function theResponseCollectionShouldNotContainTheResource(TableNode $expectedResource): void
    {
        $response = $this->getLastResponseJsonDataAsObject();

        if ($this->collectionContainsResource($response->member, $expectedResource)) {
            throw new \Exception("Resource shouldn't have been found.");
        }
    }

    /**
     * @inheritDoc
     */
    public function iSendARequestToWithJsonBody(string $method, string $url, ?string $body = null): void
    {
        $headers =
            [
                'Accept'       => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ];

        $this->sendRequestWithJsonBody($method, $url, $headers, $body);
    }

    protected function resourceMatch(TableNode $expectedResource, stdClass $receivedResource): bool
    {
        $expectedResource = $expectedResource->getRowsHash();

        foreach ($expectedResource as $key => $val) {
            if (is_string($val)) {
                $val = $this->getOrCastValue($val);
            }

            $searchResult = (string)\JmesPath\Env::search($key, $receivedResource);
            error_log($searchResult);
            if ($searchResult !== $val) {
                if (is_array($val)) {
                    $value = json_encode($val);
                }
                throw new \Exception(sprintf("Entry '%s' not found or didn't match value '%s'.", $key, $value));
            }
            return true;
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
            if (property_exists($receivedResource, $key)) {
                //(JSON LD style)
                $resource = $receivedResource->$key;
                if (is_object($resource) && property_exists($resource, 'id')) {
                    try {
                        $this->assertMatchesSubset(
                            $val,
                            $resource->id,
                            "$key.id"
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
