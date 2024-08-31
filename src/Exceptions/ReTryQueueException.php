<?php
namespace PHPQueueManager\PHPQueueManager\Exceptions;

/**
 * You can throw this error when a temporary error occurs while processing a message and you want the message to be queued for retry.
 */
class ReTryQueueException extends \Exception
{
}
