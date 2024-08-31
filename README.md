# PHP Queue Manager

This library was created to easily create and manage your own job queues. This library aims to abstract the queuing mechanisms and increase scalability. You will find an application built on this library in the [PHPQueueManager/Application](https://github.com/PHPQueueManager/Application) repo.

```
composer require phpqueuemanager/php-queue-manager
```
You can find a starter project based on this package here.

### Adapter Requires

### RabbitMQ

- [https://pecl.php.net/package/amqp](https://pecl.php.net/package/amqp)
- Include the `php-amqplib/php-amqplib` package in your project.

```
composer require php-amqplib/php-amqplib
```

### Kafka

- [https://pecl.php.net/package/rdkafka](https://pecl.php.net/package/rdkafka)
- [php.net Documentation](https://arnaud.le-blanc.net/php-rdkafka-doc/phpdoc/rdkafka.setup.html)

### Develop Your Own Queue Storage Adapter

You can write your own Queue Storage and your own handler.

```php
<?php
namespace App\QueueManager\Adapters;

use \PHPQueueManager\PHPQueueManager\Adapters\{AbstractAdapter, AdapterInterface};
use \PHPQueueManager\PHPQueueManager\Exceptions\{DeadLetterQueueException, 
    ReTryQueueException};
use \PHPQueueManager\PHPQueueManager\Queue\{JobMessageInterface,
    Message,
    MessageInterface,
    QueueInterface};

class MyQueueAdapter extends AbstractAdapter implements AdapterInterface
{

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        try {
            // TODO : Open Connection
            
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
        // TODO : Queue Declare
        
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function publish(MessageInterface $message): bool
    {
        try {
            // TODO : Add a New Job to the Queue!

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
            // TODO : Consume Queued Messages!
        } catch (\Throwable $e) {
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        // TODO : Close Connection
        
        return true;
    }

    /**
     * @inheritDoc
     */
    public function retry(MessageInterface $message): void
    {
        $message->retryNotification();
        // TODO : Set Message to Try Again
    }

    /**
     * @inheritDoc
     */
    public function addDeadLetterQueue(MessageInterface $message): void
    {
        try {
            $message->deadLetterNotification();
            // TODO : Write to Death Letter Queue.
        } catch (\Throwable $e) {
        }
    }

}
```

## Getting Help

If you have questions, concerns, bug reports, etc, please file an issue in this repository's Issue Tracker.

## Getting Involved

> All contributions to this project will be published under the MIT License. By submitting a pull request or filing a bug, issue, or feature request, you are agreeing to comply with this waiver of copyright interest.

There are two primary ways to help:

- Using the issue tracker, and
- Changing the code-base.

### Using the issue tracker

Use the issue tracker to suggest feature requests, report bugs, and ask questions. This is also a great way to connect with the developers of the project as well as others who are interested in this solution.

Use the issue tracker to find ways to contribute. Find a bug or a feature, mention in the issue that you will take on that effort, then follow the Changing the code-base guidance below.

### Changing the code-base

Generally speaking, you should fork this repository, make changes in your own fork, and then submit a pull request. All new code should have associated unit tests that validate implemented features and the presence or lack of defects. Additionally, the code should follow any stylistic and architectural guidelines prescribed by the project. In the absence of such guidelines, mimic the styles and patterns in the existing code-base.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2024 [MIT License](./LICENSE)