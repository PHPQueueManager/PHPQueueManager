<?php

namespace PHPQueueManager\PHPQueueManager\Adapters;

use PHPQueueManager\PHPQueueManager\Adapters\Kafka\KafkaAdapter;
use PHPQueueManager\PHPQueueManager\Adapters\RabbitMQ\RabbitMQAdapter;

class AdapterFactory
{

    protected const MAPS = [
        'rabbitmq'          => RabbitMQAdapter::class,
        'kafka'             => KafkaAdapter::class,
    ];

    public static function create(string $adapter, array $credentials): AdapterInterface
    {
        if (isset(self::MAPS[strtolower($adapter)])) {
            $adapter = self::MAPS[strtolower($adapter)];
        }
        if (!class_exists($adapter)) {
            throw new \Exception('"' . $adapter . '" not found!');
        }
        $adapterObject = new $adapter($credentials);
        if (!($adapterObject instanceof AdapterInterface)) {
            throw new \Exception('"' . $adapter . '" does not implement "' . AdapterInterface::class . '"');
        }

        return $adapterObject;
    }

}
