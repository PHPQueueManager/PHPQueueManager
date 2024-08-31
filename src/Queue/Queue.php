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

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        $this->adapter->close();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        $split = explode("\\", get_called_class());

        return strtolower($split[array_key_last($split)]);
    }

    /**
     * @inheritDoc
     */
    public function getDLQName(): string
    {
        return $this->getName() . '_dlq';
    }

    /**
     * @inheritDoc
     */
    public function publish(MessageInterface $message): bool
    {
        return $this->adapter
            ->queueDeclare($this)
            ->publish($message);
    }

    /**
     * @inheritDoc
     */
    public function consume(\Closure $worker): void
    {
        $this->adapter
            ->queueDeclare($this)
            ->consume($worker);
    }

}
