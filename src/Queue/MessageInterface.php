<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

interface MessageInterface
{

    public function __toString(): string;

    public static function create(string $json): self;

    public function setPayload(array $data): self;

    public function getPayload(): array;

}
