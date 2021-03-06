<?php


namespace App\Processor\Erp;

use App\Rabbit\InstagramToErpMediaQuery;
use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Exception\InstagramException;
use InstagramAPI\Instagram;
use Psr\Log\LoggerInterface;

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
     * @var InstagramToErpMediaQuery
     */
    private $instagramToErpMediaQuery;

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
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getMediaInfo(array $payload): void
    {
        $this->logger->info(sprintf('Get media %s', $payload['mediaId']), $payload);

        try {
            $mediaInfo = $this->instagram->media->getInfo($payload['mediaId'])->getHttpResponse()->getBody();
        } catch (InstagramException $exception) {
            if (strpos($exception->getMessage(), 'Media not found or unavailable')) {
                $request = [
                    'method' => 'mediaWasDeleted',
                    'payload' => [
                        'mediaId' => $payload['mediaId'],
                    ]
                ];

                $this->instagramToErpMediaQuery->publish(json_encode($request));

                $this->logger->warning(sprintf('Media was deleted %s', $payload['mediaId']), []);

                return;
            }

            throw $exception;
        }


        $message = json_decode($mediaInfo, true);

        $request = [
            'method' => 'getMediaInfo',
            'payload' => $message
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

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

        try {
            $mediaInfo = $this->instagram->people->getInfoById($payload['userId'])->getHttpResponse()->getBody();
        } catch (\InstagramAPI\Exception\NotFoundException $exception) {
            $request = [
                'method' => 'userWasDeletedCompletely',
                'payload' => [
                    'userId' => $payload['userId']
                ]
            ];

            $this->instagramToErpMediaQuery->publish(json_encode($request));

            $this->logger->warning(sprintf('Cant`t receive info about user user. That was not exists %s', $payload['userId']), ['message' => $exception->getMessage()]);

            return;
        }

        $message = json_decode($mediaInfo, true);

        $request = [
            'method' => 'getUserInfoById',
            'payload' => $message
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

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

        if (isset($payload['nextMaxId'])) {
            $comments = $this->instagram->media->getComments($payload['mediaId'], ['max_id' => $payload['nextMaxId']])->getHttpResponse()->getBody();
        } else {
            $comments = $this->instagram->media->getComments($payload['mediaId'])->getHttpResponse()->getBody();
        }

        $message = json_decode($comments, true);

        $request = [
            'method' => 'updateMediaComments',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received comments for media %s', $payload['mediaId']), $message);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getMediaComment(array $payload): void
    {
        $this->logger->info(sprintf('Get comment %s for media %s', $payload['commentId'], $payload['mediaId']), $payload);

        $comments = $this->instagram->media->getComments($payload['mediaId'], ['target_comment_id' => $payload['commentId']])->getHttpResponse()->getBody();

        $message = json_decode($comments, true);

        $request = [
            'method' => 'updateMediaComment',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'commentId' => $payload['commentId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Received comment %s for media %s', $payload['commentId'], $payload['mediaId']), $message);
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

        $this->instagramToErpMediaQuery->publish(json_encode($request));

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

        $this->instagramToErpMediaQuery->publish(json_encode($request));

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

        try {
            $commentResponse = $this->instagram->media->comment($payload['mediaId'], $payload['message'], $payload['replyCommentId']);
        } catch (\InstagramAPI\Exception\FeedbackRequiredException $exception) {
            $errorResponse = json_decode($exception->getResponse()->getHttpResponse()->getBody(), true);

            if ($errorResponse['feedback_message'] === "The comment you replied to was deleted.") {
                $request = [
                    'method' => 'answeredCommentWasDeleted',
                    'payload' => [
                        'appealId' => $payload['appealId'],
                        'mediaId' => $payload['mediaId'],
                        'replyCommentId' => $payload['replyCommentId'],
                    ]
                ];

                $this->instagramToErpMediaQuery->publish(json_encode($request));

                $this->logger->info(sprintf('Posted answer to comment fallied. Comment %s was deleted in media %s', $payload['replyCommentId'], $payload['mediaId']), $errorResponse);

                return;
            }

            if ($errorResponse['feedback_message'] === "The post you were viewing has been deleted.") {
                $request = [
                    'method' => 'answeredCommentMediaHasBeenDeleted',
                    'payload' => [
                        'appealId' => $payload['appealId'],
                        'mediaId' => $payload['mediaId'],
                        'replyCommentId' => $payload['replyCommentId'],
                    ]
                ];

                $this->instagramToErpMediaQuery->publish(json_encode($request));

                $this->logger->info(sprintf('Posted answer to comment fallied. The post %s you were viewing has been deleted.', $payload['mediaId']), $errorResponse);

                return;
            }

            if ($errorResponse['feedback_message'] === "The post you were viewing is no longer available.") {
                $request = [
                    'method' => 'answeredCommentMediaIsNoLongerAvailable',
                    'payload' => [
                        'appealId' => $payload['appealId'],
                        'mediaId' => $payload['mediaId'],
                        'replyCommentId' => $payload['replyCommentId'],
                    ]
                ];

                $this->instagramToErpMediaQuery->publish(json_encode($request));

                $this->logger->info(sprintf('Posted answer to comment fallied. The post %s you were viewing is no longer available.', $payload['mediaId']), $errorResponse);

                return;
            }


            throw $exception;
        }

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

        $this->instagramToErpMediaQuery->publish(json_encode($request));

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

        try {
            $likers = $this->instagram->media->getLikers($payload['mediaId']);
        } catch (InstagramException $exception) {
            if (strpos($exception->getMessage(), 'Sorry, this photo has been deleted.')) {
                $request = [
                    'method' => 'mediaWasDeleted',
                    'payload' => [
                        'mediaId' => $payload['mediaId'],
                    ]
                ];

                $this->instagramToErpMediaQuery->publish(json_encode($request));

                $this->logger->warning(sprintf('Media was deleted %s', $payload['mediaId']), []);

                return;
            }

            throw $exception;
        }

        $message = json_decode($likers, true);

        $request = [
            'method' => 'updateMediaLikes',
            'payload' => [
                'mediaId' => $payload['mediaId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));


        $this->logger->info(sprintf('Checked likes for media %s', $payload['mediaId']), $payload);
    }


    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function refreshPendingThread(array $payload): void
    {
        $this->logger->info(sprintf('Reread messages in pending thread %s', $payload['threadId']), $payload);

        try {
            $messages = $this->instagram->direct->getThread($payload['threadId'], $payload['cursor']);
        } catch (\InstagramAPI\Exception\NotFoundException $exception) {
            $this->logger->info(sprintf('Thread deleted in Instagram %s', $payload['threadId']), $payload);
            return;
        }

        $message = json_decode($messages, true);

        $request = [
            'method' => 'refreshPendingThread',
            'payload' => [
                'threadId' => $payload['threadId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Rereaded messages in pending thread %s', $payload['threadId']), $payload);
    }


    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function refreshThread(array $payload): void
    {
        $this->logger->info(sprintf('Reread messages in thread %s', $payload['threadId']), $payload);

        try {
            $messages = $this->instagram->direct->getThread($payload['threadId'], $payload['cursor']);
        } catch (\InstagramAPI\Exception\NotFoundException $exception) {
            $request = [
                'method' => 'closeThread',
                'payload' => [
                    'appealId' => $payload['appealId'],
                    'conversationId' => $payload['conversationId'],
                    'threadId' => $payload['threadId'],
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Thread deleted in Instagram %s', $payload['threadId']), $payload);

            return;
        }

        $message = json_decode($messages, true);

        $request = [
            'method' => 'refreshThread',
            'payload' => [
                'appealId' => $payload['appealId'],
                'conversationId' => $payload['conversationId'],
                'threadId' => $payload['threadId'],
                'force' => $payload['force'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Rereaded messages in thread %s', $payload['threadId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function searchExistsThreadIdToConversation(array $payload): void
    {
        $this->logger->info(sprintf('Find thread by account: %s', $payload['accountId']), $payload);

        $response = $this->instagram->direct->getThreadByParticipants([$payload['accountId']]);

        $message = json_decode($response, true);

        $request = [
            'method' => 'updateExistsThreadIdToConversation',
            'payload' => [
                'accountId' => $payload['accountId'],
                'conversationId' => $payload['conversationId'],
                'response' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Found thread by account: %s', $payload['accountId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     * @deprecated
     */
    public function sendPhotoToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send photo to user %s', $payload['accountId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $data = preg_replace('#^data:image/[^;]+;base64,#', '', $payload['photo']);
        $data = base64_decode($data);
        $filename = __DIR__.'/../../../var/files/'.uniqid().'.jpg';
        file_put_contents($filename, $data);

        $recipients = [];
        $recipients['thread'] = $payload['threadId'];
        $result = $this->instagram->direct->sendPhoto($recipients, $filename, ['client_context' => $payload['messageId']]);
        $message = json_decode($result, true);

        unlink($filename);

        $request = [
            'method' => 'messageToDirectHasBenDelivery',
            'payload' => [
                'itemId' => $message['payload']['item_id'],
                'threadId' => $payload['threadId'],
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
                'result' => $result
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Sent photo to user %s', $payload['threadId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendLinkToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send link to direct %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $recipients['thread'] = $payload['threadId'];
        $result = $this->instagram->direct->sendText($recipients, $payload['text'], ['client_context' => $payload['messageId']]);
        $message = json_decode($result, true);

        $request = [
            'method' => 'messageToDirectHasBenDelivery',
            'payload' => [
                'itemId' => $message['payload']['item_id'],
                'threadId' => $payload['threadId'],
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
                'result' => $result
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Sent link to direct %s', $payload['threadId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function deleteMessageFromDirect(array $payload): void
    {
        $this->logger->info(sprintf('Delete message %s in direct %s', $payload['threadItemId'], $payload['threadId']), $payload);

        $result = $this->instagram->direct->deleteItem($payload['threadId'], $payload['threadItemId']);
        $message = json_decode($result, true);

        $request = [
            'method' => 'messageInDirectHasBenDeleted',
            'payload' => [
                'messageId' => $payload['messageId'],
                'threadItemId' => $payload['threadItemId'],
                'threadId' => $payload['threadId'],
                'conversationId' => $payload['conversationId'],
                'appealId' => $payload['appealId'],
                'result' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Deleted message %s in direct %s', $payload['threadItemId'], $payload['threadId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function getPreferences(array $payload): void
    {
        $this->logger->info(sprintf('Request preferences from server'), $payload);

        $response = $this->instagram->push->getPreferences();

        $message = json_decode($response, true);

        $request = [
            'method' => 'updatePreferences',
            'payload' => [
                'response' => $message
            ]
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Requested preferences from server'), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendMediaToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send media to user %s', $payload['accountId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $recipients = [];
        $recipients['thread'] = $payload['threadId'];
        $result = $this->instagram->direct->sendPost($recipients, $payload['mediaId'] ,[
            'client_context' => $payload['messageId'],
            'media_type' => $payload['mediaType']
        ]);

        $message = json_decode($result, true);

        $request = [
            'method' => 'postToDirectHasBenDelivery',
            'payload' => [
                'threadId' => $payload['threadId'],
                'messageId' => $payload['messageId'],
                'conversationId' => $payload['conversationId'],
                'result' => $message
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Sent media to user %s', $payload['threadId']), $payload);
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function deleteComment(array $payload): void
    {
        $this->logger->info(sprintf('Deleting comment %s from  %s', $payload['commentId'], $payload['mediaId']), $payload);

        $result = $this->instagram->media->deleteComment($payload['mediaId'] ,$payload['commentId']);

        $message = json_decode($result, true);

        $request = [
            'method' => 'commentWasDeleted',
            'payload' => [
                'commentId' => $payload['commentId'],
                'mediaId' => $payload['mediaId'],
                'messageId' => $payload['messageId'],
                'result' => $message
            ]
        ];

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info(sprintf('Deleted comment %s from  %s', $payload['commentId'], $payload['mediaId']), $payload);
    }
}