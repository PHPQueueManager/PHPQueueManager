<?php

namespace PHPQueueManager\PHPQueueManager\Adapters\PDO;

use PHPQueueManager\PHPQueueManager\Adapters\AbstractAdapter;
use PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface;
use PHPQueueManager\PHPQueueManager\Exceptions\DeadLetterQueueException;
use PHPQueueManager\PHPQueueManager\Exceptions\ReTryQueueException;
use PHPQueueManager\PHPQueueManager\Queue\Message;
use PHPQueueManager\PHPQueueManager\Queue\MessageInterface;
use PHPQueueManager\PHPQueueManager\Queue\QueueInterface;

class PDOAdapter extends AbstractAdapter implements AdapterInterface
{

    /** @var \PDO */
    protected $pdo;

    protected string $tableName = 'queue';

    protected string $failTableName = 'fail_queue';

    public function __construct(array $credentials)
    {
        if (!class_exists("PDO")) {
            throw new \Exception("PDO extensions are not installed or active on the server");
        }
        parent::__construct(array_merge([
            'driver'        => 'mysql',
            'host'          => '127.0.0.1',
            'port'          => 3306,
            'dbname'        => 'test',
            'charset'       => 'utf8mb4',
            'username'      => 'root',
            'password'      => 'root',
        ], $credentials));
    }


    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        try {
            !isset($this->credentials['dsn']) && $this->credentials['dsn'] = $this->getDSN();

            $this->pdo = new \PDO($this->credentials['dsn'], $this->credentials['username'], $this->credentials['password']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function queueDeclare(QueueInterface $queue): \PHPQueueManager\PHPQueueManager\Adapters\AdapterInterface
    {
        !isset($this->pdo) && $this->connect();

        if (!isset($this->queue) || $queue !== $this->queue) {
            $this->queue = $queue;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function publish(MessageInterface $message): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO " . $this->tableName . " (message, ttl, attempt, attempt_at, try, created_at, status) VALUES (:message, :ttl, :attempt, :attempt_at, :try, :created_at, :status)");

            return $stmt->execute([
                ':message'              => $message->__toString(),
                ':ttl'                  => $message->ttl,
                ':attempt'              => $message->attempt,
                ':attempt_at'           => $message->attempt_at,
                ':try'                  => $message->try,
                ':created_at'           => $message->created_at,
                ':status'               => 0,
            ]);
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
            while (true) {
                $this->pdo->beginTransaction();

                $stmt = $this->pdo->prepare("SELECT * FROM " . $this->tableName . " WHERE status = '0' ORDER BY attempt ASC, id ASC LIMIT 1 FOR UPDATE");
                $stmt->execute();

                $msg = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$msg) {
                    $this->pdo->rollBack();
                    $this->sleep(0.5);
                    continue;
                }

                $updateStmt = $this->pdo->prepare("UPDATE " . $this->tableName . " SET status = '1' WHERE id = :id");
                $updateStmt->execute([
                    ':id'       => $msg['id'],
                ]);

                $this->pdo->commit();

                $message = Message::create($msg['message']);
                $message->id = $msg['id'];
                try {
                    $res = $this->messageWork($message, $worker);
                    if ($res) {
                        $stmt = $this->pdo->prepare("DELETE FROM " . $this->tableName . " WHERE id = :id");
                        $stmt->execute([
                            ':id'           => $msg['id'],
                        ]);
                        continue;
                    }

                    throw new \Exception("Worker failed!");

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
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        $this->pdo = null;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function retry(MessageInterface $message): void
    {
        $message->retryNotification();

        $stmt = $this->pdo->prepare();

        $stmt = $this->pdo->prepare("UPDATE " . $this->tableName . " SET status = '0', attempt = :attempt, attempt_at = :attempt_at  WHERE id = :id");
        $stmt->execute([
            ':id'           => $message->id,
            ':attempt'      => $message->attempt,
            ':attempt_at'   => $message->attempt_at,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function addDeadLetterQueue(MessageInterface $message): void
    {
        try {
            $message->deadLetterNotification();

            $stmt = $this->pdo->prepare("DELETE FROM " . $this->tableName . " WHERE id = :id");
            $stmt->execute([
                ':id'       => $message->id,
            ]);

            $stmt = $this->pdo->prepare("INSERT INTO " . $this->failTableName . " (message, error, ttl, attempt, attempt_at, try, created_at) VALUES (:message, :error, :ttl, :attempt, :attempt_at, :try, :created_at)");
            $stmt->execute([
                ':message'              => $message->__toString(),
                ':error'                => $message->error,
                ':ttl'                  => $message->ttl,
                ':attempt'              => $message->attempt,
                ':attempt_at'           => $message->attempt_at,
                ':try'                  => $message->try,
                ':created_at'           => $message->created_at,
            ]);
        } catch (\Throwable $e) {
        }
    }

    protected function getDSN(): string
    {
        return $this->credentials['driver'] . ':host=' . $this->credentials['host']
            . ';port=' . $this->credentials['port']
            . ';dbname=' . $this->credentials['dbname']
            . ';charset=' . $this->credentials['charset'];
    }

    protected function sleep($second)
    {
        usleep($second * 1000000);
    }

}
