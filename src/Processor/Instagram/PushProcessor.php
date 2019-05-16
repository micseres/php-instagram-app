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
     */
    public function comment(Notification $notification): void
    {
        switch ($notification->getActionPath()) {
            case 'comments_v2':
                $mediaId = $notification->getActionParam('media_id');
                $commentId = $notification->getActionParam('target_comment_id');
                break;
            case 'media':
            default:
                $mediaId = $notification->getActionParam('id');
                $commentId = $notification->getActionParam('forced_preview_comment_id');
        }
        printf(
            'Media ID: %s. Comment ID: %s.%s',
            $mediaId,
            $commentId,
            PHP_EOL
        );
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
