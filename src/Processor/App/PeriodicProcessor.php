<?php


namespace App\Processor\App;


use App\Rabbit\InstagramToErpMediaQuery;
use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Instagram;
use Psr\Log\LoggerInterface;

/**
 * Class PeriodicProcessor
 * @package App\Processor\App
 */
class PeriodicProcessor
{
    /**
     * @var Instagram
     */
    private $instagram;
    /**
     * @var InstagramToErpQuery
     */
    private $instagramToErpMediaQuery;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var InstagramToErpQuery
     */
    private $instagramToErpQuery;

    /**
     * CommandProcessor constructor.
     * @param Instagram $instagram
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param InstagramToErpMediaQuery $instagramToErpMediaQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        Instagram $instagram,
        InstagramToErpQuery $instagramToErpQuery,
        InstagramToErpMediaQuery $instagramToErpMediaQuery,
        LoggerInterface $logger
    )
    {
        $this->instagram = $instagram;
        $this->instagramToErpQuery = $instagramToErpQuery;
        $this->instagramToErpMediaQuery = $instagramToErpMediaQuery;
        $this->logger = $logger;
    }

    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getRecentActivityInbox(): void
    {
        $this->logger->info('Get recent activity inbox');

        $mediaInfo = $this->instagram->people->getRecentActivityInbox()->getHttpResponse()->getBody();

        $message = json_decode($mediaInfo, true);

        $request = [
            'method' => 'updateRecentActivityInbox',
            'payload' => $message
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info('Got recent activity inbox', $message);
    }


    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getPendingInbox(): void
    {
        $this->logger->info('Approve incoming pending messages');

        $pendingThreads = $this->instagram->direct->getPendingInbox();

        $message = json_decode($pendingThreads, true);


        $threadIds = [];

        $threads = $message['inbox']['threads'];

        foreach ($threads as $thread) {
            $threadIds[] = $thread['thread_id'];
        }

        if (count($threadIds) > 0) {
            $approvedThreads = $this->instagram->direct->approvePendingThreads($threadIds);

            $message = json_decode($approvedThreads, true);

            $request = [
                'method' => 'approvePendingThreads',
                'payload' => [
                    'threads' => $threadIds,
                    'message' => $message
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info('Approved pending incoming messages');

        } else {
            $this->logger->info('Nothing in incoming pending messages');
        }
    }
}