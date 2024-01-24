<?php

namespace BayWaReLusy\BehatContext;

use Aws\Sqs\SqsClient;
use BayWaReLusy\BehatContext\SqsContext\QueueUrl;
use BayWaReLusy\QueueTools\QueueService;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Exception;
use Ramsey\Uuid\Uuid;

class SqsContext implements Context
{
    /** @var QueueUrl[]  */
    protected array $queueUrls = [];

    protected ?string $awsRegion   = null;
    protected ?string $awsKey      = null;
    protected ?string $awsSecret   = null;
    protected ?string $sqsEndpoint = null;

    /** @var array<string, array<string, array<string, string>>> Queue messages */
    protected array $queueMessages = [];

    /** @var QueueService */
    protected QueueService $queueService;

    /**
     * Path to the directory with example JSON files.
     * @var string
     */
    protected string $jsonFilesPath;

    /**
     * @return QueueService
     */
    public function getQueueService(): QueueService
    {
        return $this->queueService;
    }

    /**
     * @param QueueService $queueService
     * @return SqsContext
     */
    public function setQueueService(QueueService $queueService): SqsContext
    {
        $this->queueService = $queueService;
        return $this;
    }

    /**
     * @param string $awsRegion
     * @return SqsContext
     */
    public function setAwsRegion(string $awsRegion): SqsContext
    {
        $this->awsRegion = $awsRegion;
        return $this;
    }

    /**
     * @param string $awsKey
     * @return SqsContext
     */
    public function setAwsKey(string $awsKey): SqsContext
    {
        $this->awsKey = $awsKey;
        return $this;
    }

    /**
     * @param string $awsSecret
     * @return SqsContext
     */
    public function setAwsSecret(string $awsSecret): SqsContext
    {
        $this->awsSecret = $awsSecret;
        return $this;
    }

    public function getSqsEndpoint(): ?string
    {
        return $this->sqsEndpoint;
    }

