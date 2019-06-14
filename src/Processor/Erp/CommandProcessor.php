<?php


namespace App\Processor\Erp;

use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Instagram;
use \InstagramAPI\Realtime;
use InstagramAPI\Realtime\Payload\Action\AckAction;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Class CommandProcessor
 * @package App\Processor\Erp
 */
class CommandProcessor
{
    /**
     * @var Instagram
     */
    private $instagram;
    /**
     * @var InstagramToErpQuery
     */
    private $instagramToErpQuery;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CommandProcessor constructor.
     * @param Instagram $instagram
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        Instagram $instagram,
        InstagramToErpQuery $instagramToErpQuery,
        LoggerInterface $logger
    )
    {
        $this->instagram = $instagram;
        $this->instagramToErpQuery = $instagramToErpQuery;
        $this->logger = $logger;
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getMediaInfo(array $payload): void
    {
        $this->logger->info(sprintf('Get media %s', $payload['mediaId']), $payload);

        $mediaInfo = $this->instagram->media->getInfo($payload['mediaId'])->getHttpResponse()->getBody();

        $message = json_decode($mediaInfo, true);

        $request = [
            'method' => 'getMediaInfo',
            'payload' => $message
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received media %s', $payload['mediaId']), $message);

    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getUserInfoById(array $payload): void
    {
        $this->logger->info(sprintf('Get info about user %s', $payload['userId']), $payload);

        $mediaInfo = $this->instagram->people->getInfoById($payload['userId'])->getHttpResponse()->getBody();

        $message = json_decode($mediaInfo, true);

        $request = [
            'method' => 'getUserInfoById',
            'payload' => $message
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received info about user %s', $payload['userId']), $message);

    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getMediaComments(array $payload): void
    {
        $this->logger->info(sprintf('Get comments for media %s', $payload['mediaId']), $payload);

        $comments = $this->instagram->media->getComments($payload['mediaId'])->getHttpResponse()->getBody();

        $message = json_decode($comments, true);

        $request = [
            'method' => 'updateMediaComments',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received comments for media %s', $payload['mediaId']), $message);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getMediaCommentAnswers(array $payload): void
    {

        $this->logger->info(sprintf('Get answers for comments %s in media %s', $payload['targetCommentId'], $payload['mediaId']), $payload);

        $answers = $this->instagram->media->getCommentReplies($payload['mediaId'], $payload['targetCommentId'])->getHttpResponse()->getBody();

        $message = json_decode($answers, true);

        $request = [
            'method' => 'updateMediaCommentAnswers',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'targetCommentId' => $payload['targetCommentId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received answers for comments %s in media %s', $payload['targetCommentId'], $payload['mediaId']), $message);

    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function inviteTextToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Post invite text to direct %s', $payload['accountId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $startDirect = $this->instagram->direct->sendText(['users' => [
            $payload['accountId']
        ]], $payload['text'])->getHttpResponse()->getBody();


        $message = json_decode($startDirect, true);

        $request = [
            'method' => 'confirmInviteTextToDirect',
            'payload' => [
                'threadId' => $message['payload']['thread_id'],
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $request = [
            'method' => 'messageToDirectHasBenDelivery',
            'payload' => [
                'threadId' => $message['payload']['thread_id'],
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Posted invite text to direct %s', $payload['accountId']), $message);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function postComment(array $payload): void
    {
        $this->logger->info(sprintf('Post comment to media %s', $payload['mediaId']), $payload);

        $commentResponse = $this->instagram->media->comment($payload['mediaId'], $payload['message']);

        $message = json_decode($commentResponse, true);

        $request = [
            'method' => 'postedComment',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Posted comment to media %s', $payload['mediaId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function postCommentAnswer(array $payload): void
    {
        $this->logger->info(sprintf('Post answer to comment %s in media %s', $payload['replyCommentId'], $payload['mediaId']), $payload);

        $commentResponse = $this->instagram->media->comment($payload['mediaId'], $payload['message'], $payload['replyCommentId']);

        $message = json_decode($commentResponse, true);

        $request = [
            'method' => 'postedCommentAnswer',
            'payload' => [
                'appealId' => $payload['appealId'],
                'mediaId' => $payload['mediaId'],
                'replyCommentId' => $payload['replyCommentId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Posted answer to comment %s in media %s', $payload['replyCommentId'], $payload['mediaId']), $message);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function checkMediaLikes(array $payload): void
    {
        $this->logger->info(sprintf('Check likes for media %s', $payload['mediaId']), $payload);

        $likers = $this->instagram->media->getLikers($payload['mediaId']);

        $message = json_decode($likers, true);

        $request = [
            'method' => 'updateMediaLikes',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));


        $this->logger->info(sprintf('Checked likes for media %s', $payload['mediaId']), $payload);
    }
}