<?php


namespace App\Processor\Instagram;

use App\Rabbit\InstagramToErpQuery;
use \InstagramAPI\Push\Notification;
use Psr\Log\LoggerInterface;

/**
 * Class PushProcessor
 * @package App\Processor\Instagram
 */
class PushProcessor
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
     * @param Notification $notification
     */
    public function incoming(Notification $notification): void
    {
        $this->logger->info(sprintf('Received incoming notification "%s"',  $notification->getMessage()), $notification->getActionParams());
    }

    /**
     * @param Notification $notification
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function like(Notification $notification): void
    {
        $request = [
            'method' => 'getNewLikeForMedia',
            'payload' => [
                'mediaId' => $notification->getActionParam('id'),
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received like notification for mediaId: "%s"',  $notification->getActionParam('id')), $notification->getActionParams());
    }

    /**
     * @param Notification $notification
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function comment(Notification $notification): void
    {
        $action = $notification->getActionPath();

        if ($action === 'comments_v2') {
            $mediaId = $notification->getActionParam('media_id');
            $targetCommentId = $notification->getActionParam('target_comment_id');

            $request = [
                'method' => 'getNewAnswerForCommentMedia',
                'payload' => [
                    'action' => $action,
                    'mediaId' => $mediaId,
                    'targetCommentId' => $targetCommentId,
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Comment for comment. Media ID: %s Target comment ID: %s Action: %s', $mediaId, $targetCommentId, $action), $notification->getActionParams());

            return;
        }

        if ($action === 'media') {
            $mediaId = $notification->getActionParam('id');
            $commentId = $notification->getActionParam('forced_preview_comment_id');

            $request = [
                'method' => 'getNewCommentToMedia',
                'payload' => [
                    'action' => $action,
                    'mediaId' => $mediaId,
                    'commentId' => $commentId,
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Comment for media. Media ID: %s Comment ID: %s Action: %s', $mediaId, $commentId, $action), $notification->getActionParams());

            return;
        }

        $this->logger->warning(sprintf('Undefined comment message %s', $action), $notification->getActionParams());

    }

    /**
     * @param Notification $notification
     */
    public function directMessage(Notification $notification): void
    {
        $this->logger->info(sprintf('Direct message. Thread ID: %s. Thread item ID: %s', $notification->getActionParam('id'), $notification->getActionParam('x')), $notification->getActionParams());

    }
}
