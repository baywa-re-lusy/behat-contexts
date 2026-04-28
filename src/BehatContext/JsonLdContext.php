<?php

namespace BayWaReLusy\BehatContext;

use Behat\Transformation\Transform;
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
            $searchResult = (string) \JmesPath\Env::search($key, $receivedResource);
            if ($searchResult !== $val) {
                if (is_array($val)) {
                    $val = json_encode($val);
                }
                return false;
            }
        }

        return true;
    }
}
