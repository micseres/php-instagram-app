<?php


namespace App\Command;

use App\Exception\InstagramLoginException;
use App\Processor\Erp\CommandProcessor;
use App\Processor\Instagram\PushProcessor;
use App\Processor\Instagram\RealtimeProcessor;
use App\Rabbit\ErpToInstagramQuery;
use App\Rabbit\InstagramToErpQuery;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \React\EventLoop\Factory;
use \InstagramAPI\Instagram;
use \Monolog\Logger;
use \InstagramAPI\Push as InstagramAPIPush;
use \InstagramAPI\Realtime as InstagramAPIRealtime;

/**
 * Class PushReceiverCommand
 * @package App\Commands
 */
class ServerCommand extends Command
{
    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    /**
     * @var string
     */
    protected static $defaultName = 'app:server';
    /**
     * @var ErpToInstagramQuery
     */
    private $erpToInstagramQuery;
    /**
     * @var InstagramToErpQuery
     */
    private $instagramToErpQuery;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ServerCommand constructor.
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param ErpToInstagramQuery $erpToInstagramQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        InstagramToErpQuery $instagramToErpQuery,
        ErpToInstagramQuery $erpToInstagramQuery
    ) {
        parent::__construct();
        $this->erpToInstagramQuery = $erpToInstagramQuery;
        $this->instagramToErpQuery = $instagramToErpQuery;
    }

    protected function configure()
    {
        $this
            ->setDescription('Instagram comunication server.')
            ->setHelp('php bin/console app:server')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPEnvelopeException
     * @throws \AMQPQueueException
     * @throws InstagramLoginException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->output->writeln(
            sprintf(
                "%s: service started",
                (new \DateTime())->format("Y-m-d H:i:s")
            )
        );

        /////// CONFIG ///////
        $username = getenv('APP_IG_LOGIN');
        $password = getenv('APP_IG_PASS');
        $debug = true;
        $truncatedDebug = false;

        $ig = new Instagram($debug, $truncatedDebug);

        try {
            $ig->login($username, $password);
        } catch (\Exception $e) {
            throw new InstagramLoginException('Can`t login into instagram: '.$e->getMessage());
        }

        if ($debug) {
            $pushLogger = new Logger('PUSHIER');
            $pushLogger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));

            $realtimeLogger = new Logger('REALTIME');
            $realtimeLogger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));

            $commandLogger = new Logger('COMMAND');
            $commandLogger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));
        } else {
            $pushLogger = null;
            $realtimeLogger = null;
            $commandLogger = null;
        }



        $loop = Factory::create();

        $push = new InstagramAPIPush($loop, $ig, $pushLogger);
        $rtc = new InstagramAPIRealtime($ig, $loop, $realtimeLogger);

        $pushProcessor = new PushProcessor($this->instagramToErpQuery);
        $realtimeProcessor = new RealtimeProcessor($this->instagramToErpQuery);
        $commandProcessor = new CommandProcessor($rtc, $loop, $ig, $this->instagramToErpQuery);

        $push->on('incoming', [$pushProcessor, 'incoming']);
        $push->on('like', [$pushProcessor, 'like']);
        $push->on('comment', [$pushProcessor, 'comment']);
        $push->on('direct_v2_message', [$pushProcessor, 'directMessage']);

        $push->on('error', function (\Exception $e) use ($push, $loop) {
            printf('[!!!] Got fatal error from FBNS: %s%s', $e->getMessage(), PHP_EOL);
            $push->stop();
            $loop->stop();
        });

        $rtc->on('live-started', [$realtimeProcessor, 'liveStarted']);
        $rtc->on('live-stopped', [$realtimeProcessor, 'liveStopped']);
        $rtc->on('direct-story-created', [$realtimeProcessor, 'directStoryCreated']);
        $rtc->on('direct-story-updated', [$realtimeProcessor, 'directStoryUpdated']);
        $rtc->on('direct-story-screenshot', [$realtimeProcessor, 'directStoryScreenshot']);
        $rtc->on('direct-story-action', [$realtimeProcessor, 'directStoryAction']);
        $rtc->on('thread-created', [$realtimeProcessor, 'threadCreated']);
        $rtc->on('thread-updated', [$realtimeProcessor, 'threadUpdated']);
        $rtc->on('thread-notify', [$realtimeProcessor, 'threadNotify']);
        $rtc->on('thread-seen', [$realtimeProcessor, 'threadSeen']);
        $rtc->on('thread-activity', [$realtimeProcessor, 'threadActivity']);
        $rtc->on('thread-item-created', [$realtimeProcessor, 'threadItemCreated']);
        $rtc->on('thread-item-updated', [$realtimeProcessor, 'threadItemUpdated']);
        $rtc->on('thread-item-removed', [$realtimeProcessor, 'threadItemRemoved']);
        $rtc->on('client-context-ack', [$realtimeProcessor, 'clientContextAck']);
        $rtc->on('unseen-count-update', [$realtimeProcessor, 'unseenCountUpdate']);
        $rtc->on('presence', [$realtimeProcessor, 'presence']);

        $rtc->on('error', function (\Exception $e) use ($rtc, $loop) {
            printf('[!!!] Got fatal error from Realtime: %s%s', $e->getMessage(), PHP_EOL);
            $rtc->stop();
            $loop->stop();
        });

        $this->readErpCommand($loop, $commandProcessor);

        $rtc->start();
        $push->start();

        $loop->run();
    }


    /**
     * @param LoopInterface $loop
     * @param CommandProcessor $commandProcessor
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPQueueException
     */
    private function readErpCommand(LoopInterface $loop, CommandProcessor $commandProcessor): void
    {
        $queue = $this->erpToInstagramQuery->getQueue();

        $loop->addPeriodicTimer(0.1, function () use ($queue, $commandProcessor) {
            $message = $queue->get();

            if (false !== $message) {
                $payload = json_decode($message->getBody(),  true);
                call_user_func([$commandProcessor, $payload['method']], $payload['payload']);
                $queue->ack($message->getDeliveryTag());
            }
        });
    }
}