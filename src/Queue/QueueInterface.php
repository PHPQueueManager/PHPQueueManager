<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

interface QueueInterface
{

    public function getName(): string;

    public function getDLQName(): string;

    public function publish(MessageInterface $message): bool;

    public function consume(\Closure $worker);

}
