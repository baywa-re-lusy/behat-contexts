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

    protected ?string $awsRegion = null;
    protected ?string $awsKey = null;
    protected ?string $awsSecret = null;

    /** @var array<string, array<string, array<string, string>>> Queue messages */
    protected array $queueMessages = [];

    /** @var QueueService */
    protected QueueService $queueService;

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

        // Create SQS client
        $sqsClient = new SqsClient([
            'version'     => '2012-11-05',
            'region'      => $this->awsRegion,
            'credentials' =>
                [
                    'key'    => $this->awsKey,
                    'secret' => $this->awsSecret,
                ]
        ]);

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
