<?php
namespace PHPQueueManager\PHPQueueManager\Adapters\RabbitMQ;

use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPQueueManager\PHPQueueManager\Adapters\AbstractAdapter;
use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Exceptions\DeadLetterQueueException;
use PHPQueueManager\PHPQueueManager\Exceptions\ReTryQueueException;
use PHPQueueManager\PHPQueueManager\Queue\JobMessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\Message;
use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

class RabbitMQAdapter extends AbstractAdapter implements AdapterInterface
{

    protected AMQPStreamConnection $connection;

    protected AbstractChannel|AMQPChannel $channel;


    /**
     * @inheritDoc
     */
    public function __construct(array $credentials)
    {
        parent::__construct(array_merge([
            'host'          => 'localhost',
            'port'          => 5672,
            'username'      => 'guest',
            'password'      => 'guest',
        ], $credentials));
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function queueDeclare(QueueInterface $queue): self
    {
        !isset($this->connection) && $this->connect();

        if (!isset($this->queue) || $queue !== $this->queue) {
            $this->queue = $queue;
            $this->channel->queue_declare($this->queue->getName(), false, true, false, false);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function consume(\Closure $worker)
    {
        try {
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume($this->queue->getName(), '', false, false, false, false, function ($msg) use ($worker) {
                $message = Message::create($msg->body);
                try {
                    $res = $this->messageWork($message, $worker);

                    if ($res) {
                        $this->channel->basic_ack($msg->delivery_info['delivery_tag']);
                    } else {
                        $isRetry = $message->try < $message->attempt;
                        $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, $isRetry);

                        $isRetry && $message->retryNotification();
                    }
                } catch (ReTryQueueException $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    $this->retry($message);
                    $this->queue->report($e);
                } catch (DeadLetterQueueException $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    $this->addDeadLetterQueue($message);
                    $this->queue->report($e);
                } catch (\Throwable $e) {
                    $message->error = $e->getMessage();
                    $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
                    if ($message->try < $message->attempt) {
                        $this->retry($message);
                    } else {
                        $this->addDeadLetterQueue($message);
                    }
                    $this->queue->report($e);
                }
            });

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        isset($this->channel) && $this->channel->close();
        isset($this->connection) && $this->connection->close();

        return true;
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
                $msg = new AMQPMessage($message->__toString(), [
                    'delivery_mode'     => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]);

                $this->channel->basic_publish($msg, '', $deadLetterQueue);
            }

        } catch (\Throwable $e) {
        }
    }

}
