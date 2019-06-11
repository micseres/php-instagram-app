<?php


namespace App\Command;

use App\Exception\InstagramLoginException;
use App\Processor\Erp\CommandProcessor;
use App\Processor\Instagram\PushProcessor;
use App\Processor\Instagram\RealtimeProcessor;
use App\Processor\Erp\DirectProcessor;
use App\Rabbit\ErpToInstagramQuery;
use App\Rabbit\InstagramToErpQuery;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
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
     * @throws \Exception
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

        $username = getenv('APP_IG_LOGIN');
        $password = getenv('APP_IG_PASS');
        $processorsDebug = (bool)getenv('APP_PROCESSORS_DEBUG');
        $igDebug = (bool)getenv('APP_IG_DEBUG');

        $ig = new Instagram($igDebug);

        try {
            $ig->login($username, $password);
        } catch (\Exception $e) {
            throw new InstagramLoginException('Can`t login into instagram: '.$e->getMessage());
        }

        $logPath = __DIR__.'/../../var/log/';

        $pushLogger = new Logger('IG_PUSHIER');
        $pushProcessorLogger = new Logger('APP_PUSHIER_PROCESSOR');
        $realtimeLogger = new Logger('IG_REALTIME');
        $realtimeProcessorLogger = new Logger('IG_REALTIME_PROCESSOR');
        $commandLogger = new Logger('IG_COMMAND');
        $commandProcessorLogger = new Logger('IG_COMMAND_PROCESSOR');
        $directProcessorLogger = new Logger('IG_DIRECT_PROCESSOR');

        $pushLogger->pushHandler(new StreamHandler($logPath.'push_logger.log', Logger::INFO));
        $pushProcessorLogger->pushHandler(new StreamHandler($logPath.'push_processor_logger.log', Logger::INFO));
        $realtimeLogger->pushHandler(new StreamHandler($logPath.'realtime_logger.log', Logger::INFO));
        $realtimeProcessorLogger->pushHandler(new StreamHandler($logPath.'realtime_processor_logger.log', Logger::INFO));
        $commandLogger->pushHandler(new StreamHandler($logPath.'command_logger.log', Logger::INFO));
        $commandProcessorLogger->pushHandler(new StreamHandler($logPath.'command_processor_logger.log', Logger::INFO));
        $directProcessorLogger->pushHandler(new StreamHandler($logPath.'direct_processor_logger.log', Logger::INFO));

        if ($processorsDebug) {
            $pushLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $pushProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $realtimeLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $realtimeProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $commandLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $commandProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $directProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $loop = Factory::create();

        $push = new InstagramAPIPush($loop, $ig, $pushLogger);
        $rtc = new InstagramAPIRealtime($ig, $loop, $realtimeLogger);

        $pushProcessor = new PushProcessor($this->instagramToErpQuery, $pushProcessorLogger);
        $realtimeProcessor = new RealtimeProcessor($this->instagramToErpQuery, $realtimeProcessorLogger);
        $commandProcessor = new CommandProcessor($ig, $this->instagramToErpQuery, $commandProcessorLogger);
        $directProcessor = new DirectProcessor($rtc, $loop, $ig, $this->instagramToErpQuery, $commandProcessorLogger);

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
        $rtc->on('unseen-count-update', [$realtimeProcessor, 'unseenCountUpdate']);
        $rtc->on('presence', [$realtimeProcessor, 'presence']);

        $rtc->on('client-context-ack', [$directProcessor, 'clientContextAck']);
        $rtc->on('client-context-ack', [$realtimeProcessor, 'clientContextAck']);

        $rtc->on('error', function (\Exception $e) use ($rtc, $loop) {
            printf('[!!!] Got fatal error from Realtime: %s%s', $e->getMessage(), PHP_EOL);
            $rtc->stop();
            $loop->stop();
        });

        $queue = $this->erpToInstagramQuery->getQueue();

        $loop->addPeriodicTimer(0.25, function () use ($queue, $commandProcessor, $directProcessor) {
            $message = $queue->get();

            if (false !== $message) {
                $payload = json_decode($message->getBody(),  true);

                if ($payload['processor'] === 'direct') {
                    call_user_func([$directProcessor, $payload['method']], $payload['payload']);
                } else {
                    call_user_func([$commandProcessor, $payload['method']], $payload['payload']);
                }

                $queue->ack($message->getDeliveryTag());
            }
        });

        $rtc->start();
        $push->start();

        $loop->run();
    }
}