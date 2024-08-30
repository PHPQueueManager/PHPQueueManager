<?php

namespace PHPQueueManager\PHPQueueManager\Adapters\RabbitMQ;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Exceptions\DeadLetterQueueException;
use PHPQueueManager\PHPQueueManager\Exceptions\ReTryQueueException;
use PHPQueueManager\PHPQueueManager\Queue\Message;
use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

class RabbitMQAdapter implements AdapterInterface
{

    protected array $credentials = [
        'host'          => 'localhost',
        'port'          => 5672,
        'username'      => 'guest',
        'password'      => 'guest',
    ];

    protected AMQPStreamConnection $connection;

    protected AbstractChannel|AMQPChannel $channel;

    private QueueInterface $queue;

    public function __construct(array $credentials)
    {
        $this->credentials = array_merge($this->credentials, $credentials);
    }

    public function connect(): bool
    {
        try {
            $this->connection = new AMQPStreamConnection($this->credentials['host'], $this->credentials['port'], $this->credentials['username'], $this->credentials['password']);
            $this->channel = $this->connection->channel();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function queueDeclare(QueueInterface $queue): self
    {
        !isset($this->connection) && $this->connect();

        if (!isset($this->queue) || $queue !== $this->queue) {
            $this->queue = $queue;
            $this->channel->queue_declare($this->queue->getName(), false, true, false, false);
        }

        return $this;
    }

    public function publish(MessageInterface $message): bool
    {
        try {
            $msg = new AMQPMessage($message->__toString(), [
                'delivery_mode'     => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $this->channel->basic_publish($msg, '', $this->queue->getName());

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function consume(\Closure $worker)
    {
        try {
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume($this->queue->getName(), '', false, false, false, false, function ($msg) use ($worker) {
                $message = Message::create($msg->body);
                try {
                    if (!empty($message->ttl) && strtotime($message->ttl) > time()) {
                        throw new DeadLetterQueueException("It was not processed because the last processing date has passed!");
                    }
                    $message->try = $message->try + 1;
                    $message->attempt_at = date("c");

                    $res = call_user_func_array($worker, [
                        $message,
                    ]);

                    $res
                        ? $this->channel->basic_ack($msg->delivery_info['delivery_tag'])
                        : $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, $message->try < $message->attempt);
                } catch (ReTryQueueException $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    $this->retry($message);
                } catch (DeadLetterQueueException $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    $this->addDeadLetterQueue($message);
                } catch (\Throwable $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    if ($message->try < $message->attempt) {
                        $this->retry($message);
                    } else {
                        $this->addDeadLetterQueue($message);
                    }
                }
            });

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Throwable $e) {
        }
    }

    public function close(): bool
    {
        isset($this->channel) && $this->channel->close();
        isset($this->connection) && $this->connection->close();

        return true;
    }

    public function retry(MessageInterface $message): void
    {
        $this->publish($message);
    }

    public function addDeadLetterQueue(MessageInterface $message): void
    {
        try {
            $msg = new AMQPMessage($message->__toString(), [
                'delivery_mode'     => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $this->channel->basic_publish($msg, '', $this->queue->getDLQName());
        } catch (\Throwable $e) {
        }
    }

}
