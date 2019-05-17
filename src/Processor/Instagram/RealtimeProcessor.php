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
     * PushProcessor constructor.
     * @param InstagramToErpQuery $instagramToErpQuery
     */
    public function __construct(
        InstagramToErpQuery $instagramToErpQuery
    ) {
        $this->instagramToErpQuery = $instagramToErpQuery;
    }

    /**
     * @param LiveBroadcast $broadcast
     */
    public function liveStarted(LiveBroadcast $broadcast): void
    {
        printf('[RTC] Live broadcast %s has been started%s', $broadcast->getBroadcastId(), PHP_EOL);
    }

    /**
     * @param LiveBroadcast $broadcast
     */
    public function liveStopped(LiveBroadcast $broadcast): void
    {
        printf('[RTC] Live broadcast %s has been stopped%s', $broadcast->getBroadcastId(), PHP_EOL);
    }

    /**
     * @param DirectThread $directThread
     */
    public function directStoryCreated(DirectThread $directThread): void
    {
        printf('[RTC] Story %s has been created%s', $directThread->getThreadId(), PHP_EOL);
    }


    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param DirectThreadItem $directThreadItem
     */
    public function directStoryUpdated(string $threadId, string $threadItemId, DirectThreadItem $directThreadItem): void
    {
        printf('[RTC] Item %s has been created in story %s%s', $threadItemId, $threadId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param StoryScreenshot $storyScreenshot
     */
    public function directStoryScreenshot(string $threadId, StoryScreenshot $storyScreenshot): void
    {
        printf('[RTC] %s has taken screenshot of story %s%s', $storyScreenshot->getActionUserDict()->getUsername(), $threadId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param ActionBadge $actionBadge
     */
    public function directStoryAction(string $threadId, ActionBadge $actionBadge): void
    {
        printf('[RTC] Story in thread %s has badge %s now%s', $threadId, $actionBadge->getActionType(), PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param DirectThread $directThread
     */
    public function threadCreated(string $threadId, DirectThread $directThread): void
    {
        printf('[RTC] Thread %s has been created%s', $threadId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param DirectThread $directThread
     */
    public function threadUpdated(string $threadId, DirectThread $directThread): void
    {
        printf('[RTC] Thread %s has been updated%s', $threadId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param ThreadAction $threadAction
     */
    public function threadNotify(string $threadId, string $threadItemId, ThreadAction $threadAction): void
    {
        printf('[RTC] Thread %s has notification from %s%s', $threadId, $threadAction->getUserId(), PHP_EOL);
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
        printf('[RTC] Thread %s has been checked by %s%s', $threadId, $userId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param ThreadActivity $threadActivity
     */
    public function threadActivity(string $threadId, ThreadActivity $threadActivity): void
    {
        printf('[RTC] Thread %s has some activity made by %s%s', $threadId, $threadActivity->getSenderId(), PHP_EOL);
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

        var_dump('threadItemCreated', $message);

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
        printf('[RTC] Item %s has been created in thread %s%s', $threadItemId, $threadId, PHP_EOL);
    }

    /**
     * @param string $threadId
     * @param string $threadItemId
     * @param DirectThreadItem $directThreadItem
     */
    public function threadItemUpdated(string $threadId, string $threadItemId, DirectThreadItem $directThreadItem): void
    {
        printf('[RTC] Item %s has been updated in thread %s%s', $threadItemId, $threadId, PHP_EOL);
    }


    /**
     * @param string $threadId
     * @param string $threadItemId
     */
    public function threadItemRemoved(string $threadId, string $threadItemId): void
    {
        printf('[RTC] Item %s has been removed from thread %s%s', $threadItemId, $threadId, PHP_EOL);
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

        if ($ackAction->getStatus() === 'ok') {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'threadId' => $ackAction->getPayload()->getThreadId(),
                    'itemId' => $ackAction->getPayload()->getItemId(),
                    'messageId' => $ackAction->getPayload()->getClientContext(),
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));
        }

        printf('[RTC] Received ACK for %s with status %s%s', $ackAction->getPayload()->getClientContext(), $ackAction->getStatus(), PHP_EOL);
    }

    /**
     * @param string $inbox
     * @param DirectSeenItemPayload $directSeenItemPayload
     */
    public function unseenCountUpdate(string $inbox, DirectSeenItemPayload $directSeenItemPayload): void
    {
        printf('[RTC] Updating unseen count in %s to %d%s', $inbox, $directSeenItemPayload->getCount(), PHP_EOL);
    }

    /**
     * @param UserPresence $userPresence
     */
    public function presence(UserPresence $userPresence): void
    {
        $action = $userPresence->getIsActive() ? 'is now using' : 'just closed';
        printf('[RTC] User %s %s one of Instagram apps%s', $userPresence->getUserId(), $action, PHP_EOL);
    }

}