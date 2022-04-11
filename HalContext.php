<?php

use Behat\Behat\Context\Context;
use \GuzzleHttp\Psr7\Response as HttpResponse;
use Behat\Gherkin\Node\TableNode;

class FeatureContext implements Context
{
    protected ?HttpResponse $lastResponse = null;

    /**
     * @return HttpResponse|null
     */
    public function getLastResponse(): ?HttpResponse
    {
        return $this->lastResponse;
    }

    /**
     * @param HttpResponse|null $lastResponse
     * @return FeatureContext
     */
    public function setLastResponse(?HttpResponse $lastResponse): FeatureContext
    {
        $this->lastResponse = $lastResponse;
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
     * @Then the response status code should be :httpStatus
     * @throws Exception
     */
    public function theResponseStatusCodeShouldBe(string $httpStatus): void
    {
        if ((string)$this->getLastResponse()->getStatusCode() !== $httpStatus) {
            throw new \Exception(
                'HTTP code does not match ' . $httpStatus .
                ' (actual: ' . $this->getLastResponse()->getStatusCode() . ')' . PHP_EOL
                . $this->getLastResponse()->getBody()
            );
        }
    }

    /**
     * @Then the response should contain exactly :nbEntries :typeEntries
     */
    public function theResponseShouldContainExactly(string $nbEntries, string $typeEntries)
    {
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
    public function theResponseCollectionShouldContainTheResource($collectionName, TableNode $expectedResource): void
    {
        $collection = $this->getLastResponseJsonData()->_embedded->$collectionName;

        if (!$this->collectionContainsResource($collection, $expectedResource)) {
            throw new \Exception('Resource not found.');
        }
    }

    /**
     * @Then the response collection :collectionName should not contain the resource:
     * @throws Exception
     */
    public function theResponseCollectionShouldNotContainTheResource($collectionName, TableNode $expectedResource)
    {
        $collection = $this->getLastResponseJsonData()->_embedded->$collectionName;

        if ($this->collectionContainsResource($collection, $expectedResource)) {
            throw new \Exception("Resource shouldn't have been found.");
        }
    }

    /**
     * Return true if the given collection contains the expected resource, false otherwise.
     *
     * @param array $collection
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
                (!property_exists($receivedResource, '_embedded') ||
                    !property_exists($receivedResource->_embedded, $key) || $receivedResource->_embedded->$key->id != $val)
            ) {
                $resourceFound = false;
                break;
            }
        }

        return $resourceFound;
    }

    /**
     * @param bool $returnAsAssociativeArray
     * @return stdClass|array
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
     * @param $value
     * @return mixed
     */
    protected function getOrCastValue($value): mixed
    {
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (str_starts_with($value, 'file://')) {
            $value = file_get_contents(__DIR__ . '/../_files/' . substr($value, 7));
        }

        // Check if value is JSON
        $json = json_decode($value);
        if (json_last_error() === JSON_ERROR_NONE && !preg_match('/^\d+$/', $value)) {
            $value = $json;
        }

        return $value;
    }
}
