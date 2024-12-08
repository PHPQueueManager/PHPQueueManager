<?php

namespace PHPQueueManager\PHPQueueManager\Adapters\Kafka;

use PHPQueueManager\PHPQueueManager\Adapters\AbstractAdapter;
use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Exceptions\DeadLetterQueueException;
use PHPQueueManager\PHPQueueManager\Exceptions\ReTryQueueException;
use PHPQueueManager\PHPQueueManager\Queue\Message;
use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;
use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\KafkaConsumer;

class KafkaAdapter extends AbstractAdapter implements AdapterInterface
{

    protected Conf $conf;

    /**
     * @inheritDoc
     */
    public function __construct(array $credentials)
    {
        parent::__construct(array_merge([
            'bootstrap.servers'         => 'localhost:9092',
            'group.id'                  => 'PQMConsumerGroup',
            'metadata.broker.list'      => 'localhost:9092',
        ], $credentials));
    }

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        try {
            $this->conf = new Conf();
            foreach ($this->credentials as $name => $value) {
                $this->conf->set($name, $value);
            }

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
        !isset($this->conf) && $this->connect();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function publish(MessageInterface $message): bool
    {
        try {
            $producer = new Producer($this->conf);
            $topic = $producer->newTopic($this->queue->getName());
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message->__toString());
            $producer->poll(0);

            while ($producer->getOutQLen() > 0) {
                $producer->poll(50);
            }

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
            $consumer = new KafkaConsumer($this->conf);
            $consumer->subscribe([$this->queue->getName()]);

            while (true) {
                $msg = $consumer->consume(120 * 1000);
                if ($msg->err == RD_KAFKA_RESP_ERR_NO_ERROR) {

                    $message = Message::create($msg->payload);
                    try {
                        $res = $this->messageWork($message, $worker);
                        if (!$res && $message->try < $message->attempt) {
                            $this->retry($message);
                        }
                    } catch (ReTryQueueException $e) {
                        $message->error = $e->getMessage();
                        $this->retry($message);
                        $this->queue->report($e);
                    } catch (DeadLetterQueueException $e) {
                        $message->error = $e->getMessage();
                        $this->addDeadLetterQueue($message);
                        $this->queue->report($e);
                    } catch (\Throwable $e) {
                        $message->error = $e->getMessage();
                        if ($message->try < $message->attempt) {
                            $this->retry($message);
                        } else {
                            $this->addDeadLetterQueue($message);
                        }
                        $this->queue->report($e);
                    }
                }

                if ($msg->err == RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                    // There is no Message to Receive!
                    continue;
                }

                if ($msg->err == RD_KAFKA_RESP_ERR__TIMED_OUT) {
                    // Message reception timed out
                    continue;
                }

                throw new \Exception($msg->errstr(), $msg->err);
            }
        }catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
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
                $producer = new Producer($this->conf);
                $topic = $producer->newTopic($deadLetterQueue);
                $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message->__toString());
                $producer->poll(0);

                while ($producer->getOutQLen() > 0) {
                    $producer->poll(50);
                }
            }
        } catch (\Throwable $e) {
        }
    }

}
