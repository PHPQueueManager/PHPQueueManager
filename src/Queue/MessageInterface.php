<?php

namespace PHPQueueManager\PHPQueueManager\Queue;

interface MessageInterface
{

    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * It defines the message content with the payload coming from the queue. It is not recommended to crush it.
     *
     * @param array $properties
     * @return void
     */
    public function __setProperties(array $properties): void;

    /**
     * @param string $json
     * @return self
     */
    public static function create(string $json): self;

    /**
     * Sets the payload content that the message will carry.
     *
     * @param array $data
     * @return self
     */
    public function setPayload(array $data): self;

    /**
     * Returns the payload content carried by the message.
     *
     * @return array
     */
    public function getPayload(): array;

    /**
     * If the message fails to be reprocessed for some reason, it allows you to catch it from your application.
     *
     * @return bool
     */
    public function retryNotification(): bool;

    /**
     * If the message fails for any reason, it allows you to catch it from your application.
     *
     * @return bool
     */
    public function deadLetterNotification(): bool;

}
