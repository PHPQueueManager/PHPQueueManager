<?php
namespace PHPQueueManager\PHPQueueManager\Queue;

use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;

/**
 * It is used to define a different operating principle for a message type.
 */
interface JobMessageInterface extends MessageInterface
{

    /**
     * @param \PHPQueueManager\PHPQueueManager\Queue\MessageInterface $message
     * @return bool
     */
    public function worker(MessageInterface $message): bool;

}
