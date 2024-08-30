<?php

namespace PHPQueueManager\PHPQueueManager\Adapters;

use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

interface AdapterInterface
{

    public function __construct(array $credentials);

    public function connect(): bool;

    public function queueDeclare(QueueInterface $queue): self;

    public function publish(MessageInterface $message): bool;

    public function consume(\Closure $worker);

    public function close(): bool;

    public function retry(MessageInterface $message): void;

    public function addDeadLetterQueue(MessageInterface $message): void;

}
