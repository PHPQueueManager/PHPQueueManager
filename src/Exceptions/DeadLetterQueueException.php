<?php
namespace PHPQueueManager\PHPQueueManager\Exceptions;

/**
 * You can raise this error when a permanent error occurs during processing of a message and you do not want the message to be retried.
 */
class DeadLetterQueueException extends \Exception
{
}
