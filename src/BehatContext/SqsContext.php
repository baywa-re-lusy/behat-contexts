<?php

namespace BayWaReLusy\BehatContext;

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
    public function aMessageWithTheFollowingContentShouldHaveBeenQueuedIn(string $queueName, TableNode $table): void
    {
        $this->receiveMessagesFromQueue($queueName);

        foreach ($this->queueMessages[$queueName] as $messageContent) {
            foreach ($table->getRows() as $row) {
                if ($messageContent[$row[0]] != $row[1]) {
                    continue 2;
                }
            }

            return;
        }

        throw new Exception(sprintf("Message not found in queue '%s'.", $queueName));
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