    public function setSqsEndpoint(?string $sqsEndpoint): SqsContext
    {
        $this->sqsEndpoint = $sqsEndpoint;
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
     * @return SqsContext
     */
    public function setJsonFilesPath(string $jsonFilesPath): SqsContext
    {
        $this->jsonFilesPath = $jsonFilesPath;
        return $this;
    }

    /**
     * Add a queue with its URL
     *
     * @param QueueUrl $queueUrl
     * @return SqsContext
     */
    public function addQueue(QueueUrl $queueUrl): SqsContext
    {
        $this->queueUrls[] = $queueUrl;
        return $this;
    }

    /**
     * Clear all registered queues.
     *
     * @return void
     * @throws Exception
     */
    public function clearAllQueues(): void
    {
        if (is_null($this->awsRegion) || is_null($this->awsKey) || is_null($this->awsSecret)) {
            throw new \Exception('AWS Credentials not set.');
        }

        $sqsOptions =
            [
                'version'     => '2012-11-05',
                'region'      => $this->awsRegion,
                'credentials' =>
                    [
                        'key'    => $this->awsKey,
                        'secret' => $this->awsSecret,
                    ]
            ];

        // Endpoint is only mandatory for non-AWS SQS-providers like ElasticMQ
        if (!is_null($this->getSqsEndpoint())) {
            $sqsOptions['endpoint'] = $this->getSqsEndpoint();
        }

        // Create SQS client
        $sqsClient = new SqsClient($sqsOptions);

        // Clear all queues
        foreach ($this->queueUrls as $queueUrl) {
            $sqsClient->purgeQueue(['QueueUrl' => $queueUrl->getQueueUrl()]);
        }
    }

    /**
     * @Given a message in queue :queueName:
     */
    public function aMessageInQueue(string $queueName, TableNode $message): void
    {
        $queueUrl = $this->getQueueUrl($queueName);

        $this->getQueueService()->sendMessage(
            $queueUrl->getQueueUrl(),
            (string)json_encode($message->getRowsHash()),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString()
        );
    }

    /**
     * @Given a message in queue :queueName with JSON content :jsonOrFileName
     */
    public function aMessageInQueueWithJsonContent(string $queueName, string $jsonOrFileName): void
    {
        $queueUrl = $this->getQueueUrl($queueName);

        if (str_starts_with($jsonOrFileName, 'file://')) {
            $fileName = $this->getJsonFilesPath() . DIRECTORY_SEPARATOR . str_replace('file://', '', $jsonOrFileName);
            $jsonOrFileName = file_get_contents($fileName);

            if (!$jsonOrFileName) {
                throw new Exception(sprintf("File %s not found.", $fileName));
            }
        }

        $this->getQueueService()->sendMessage(
            $queueUrl->getQueueUrl(),
            $jsonOrFileName,
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString()
        );
    }

    /**
     * @Then a message with the following content should have been queued in :queueName:
     * @throws Exception
     */
    public function aMessageWithTheFollowingContentShouldHaveBeenQueuedIn(
        string $queueName,
        TableNode $table,
        bool $exactMatchRequired = false
    ): void {
        $this->receiveMessagesFromQueue($queueName);

        foreach ($this->queueMessages[$queueName] as $messageContent) {
            if ($exactMatchRequired && count($messageContent) !== count($table->getRows())) {
                continue;
            }

            foreach ($table->getRows() as $row) {
                if ($row[1] === 'false') {
                    $row[1] = false;
                } elseif ($row[1] === 'true') {
                    $row[1] = true;
                }

                if ($messageContent[$row[0]] != $row[1]) {
                    continue 2;
                }
            }

            return;
        }

        throw new Exception(sprintf("Message not found in queue '%s'.", $queueName));
    }

    /**
     * @Then a message with exactly the following content should have been queued in :queueName:
     * @Then a message with exactly the following content should be in the queue :queueName:
     * @throws Exception
     */
    public function aMessageWithExactlyTheFollowingContentShouldHaveBeenQueuedIn(
        string $queueName,
        TableNode $table
    ): void {
        $this->aMessageWithTheFollowingContentShouldHaveBeenQueuedIn($queueName, $table, true);
    }

    /**
     * @Then a message with the following content shouldn't have been queued in :queueName:
     * @Then a message with the following content should no longer be in queue :queueName:
     * @throws Exception
     */
    public function aMessageWithTheFollowingContentShouldntHaveBeenQueuedIn(string $queueName, TableNode $table): void
    {
        $this->receiveMessagesFromQueue($queueName);

        foreach ($this->queueMessages[$queueName] as $messageContent) {
            foreach ($table->getRows() as $row) {
                if ($messageContent[$row[0]] != $row[1]) {
                    continue 2;
                }
            }

            throw new \Exception(sprintf("Message should not have been found in queue '%s'.", $queueName));
        }
    }

    /**
     * @Then a message with exactly the following JSON path content should have been queued in :queueName:
     * @Then a message with exactly the following JSON path content should be in queue :queueName:
     */
    public function aMessageWithExactlyTheFollowingJSONPathContentShouldHaveBeenQueuedIn(
        string $queueName,
        TableNode $table
    ): void {
        $this->receiveMessagesFromQueue($queueName);

        $validMessage = false;
        foreach ($this->queueMessages[$queueName] as $messageContent) {
            foreach ($table->getRows() as $row) {
                $searchResult = (string) \JmesPath\Env::search($row[0], $messageContent);
                if ($searchResult != $row[1]) {
                    continue 2;
                }
            }

            $validMessage = true;
        }

        if (!$validMessage) {
            throw new \Exception(sprintf("Message should not have been found in queue '%s'.", $queueName));
        }
    }

    /**
     * @Then a message with content :content should have been queued in :queueName
     * @Then a message with content :content should be in queue :queueName
     */
    public function aMessageWithContentShouldHaveBeenQueuedIn(
        string $content,
        string $queueName,
    ): void {
        $this->receiveMessagesFromQueue($queueName);

        if (str_starts_with($content, 'file://')) {
            $fileName = $this->getJsonFilesPath() . DIRECTORY_SEPARATOR . str_replace('file://', '', $content);
            $content = file_get_contents($fileName);

            if (!$content) {
                throw new Exception(sprintf("File %s not found.", $fileName));
            }

            if (str_ends_with($fileName, '.json')) {
                $content = json_decode($content, true);
            }
        }

        $messageFound = false;
        foreach ($this->queueMessages[$queueName] as $messageContent) {
            if ($content === $messageContent) {
                $messageFound = true;
                break;
            }
        }

        if (!$messageFound) {
            throw new \Exception(sprintf("Message should have been found in queue '%s'.", $queueName));
        }
    }

    /**
     * Read all messages from the given Queue.
     *
     * @param string $queueName
     * @return void
     * @throws Exception
     */
    protected function receiveMessagesFromQueue(string $queueName): void
    {
        $queueUrl = $this->getQueueUrl($queueName);

        if (!array_key_exists($queueName, $this->queueMessages)) {
            $this->queueMessages[$queueName] = [];
        }

        while ($message = $this->getQueueService()->receiveMessage($queueUrl->getQueueUrl())) {
            $this->queueMessages[$queueName][$message->getReceiptHandle()] = json_decode($message->getBody(), true);
            $this->getQueueService()->deleteMessage($queueUrl->getQueueUrl(), $message);
        }
    }

    /**
     * @throws Exception
     */
    protected function getQueueUrl(string $queueName): QueueUrl
    {
        foreach ($this->queueUrls as $queueUrl) {
            if ($queueUrl->getQueueName() === $queueName) {
                return $queueUrl;
            }
        }

        throw new Exception(sprintf("No Queue found with name '%s'", $queueName));
    }
}
