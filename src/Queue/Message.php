<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;

use function date;
use function get_called_class;
use function json_decode;
use function json_encode;
use function class_exists;
use function array_key_exists;

/**
 * @property string $error
 * @property array $payload
 * @property null|int $ttl
 * @property int $attempt
 * @property string $attempt_at
 * @property int $try
 * @property string $created_at
 * @property string $class
 */
#[\AllowDynamicProperties]
class Message implements MessageInterface
{

    protected array $properties = [
        'payload'       => [],
        'ttl'           => null,
        'attempt'       => 1,
        'attempt_at'    => null,
        'try'           => 0,
        'created_at'    => null,
        'class'         => null,
        'error'         => null,
    ];

    public function __construct()
    {
        $this->properties['created_at'] = date("c");
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $this->properties['class'] = get_called_class();
        return json_encode($this->properties);
    }

    /**
     * @inheritDoc
     */
    public function __set(string $name, $value): void
    {
        array_key_exists($name, $this->properties) && $this->properties[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function __get(string $name)
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    /**
     * @inheritDoc
     */
    public function __setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * @inheritDoc
     */
    public static function create(string $json): self
    {
        $properties = json_decode($json, true);
        if (empty($properties['class'])) {
            $properties['class'] = Message::class;
        }
        if (!class_exists($properties['class'])) {
            throw new \Exception('"' . $properties['class'] . '" class not found!');
        }
        $message = new $properties['class']();
        if (!($message instanceof MessageInterface)) {
            throw new \Exception('"' . $properties['class'] . '" must implement MessageInterface!');
        }

        $message->__setProperties($properties);

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function setPayload(array $data): self
    {
        $this->properties['payload'] = $data;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): array
    {
        return $this->properties['payload'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function retryNotification(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deadLetterNotification(): bool
    {
        return true;
    }

}
