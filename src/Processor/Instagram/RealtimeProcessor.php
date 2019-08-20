<?php


namespace App\Processor\Instagram;

use App\Rabbit\InstagramToErpQuery;
use \InstagramAPI\Realtime\Payload\LiveBroadcast;
use InstagramAPI\Response\Model\DirectSeenItemPayload;
use \InstagramAPI\Response\Model\DirectThread;
use \InstagramAPI\Response\Model\DirectThreadItem;
use \InstagramAPI\Realtime\Payload\StoryScreenshot;
use \InstagramAPI\Response\Model\ActionBadge;
use \InstagramAPI\Realtime\Payload\ThreadAction;
use \InstagramAPI\Response\Model\DirectThreadLastSeenAt;
use \InstagramAPI\Realtime\Payload\ThreadActivity;
use \InstagramAPI\Realtime\Payload\Action\AckAction;
use InstagramAPI\Response\Model\UserPresence;
use Psr\Log\LoggerInterface;

/**
 * Class RealtimeProcessor
 * @package App\Processor\Instagram
 */
class RealtimeProcessor
{
    /**
     * @var InstagramToErpQuery
     */
    private $instagramToErpQuery;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * PushProcessor constructor.
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        InstagramToErpQuery $instagramToErpQuery,
        LoggerInterface $logger
    ) {
        $this->instagramToErpQuery = $instagramToErpQuery;
        $this->logger = $logger;
    }

    /**
     * @param LiveBroadcast $broadcast
     */
    public function liveStarted(LiveBroadcast $broadcast): void
    {
        $this->logger->info(sprintf('Live broadcast %s has been started', $broadcast->getBroadcastId()), []);
    }

    /**
     * @param LiveBroadcast $broadcast
     */
    public function liveStopped(LiveBroadcast $broadcast): void
    {
        $this->logger->info(sprintf('Live broadcast %s has been stopped', $broadcast->getBroadcastId()), []);
    }

    /**
     * @param DirectThread $directThread
     */
    public function directStoryCreated(DirectThread $directThread): void
    {
        $this->logger->info(sprintf('Story %s has been created', $directThread->getThreadId()), []);
    }


    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param DirectThreadItem $directThreadItem
     */
    public function directStoryUpdated(string $threadId, string $threadItemId, DirectThreadItem $directThreadItem): void
    {
        $this->logger->info(sprintf('Item %s has been created in story %s', $threadItemId, $threadId), []);
    }

    /**
     * @param string $threadId
     * @param StoryScreenshot $storyScreenshot
     */
    public function directStoryScreenshot(string $threadId, StoryScreenshot $storyScreenshot): void
    {
        $this->logger->info(sprintf('User %s has taken screenshot of story in direct %s', $storyScreenshot->getActionUserDict()->getUsername(), $threadId), []);

    }

    /**
     * @param string $threadId
     * @param ActionBadge $actionBadge
     */
    public function directStoryAction(string $threadId, ActionBadge $actionBadge): void
    {
        $this->logger->info(sprintf('Story in thread %s has badge %s now', $threadId, $actionBadge->getActionType()), []);

    }

    /**
     * @param string $threadId
     * @param DirectThread $directThread
     */
    public function threadCreated(string $threadId, DirectThread $directThread): void
    {
        $this->logger->info(sprintf('Thread %s has been created', $threadId), []);
    }

    /**
     * @param string $threadId
     * @param DirectThread $directThread
     */
    public function threadUpdated(string $threadId, DirectThread $directThread): void
    {
        $this->logger->info(sprintf('Thread %s has been updated', $threadId), []);
    }

    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param ThreadAction $threadAction
     */
    public function threadNotify(string $threadId, string $threadItemId, ThreadAction $threadAction): void
    {
        $this->logger->info(sprintf('Thread %s has notification from %s on item %s', $threadId, $threadAction->getUserId(), $threadItemId), []);
    }

    /**
     * @param string $threadId
     * @param string $userId
     * @param DirectThreadLastSeenAt $directThreadLastSeenAt
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function threadSeen(string $threadId, string $userId, DirectThreadLastSeenAt $directThreadLastSeenAt): void
    {
        $request = [
            'method' => 'messagesInDirectHasBenSeen',
            'payload' => [
                'threadId' => $threadId,
                'userId' => $userId
            ]
        ];


        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Thread %s has been checked by %s at %s', $threadId, $userId, $directThreadLastSeenAt), []);
    }

    /**
     * @param string $threadId
     * @param ThreadActivity $threadActivity
     */
    public function threadActivity(string $threadId, ThreadActivity $threadActivity): void
    {
        $this->logger->info(sprintf('Thread %s has some activity made by %s', $threadId, $threadActivity->getSenderId()), []);
    }

    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param DirectThreadItem $directThreadItem
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function threadItemCreated(string $threadId, string $threadItemId, DirectThreadItem $directThreadItem): void
    {
        $message = json_decode($directThreadItem,  true);

        $request = [
            'method' => 'threadItemCreated',
            'payload' => [
                'direct' => [
                    'threadId' => $threadId,
                    'threadItemId' => $threadItemId
                ],
                'message' => $message,
            ]
        ];


        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Item %s has been created in thread %s', $threadItemId, $threadId), []);
    }

    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param DirectThreadItem $directThreadItem
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function threadItemUpdated(string $threadId, string $threadItemId, DirectThreadItem $directThreadItem): void
    {
        $message = json_decode($directThreadItem,  true);

        $request = [
            'method' => 'threadItemUpdated',
            'payload' => [
                'direct' => [
                    'threadId' => $threadId,
                    'threadItemId' => $threadItemId
                ],
                'message' => $message,
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Item %s has been updated in thread %s', $threadItemId, $threadId), []);
    }


    /**
     * @param string $threadId
     * @param string $threadItemId
     */
    public function threadItemRemoved(string $threadId, string $threadItemId): void
    {
        $this->logger->info(sprintf('Item %s has been removed from thread %s', $threadItemId, $threadId), []);
    }

    /**
     * @param AckAction $ackAction
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function clientContextAck(AckAction $ackAction): void
    {
        /** @Todo that double directProcessor ACK. Understand cases and remove */
        $this->logger->info(sprintf('Item %s get ACK status %s', $ackAction->getPayload()->getClientContext(), $ackAction->getStatus()), []);
    }

    /**
     * @param string $inbox
     * @param DirectSeenItemPayload $directSeenItemPayload
     */
    public function unseenCountUpdate(string $inbox, DirectSeenItemPayload $directSeenItemPayload): void
    {
        $this->logger->info(sprintf('Updating unseen count in %s to %d', $inbox, $directSeenItemPayload->getCount()), []);
    }

    /**
     * @param UserPresence $userPresence
     */
    public function presence(UserPresence $userPresence): void
    {
        $action = $userPresence->getIsActive() ? 'is now using' : 'just closed';
        $this->logger->info(sprintf('User %s %s one of Instagram apps', $userPresence->getUserId(), $action), []);
    }

}