<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;

#[\AllowDynamicProperties]
class Message implements MessageInterface
{

    protected array $data = [
        'payload'       => [],
        'ttl'           => null,
        'attempt'       => 1,
        'attempt_at'    => null,
        'try'           => 0,
        'created_at'    => null,
    ];

    public function __construct()
    {
        $this->data['created_at'] = date("c");
    }

    public function __toString(): string
    {
        return json_encode($this->data);
    }

    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public static function create(string $json): self
    {
        $message = new self();
        $message->data = json_decode($json, true);

        return $message;
    }

    public function setPayload(array $data): self
    {
        $this->data['payload'] = $data;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->data['payload'] ?? [];
    }
}