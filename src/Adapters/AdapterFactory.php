<?php
namespace PHPQueueManager\PHPQueueManager\Adapters;

class AdapterFactory
{

    protected const MAPS = [
        'rabbitmq'          => "\\PHPQueueManager\\PHPQueueManager\\Adapters\\RabbitMQ\\RabbitMQAdapter",
        'kafka'             => "\\PHPQueueManager\\PHPQueueManager\\Adapters\\Kafka\\KafkaAdapter",
    ];

    /**
     * @param string $adapter
     * @param array $credentials
     * @return AdapterInterface
     * @throws \Exception
     */
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
