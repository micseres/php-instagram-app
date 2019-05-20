<?php


namespace App\Processor\Instagram;

use App\Rabbit\InstagramToErpQuery;
use \InstagramAPI\Push\Notification;

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
     * PushProcessor constructor.
     * @param InstagramToErpQuery $instagramToErpQuery
     */
    public function __construct(
        InstagramToErpQuery $instagramToErpQuery
    ) {
        $this->instagramToErpQuery = $instagramToErpQuery;
    }

    /**
     * @param Notification $notification
     */
    public function incoming(Notification $notification): void
    {
        printf('%s%s', $notification->getMessage(), PHP_EOL);
    }

    /**
     * @param Notification $notification
     */
    public function like(Notification $notification): void
    {
        printf('Media ID: %s%s', $notification->getActionParam('id'), PHP_EOL);
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

        var_dump($notification);

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

            printf(
                'Media ID: %s. Target comment ID: %s.%s. Action %s',
                $mediaId,
                $targetCommentId,
                $action,
                PHP_EOL
            );
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

            printf(
                'Media ID: %s. Comment ID: %s.%s. Action %s',
                $mediaId,
                $commentId,
                $action,
                PHP_EOL
            );
        }
    }

    /**
     * @param Notification $notification
     */
    public function directMessage(Notification $notification): void
    {
        printf(
            'Thread ID: %s. Thread item ID: %s.%s',
            $notification->getActionParam('id'),
            $notification->getActionParam('x'),
            PHP_EOL
        );
    }
}
