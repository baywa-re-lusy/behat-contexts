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
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

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
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

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
        /** @var stdClass $response */
        $response = $this->getLastResponseJsonData();

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
                'Content-Type' => 'application/json',
            ];

        $this->sendRequestWithJsonBody($method, $url, $headers, $body);
    }
}
