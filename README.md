BayWa r.e. Behat Contexts
=========================

[![CircleCI](https://circleci.com/gh/baywa-re-lusy/behat-contexts/tree/main.svg?style=svg)](https://circleci.com/gh/baywa-re-lusy/behat-contexts/tree/main)

This repository provides you with different Behat Contexts containing common test steps that can be reused across
different projects.

# Installation

Install the package via Composer:
```shell
$ composer require --dev lusy/behat-contexts
```

In your `behat.yml`, add the following:
```yml
default:
  ...
  suites:
    api_features:
      contexts:
        ...
        - BayWaReLusy\BehatContext\HalContext
        - BayWaReLusy\BehatContext\Auth0Context
        - BayWaReLusy\BehatContext\SqsContext
        - BayWaReLusy\BehatContext\ConsoleContext
        ...
```

# HalContext

A Context to parse & test API responses in HAL format:
- https://de.wikipedia.org/wiki/Hypertext_Application_Language
- https://stateless.group/hal_specification.html

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
        $this->getHalContext()
            ->setJsonFilesPath(<path to directory with JSON files>)
            ->setBaseUrl(<API Base URL>)
            ->setBearerToken(<API Bearer Token>);
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

You can add placeholders to your URL by writing:
```gherkin
When I send a "GET" request to "/resource-url/{MY_PLACEHOLDER}"
```

And add the corresponding value with:
```php
$this->getHalContext()->addPlaceholder('MY_PLACEHOLDER', '<placeholder value>');
```

# Auth0Context

A Context to login to Auth0 with Login/Password or as a Machine-to-Machine client.
The `HalContext` needs to be initialized first.

In your `FeatureContext`, add the following:
```php
use BayWaReLusy\BehatContext\Auth0Context\Auth0ContextAwareTrait;
use BayWaReLusy\BehatContext\Auth0Context\Auth0ContextAwareInterface;
use BayWaReLusy\BehatContext\Auth0Context\MachineToMachineCredentials;
use BayWaReLusy\BehatContext\Auth0Context\UserCredentials;

class FeatureContext implements
    ...
    Auth0ContextAwareInterface
    ...
{
    use Auth0ContextAwareTrait;
    
    ...
    
    /**
     * @BeforeScenario
     */
    public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope)
    {
        ...
        $this->gatherAuth0Context($scope);
        $this->getAuth0Context()
            ->setHalContext($this->getHalContext())
            ->setAuth0Audience(<Auth0 audience>)
            ->setAuth0TokenEndpoint(<Auth0 Token Endpoint URL>)
            ->addMachineToMachineCredentials(new MachineToMachineCredentials(
                '<Client name describing the client>',
                '<Auth0 Client ID>',
                '<Auth0 Client Secret>'
            ))
            ->addUserCredentials(new UserCredentials(
                '<User Login>',
                '<User Password>',
                '<Auth0 Client ID>'
            ));
        ...
    }
}
```

# AuthContext

A Context to login to a generic Auth Server (OpenID Connect/OAuth2) with Login/Password or as a Machine-to-Machine client.
The `HalContext` needs to be initialized first.

In your `FeatureContext`, add the following:
```php
use BayWaReLusy\BehatContext\AuthContext\AuthContextAwareTrait;
use BayWaReLusy\BehatContext\AuthContext\AuthContextAwareInterface;
use BayWaReLusy\BehatContext\AuthContext\MachineToMachineCredentials;
use BayWaReLusy\BehatContext\AuthContext\UserCredentials;

class FeatureContext implements
    ...
    AuthContextAwareInterface
    ...
{
    use AuthContextAwareTrait;
    
    ...
    
    /**
     * @BeforeScenario
     */
    public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope)
    {
        ...
        $this->gatherAuthContext($scope);
        $this->getAuthContext()
            ->setHalContext($this->getHalContext())
            ->setServerAddress(<Auth Server address>)
            ->setTokenEndpoint(<Auth Server Token endpoint>)
            ->setTokenEndpoint(<Auth Server Token endpoint>)
            ->addMachineToMachineCredentials(new MachineToMachineCredentials(
                '<Client name describing the client>',
                '<Auth Client ID>',
                '<Auth Client Secret>'
            ))
            ->addUserCredentials(new UserCredentials(
                '<User Login>',
                '<User Password>',
                '<Auth Client ID>'
            ));
        ...
    }
}
```

# SqsContext

A Context to use AWS SQS compatible queues like e.g. ElasticMQ

In your `FeatureContext`, add the following:
```php
use BayWaReLusy\BehatContext\SqsContext\SqsContextAwareTrait;
use BayWaReLusy\BehatContext\SqsContext\SqsContextAwareInterface;
use BayWaReLusy\BehatContext\SqsContext\QueueUrl;

class FeatureContext implements
    ...
    SqsContextAwareInterface
    ...
{
    use SqsContextAwareTrait;
    
    ...
    
    /**
     * @BeforeScenario
     */
    public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope)
    {
        ...
        $queueService = ... // <== instance of BayWaReLusy\QueueTools\QueueService
        
        $this->gatherSqsContext($scope);
        $this->getSqsContext()
            ->setQueueService($queueService)
            ->setAwsRegion(<AWS Region>)
            ->setAwsKey(<AWS Key>)
            ->setAwsSecret(<AWS Secret>)
            ->addQueue(new QueueUrl('queueName', $queueUrl));
        ...
    }
}
```

To clear the queues before each Scenario, use the following code:
```php
/**
 * @BeforeScenario
 */
public function clearAllQueues(): void
{
    // Clear all queues
    $this->sqsContext->clearAllQueues();
}
```

# ConsoleContext

A Context containing steps to test console routes

In your `FeatureContext`, add the following:
```php
use BayWaReLusy\BehatContext\ConsoleContext\ConsoleContextAwareTrait;
use BayWaReLusy\BehatContext\ConsoleContext\ConsoleContextAwareInterface;

class FeatureContext implements
    ...
    ConsoleContextAwareInterface
    ...
{
    use ConsoleContextAwareTrait;
    
    ...
    
    /**
     * @BeforeScenario
     */
    public function gatherContexts(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope)
    {
        ...
        $this->gatherConsoleContext($scope);
        ...
    }
}
```
