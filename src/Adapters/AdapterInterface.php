<?php

namespace PHPQueueManager\PHPQueueManager\Adapters;

use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

interface AdapterInterface
{

    /**
     * @param array $credentials
     */
    public function __construct(array $credentials);

    /**
     * @return bool
     */
    public function connect(): bool;

    /**
     * @param QueueInterface $queue
     * @return self
     */
    public function queueDeclare(QueueInterface $queue): self;

    /**
     * @param MessageInterface $message
     * @return bool
     */
    public function publish(MessageInterface $message): bool;

    /**
     * @param \Closure $worker
     * @return mixed
     */
    public function consume(\Closure $worker);

    /**
     * @return bool
     */
    public function close(): bool;

    /**
     * @param MessageInterface $message
     * @return void
     */
    public function retry(MessageInterface $message): void;

    /**
     * @param MessageInterface $message
     * @return void
     */
    public function addDeadLetterQueue(MessageInterface $message): void;

}
