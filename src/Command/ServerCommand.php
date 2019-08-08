<?php


namespace App\Command;

use App\Exception\InstagramChallengeCodeException;
use App\Exception\InstagramLoginException;
use App\Instagram\ExtendedInstagram;
use App\Processor\App\PeriodicProcessor;
use App\Processor\Erp\CommandProcessor;
use App\Processor\Instagram\PushProcessor;
use App\Processor\Instagram\RealtimeProcessor;
use App\Processor\Erp\DirectProcessor;
use App\Rabbit\ErpToInstagramQuery;
use App\Rabbit\ErpToInstagramSlowQuery;
use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Exception\ChallengeRequiredException;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \React\EventLoop\Factory;
use \Monolog\Logger;
use \InstagramAPI\Push as InstagramAPIPush;
use \InstagramAPI\Realtime as InstagramAPIRealtime;


/**
 * Class PushReceiverCommand
 * @package App\Commands
 */
class ServerCommand extends Command
{
    const VERIFICATION_BY_SMS = 0;
    const VERIFICATION_BY_EMAIL = 1;

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
     * @var ErpToInstagramSlowQuery
     */
    private $erpToInstagramSlowQuery;

    /**
     * ServerCommand constructor.
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param ErpToInstagramQuery $erpToInstagramQuery
     * @param ErpToInstagramSlowQuery $erpToInstagramSlowQuery
     */
    public function __construct(
        InstagramToErpQuery $instagramToErpQuery,
        ErpToInstagramQuery $erpToInstagramQuery,
        ErpToInstagramSlowQuery $erpToInstagramSlowQuery
    ) {
        parent::__construct();
        $this->erpToInstagramQuery = $erpToInstagramQuery;
        $this->instagramToErpQuery = $instagramToErpQuery;
        $this->erpToInstagramSlowQuery = $erpToInstagramSlowQuery;
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

        $ig = new ExtendedInstagram($igDebug);

        try {
            $loginResponse = $ig->login($username, $password);

            if ($loginResponse !== null && $loginResponse->isTwoFactorRequired()) {
                $twoFactorIdentifier = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
                $this->output->writeln('Enter code for two factor auth:' );
                $verificationCode = trim((string)fgets(STDIN));
                $ig->finishTwoFactorLogin($username, $password, $twoFactorIdentifier, $verificationCode);
            }
        } catch (\Exception $e) {
            $response = $e->getResponse();

            if ($e instanceof ChallengeRequiredException
                && $response->getErrorType() === 'checkpoint_challenge_required') {

                sleep(3);

                $checkApiPath = substr( $response->getChallenge()->getApiPath(), 1);

                $customResponse = $ig->request($checkApiPath)
                    ->setNeedsAuth(false)
                    ->addPost('choice', self::VERIFICATION_BY_EMAIL)
                    ->addPost('_uuid', $ig->uuid)
                    ->addPost('guid', $ig->uuid)
                    ->addPost('device_id', $ig->device_id)
                    ->addPost('_uid', $ig->account_id)
                    ->addPost('_csrftoken', $ig->client->getToken())
                    ->getDecodedResponse();

                try {
                    if ($customResponse['status'] === 'ok' && $customResponse['action'] === 'close') {
                        $this->output->writeln('Checkpoint bypassed! Run this file again to validate that it works.');
                        exit();
                    }

                    $this->output->writeln('Code that you received via ' . ( self::VERIFICATION_BY_EMAIL ? 'email' : 'sms' ) . ':' );
                    $code = trim((string)fgets(STDIN));
                    $ig->changeUser($username, $password);

                    $customResponse = $ig->request($checkApiPath)
                        ->setNeedsAuth(false)
                        ->addPost('security_code', $code)
                        ->addPost('_uuid', $ig->uuid)
                        ->addPost('guid', $ig->uuid)
                        ->addPost('device_id', $ig->device_id)
                        ->addPost('_uid', $ig->account_id)
                        ->addPost('_csrftoken', $ig->client->getToken())
                        ->getDecodedResponse();

                    if ($customResponse['status'] === 'ok') {
                        $this->output->writeln('Finished, logged in successfully! Run this file again to validate that it works.');
                    } else {
                        throw new InstagramChallengeCodeException($customResponse);
                    }
                } catch ( \Exception $ex ) {
                    throw new InstagramLoginException($ex->getMessage());
                }
            } else {
                throw new InstagramLoginException($e->getMessage());
            }
        }



        $logPath = __DIR__.'/../../var/log/';

        $pushLogger = new Logger('IG_PUSHIER');
        $pushProcessorLogger = new Logger('APP_PUSHIER_PROCESSOR');
        $realtimeLogger = new Logger('IG_REALTIME');
        $realtimeProcessorLogger = new Logger('IG_REALTIME_PROCESSOR');
        $commandLogger = new Logger('IG_COMMAND');
        $commandProcessorLogger = new Logger('IG_COMMAND_PROCESSOR');
        $directProcessorLogger = new Logger('IG_DIRECT_PROCESSOR');
        $periodicProcessorLogger = new Logger('IG_PERIODIC_PROCESSOR');


        $pushLogger->pushHandler(new StreamHandler($logPath.'push_logger.log', Logger::INFO));
        $pushProcessorLogger->pushHandler(new StreamHandler($logPath.'push_processor_logger.log', Logger::INFO));
        $realtimeLogger->pushHandler(new StreamHandler($logPath.'realtime_logger.log', Logger::INFO));
        $realtimeProcessorLogger->pushHandler(new StreamHandler($logPath.'realtime_processor_logger.log', Logger::INFO));
        $commandLogger->pushHandler(new StreamHandler($logPath.'command_logger.log', Logger::INFO));
        $commandProcessorLogger->pushHandler(new StreamHandler($logPath.'command_processor_logger.log', Logger::INFO));
        $directProcessorLogger->pushHandler(new StreamHandler($logPath.'direct_processor_logger.log', Logger::INFO));
        $periodicProcessorLogger->pushHandler(new StreamHandler($logPath.'direct_periodic_logger.log', Logger::INFO));

        if ($processorsDebug) {
            $pushLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $pushProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $realtimeLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $realtimeProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $commandLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $commandProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $directProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            $periodicProcessorLogger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $loop = Factory::create();

        $push = new InstagramAPIPush($loop, $ig, $pushLogger);
        $rtc = new InstagramAPIRealtime($ig, $loop, $realtimeLogger);

        $pushProcessor = new PushProcessor($this->instagramToErpQuery, $pushProcessorLogger);
        $realtimeProcessor = new RealtimeProcessor($this->instagramToErpQuery, $realtimeProcessorLogger);
        $commandProcessor = new CommandProcessor($ig, $this->instagramToErpQuery, $commandProcessorLogger);
        $directProcessor = new DirectProcessor($rtc, $loop, $ig, $this->instagramToErpQuery, $commandProcessorLogger);
        $periodicProcessor = new PeriodicProcessor($ig, $this->instagramToErpQuery, $periodicProcessorLogger);

        $push->on('incoming', [$pushProcessor, 'incoming']);
        $push->on('like', [$pushProcessor, 'like']);
        $push->on('comment', [$pushProcessor, 'comment']);
        $push->on('direct_v2_message', [$pushProcessor, 'directMessage']);

        $push->on('error', function (\Exception $e) use ($push, $loop) {
            $this->output->writeln(
                sprintf('[!!!] Got fatal error from FBNS: %s%s', $e->getMessage(), PHP_EOL)
            );
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
            $this->output->writeln(
                sprintf('[!!!] Got fatal error from Realtime: %s%s', $e->getMessage(), PHP_EOL)
            );
            $rtc->stop();
            $loop->stop();
        });

        $shortIntervalQueue = $this->erpToInstagramQuery->getQueue();

        $loop->addPeriodicTimer(5, function () use ($shortIntervalQueue, $commandProcessor, $directProcessor) {
            $message = $shortIntervalQueue->get();

            if (false !== $message) {
                $payload = json_decode($message->getBody(),  true);

                if ($payload['processor'] === 'direct') {
                    call_user_func([$directProcessor, $payload['method']], $payload['payload']);
                } else {
                    call_user_func([$commandProcessor, $payload['method']], $payload['payload']);
                }

                $shortIntervalQueue->ack($message->getDeliveryTag());
            }
        });

        $longIntervalQueue = $this->erpToInstagramSlowQuery->getQueue();

        $loop->addPeriodicTimer(20, function () use ($longIntervalQueue, $commandProcessor, $directProcessor) {
            $message = $longIntervalQueue->get();

            if (false !== $message) {
                $payload = json_decode($message->getBody(),  true);

                if ($payload['processor'] === 'direct') {
                    call_user_func([$directProcessor, $payload['method']], $payload['payload']);
                } else {
                    call_user_func([$commandProcessor, $payload['method']], $payload['payload']);
                }

                $longIntervalQueue->ack($message->getDeliveryTag());
            }
        });

//        $loop->addPeriodicTimer(150, function () use ($periodicProcessor) {
//            $periodicProcessor->getRecentActivityInbox();
//        });

        $rtc->start();
        $push->start();

        $loop->run();
    }
}