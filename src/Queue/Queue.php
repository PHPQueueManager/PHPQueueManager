<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

class Queue implements QueueInterface
{

    protected AdapterInterface $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->adapter->connect();
    }

    public function __destruct()
    {
        $this->adapter->close();
    }

    public function getName(): string
    {
        $split = explode("\\", get_called_class());

        return strtolower($split[array_key_last($split)]);
    }

    public function getDLQName(): string
    {
        return $this->getName() . '_dlq';
    }

    public function publish(MessageInterface $message): bool
    {
        return $this->adapter
            ->queueDeclare($this)
            ->publish($message);
    }

    public function consume(\Closure $worker)
    {
        $this->adapter
            ->queueDeclare($this)
            ->consume($worker);
    }

}
