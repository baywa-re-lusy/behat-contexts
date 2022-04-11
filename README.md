BayWa r.e. Behat Contexts
=========================

[![CircleCI](https://circleci.com/gh/baywa-re-lusy/behat-contexts/tree/main.svg?style=svg)](https://circleci.com/gh/baywa-re-lusy/behat-contexts/tree/main)

This repository provides you with different Behat Contexts containing common test steps that can be reused across
different projects.

## HalContext

A Context to parse & test API responses in HAL format:
- https://de.wikipedia.org/wiki/Hypertext_Application_Language
- https://stateless.group/hal_specification.html

### Installation

In your `FeatureContext`, add the following:

```php
use BayWaReLusy\BehatContext\HalContext\HalContextAwareTrait;
use BayWaReLusy\BehatContext\HalContext\HalContextAwareInterface;

class FeatureContext implements
    ...
    HalContextAwareInterface
    ...
{
    use HalContextAwareTrait;
    
    ...
    
    /**
     * @BeforeScenario
     */
    public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope)
    {
        ...
        $this->gatherHalContext($scope);
        $this->getHalContext()->setJsonFilesPath(<path to directory with JSON files>);
        ...
    }
}
```

And when you receive a reponse from your API, pass it to the context:

```php
/** @var Psr\Http\Message\ResponseInterface */
$apiResponse = ...

$this->getHalContext()->setLastResponse($apiResponse);
```
