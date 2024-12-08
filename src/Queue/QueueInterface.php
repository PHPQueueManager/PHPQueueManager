<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

interface QueueInterface
{

    /**
     * Gives the name of the queue or topic where messages will be processed and listened to.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * If an error occurs while processing a message, it defines the name of the queue or topic to which the message will be moved.
     *
     * @return string
     */
    public function getDLQName(): string;

    /**
     * Adds a message to the queue.
     *
     * @param MessageInterface $message
     * @return bool
     */
    public function publish(MessageInterface $message): bool;

    /**
     * Consumes messages from the queue.
     *
     * @param \Closure $worker
     * @return void
     */
    public function consume(\Closure $worker): void;

    /**
     * @param \Throwable $exception
     * @return void
     */
    public function report(\Throwable $exception): void;

}
