<?php

namespace BayWaReLusy\BehatContext\SqsContext;

class QueueUrl
{
    /**
     * @param string $queueName
     * @param string $queueUrl
     */
    public function __construct(
        protected string $queueName,
        protected string $queueUrl
    ) {
    }

    /**
     * @return string
     */
    public function getQueueName(): string
    {
        return $this->queueName;
    }

    /**
     * @return string
     */
    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }
}
