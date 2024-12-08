<?php

namespace PHPQueueManager\PHPQueueManager\Adapters;

use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Exceptions\DeadLetterQueueException;
use PHPQueueManager\PHPQueueManager\Queue\JobMessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

abstract class AbstractAdapter implements AdapterInterface
{

    protected array $credentials;

    /**
     * @var QueueInterface
     * @see self::queueDeclare()
     */
    protected QueueInterface $queue;

    /**
     * @inheritDoc
     */
    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @inheritDoc
     */
    abstract public function connect(): bool;

    /**
     * @inheritDoc
     */
    abstract public function queueDeclare(QueueInterface $queue): \PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;

    /**
     * @inheritDoc
     */
    abstract public function publish(MessageInterface $message): bool;

    /**
     * @inheritDoc
     */
    abstract public function consume(\Closure $worker);

    /**
     * @inheritDoc
     */
    abstract public function close(): bool;

    /**
     * @inheritDoc
     */
    abstract public function retry(MessageInterface $message): void;

    /**
     * @inheritDoc
     */
    abstract public function addDeadLetterQueue(MessageInterface $message): void;

    /**
     * @param MessageInterface $message
     * @param callable|\Closure $worker
     * @return bool
     * @throws DeadLetterQueueException
     */
    protected function messageWork(MessageInterface &$message, callable|\Closure $worker): bool
    {
        try {
            if (!empty($message->ttl) && strtotime($message->ttl) > time()) {
                throw new DeadLetterQueueException("It was not processed because the last processing date has passed!");
            }
            $message->try = $message->try + 1;
            $message->attempt_at = date("c");

            if ($message instanceof JobMessageInterface) {
                $worker = [$message, 'worker'];
            }

            return (bool)(call_user_func_array($worker, [
                $message,
            ]));
        } catch (\Throwable $e) {
            if ($message instanceof JobMessageInterface) {
                $message->report($e);
            }
            throw $e;
        }
    }

}