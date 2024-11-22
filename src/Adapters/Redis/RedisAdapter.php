<?php

namespace PHPQueueManager\PHPQueueManager\Adapters\Redis;

use \PHPQueueManager\PHPQueueManager\Adapters\{AbstractAdapter, AdapterInterface};
use \PHPQueueManager\PHPQueueManager\Exceptions\{DeadLetterQueueException, ReTryQueueException};
use \PHPQueueManager\PHPQueueManager\Queue\{Message, MessageInterface, QueueInterface};

class RedisAdapter extends AbstractAdapter implements AdapterInterface
{

    protected const GROUP_PREFIX = "grp";

    protected const STREAM_PREFIX = "str";

    protected const CONSUMER_PREFIX = "con";

    private \Redis $redis;

    private QueueInterface $queue;

    public function __construct(array $credentials)
    {
        parent::__construct(array_merge([
            'host'          => '127.0.0.1',
            'port'          => 6379,
        ], $credentials));
    }

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        try {
            $this->redis = new \Redis();
            $this->redis->connect($this->credentials['host'], $this->credentials['port']);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function queueDeclare(QueueInterface $queue): AdapterInterface
    {
        !isset($this->redis) && $this->connect();

        if (!isset($this->queue) || $queue !== $this->queue) {
            $this->queue = $queue;
            try {
                $stream = self::STREAM_PREFIX . $this->queue->getName();
                $group = self::GROUP_PREFIX . $this->queue->getName();
                $this->redis->xgroup('CREATE', $stream, $group, '$', true);
            } catch (\RedisException $e) {
                // Consumer Grup Zaten Mevcut
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function publish(MessageInterface $message): bool
    {
        try {
            $msg = [
                'message'       => $message->__toString(),
            ];

            $this->redis->xAdd($this->queue->getName(), '*', $msg);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function consume(\Closure $worker)
    {
        try {
            $group = self::GROUP_PREFIX . $this->queue->getName();
            $consumer = self::CONSUMER_PREFIX . $this->queue->getName();
            $stream = self::STREAM_PREFIX . $this->queue->getName();
            while (true) {
                $messages = $this->redis->xReadGroup($group, $consumer, [$stream => '>'], 1);
                if ($messages) {
                    foreach ($messages[$stream] as $id => $msg) {
                        $message = Message::create(json_encode($msg[0]['message']));
                        try {
                            $res = $this->messageWork($message, $worker);
                            if ($res) {
                                $this->redis->xAck($stream, $group, [$id]);
                            } else {
                                if ($message->try < $message->attempt) {
                                    throw new ReTryQueueException("The worker failed!");
                                }
                            }
                        } catch (ReTryQueueException $e) {
                            $message->error = $e->getMessage();
                            $this->redis->xAck($stream, $group, [$id]);
                            $this->retry($message);
                        } catch (DeadLetterQueueException $e) {
                            $message->error = $e->getMessage();
                            $this->redis->xAck($stream, $group, [$id]);
                            $this->addDeadLetterQueue($message);
                        } catch (\Throwable $e) {
                            $message->error = $e->getMessage();
                            $this->redis->xAck($stream, $group, [$id]);
                            if ($message->try < $message->attempt) {
                                $this->retry($message);
                            } else {
                                $this->addDeadLetterQueue($message);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return !isset($this->redis) || $this->redis->close();
    }

    /**
     * @inheritDoc
     */
    public function retry(MessageInterface $message): void
    {
        $message->retryNotification();
        $this->publish($message);
    }

    /**
     * @inheritDoc
     */
    public function addDeadLetterQueue(MessageInterface $message): void
    {
        try {
            $message->deadLetterNotification();

            $deadLetterQueue = $this->queue->getDLQName();
            if (!empty($deadLetterQueue)) {
                $msg = [
                    'message'       => $message->__toString(),
                ];

                $this->redis->xAdd($deadLetterQueue, '*', $msg);
            }

        } catch (\Throwable $e) {
        }
    }

}
