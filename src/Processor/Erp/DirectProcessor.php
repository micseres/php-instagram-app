<?php


namespace App\Processor\Erp;

use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Instagram;
use InstagramAPI\Realtime;
use InstagramAPI\Realtime\Payload\Action\AckAction;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Class DirectProcessor
 * @package Processor\Erp
 */
class DirectProcessor
{
    const TIMEOUT = 5;

    /**
     * @var Realtime
     */
    private $realtime;

    /** @var Deferred[] */
    protected $contexts;
    /**
     * @var LoopInterface
     */
    private $loop;
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
     * @param Realtime $realtime
     * @param LoopInterface $loop
     * @param Instagram $instagram
     * @param InstagramToErpQuery $instagramToErpQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        Realtime $realtime,
        LoopInterface $loop,
        Instagram $instagram,
        InstagramToErpQuery $instagramToErpQuery,
        LoggerInterface $logger
    )
    {
        $this->realtime = $realtime;
        $this->loop = $loop;
        $this->instagram = $instagram;
        $this->instagramToErpQuery = $instagramToErpQuery;
        $this->logger = $logger;
    }

    /**
     * Called when ACK has been received.
     *
     * @param AckAction $ack
     */
    public function clientContextAck(AckAction $ack)
    {
        $context = $ack->getPayload()->getClientContext();
        $this->logger->info(sprintf('Received ACK for %s with status %s', $context, $ack->getStatus()));

        if (!isset($this->contexts[$context])) {
            return;
        }

        $deferred = $this->contexts[$context];
        $deferred->resolve($ack);

        unset($this->contexts[$context]);
    }

    /**
     * @param $context
     * @return PromiseInterface
     */
    protected function handleRealtimeContext($context): PromiseInterface
    {
        $deferred = new Deferred();
        $this->contexts[$context] = $deferred;

        $timeout = $this->loop->addTimer(self::TIMEOUT, function () use ($deferred, $context) {
            $deferred->reject();
            unset($this->contexts[$context]);
        });

        return $deferred->promise()
            ->then(function (AckAction $ack) use ($timeout, $context) {
                $timeout->cancel();
                $this->logger->info(sprintf('Resolve ACK for context %s with status %s', $context, $ack->getStatus()));

                return $ack->asArray();
            })
            ->otherwise(function ($result) use ($context) {
                $this->logger->info(sprintf('Reject ACK for context %s', $context));

                return false;
            });
    }

    /**
     * @param array $payload
     */
    public function indicateActivityInDirectThread(array $payload): void
    {
        $this->logger->info(sprintf('Indicate activity in direct thread %s %b', $payload['threadId'], (bool)$payload['flag']), $payload);

        $this->handleRealtimeContext($this->realtime->indicateActivityInDirectThread($payload['threadId'], (bool)$payload['flag']))
            ->then(function ($result) use ($payload) {
                $this->logger->info(sprintf('Ack indicate activity in direct thread %s %b', $payload['threadId'], (bool)$payload['flag']), $result);
            });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendTextToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send text to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($context = $this->realtime->sendTextToDirect($payload['threadId'], $payload['text'], [
            'client_context' => $payload['messageId']
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send text to direct thread %s', $payload['threadId']), $result);

        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendPostToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send media to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendPostToDirect($payload['threadId'], $payload['mediaId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send media to direct thread %s', $payload['threadId']), $result);
        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendStoryToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send story to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendStoryToDirect($payload['threadId'], $payload['storyId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send story to direct thread %s', $payload['threadId']), $result);
        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendProfileToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send profile to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendProfileToDirect($payload['threadId'], $payload['locationId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send profile to direct thread %s', $payload['threadId']), $result);
        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendLocationToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send location to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendLocationToDirect($payload['threadId'], $payload['locationId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send profile to direct thread %s', $payload['threadId']), $result);
        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendHashtagToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send hashtag to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendHashtagToDirect($payload['threadId'], $payload['hashtag'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) use ($payload) {
            $request = [
                'method' => 'messageToDirectHasBenDelivery',
                'payload' => [
                    'conversationId' => $payload['conversationId'],
                    'messageId' => $payload['messageId'],
                    'threadId' =>  $payload['threadId'],
                    'result' => $result
                ]
            ];

            $this->instagramToErpQuery->publish(json_encode($request));

            $this->logger->info(sprintf('Ack send hashtag to direct thread %s', $payload['threadId']), $result);
        });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendLikeToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send like to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendLikeToDirect($payload['threadId'], ['client_context' => $payload['messageId']]))
            ->then(function ($result) use ($payload) {
                $request = [
                    'method' => 'messageToDirectHasBenDelivery',
                    'payload' => [
                        'conversationId' => $payload['conversationId'],
                        'messageId' => $payload['messageId'],
                        'threadId' =>  $payload['threadId'],
                        'result' => $result
                    ]
                ];

                $this->instagramToErpQuery->publish(json_encode($request));

                $this->logger->info(sprintf('Ack send like to direct thread %s', $payload['threadId']), $result);
            });
    }

    /**
     * @param array $payload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function sendReactionToDirect(array $payload): void
    {
        $this->logger->info(sprintf('Send reaction to direct thread %s', $payload['threadId']), $payload);

        $request = [
            'method' => 'messageToDirectHasBenSend',
            'payload' => [
                'conversationId' => $payload['conversationId'],
                'messageId' => $payload['messageId'],
            ]
        ];

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->handleRealtimeContext($this->realtime->sendReactionToDirect($payload['threadId'], $payload['threadItemId'], 'like'))
            ->then(function ($result) use ($payload) {
                $request = [
                    'method' => 'messageToDirectHasBenDelivery',
                    'payload' => [
                        'conversationId' => $payload['conversationId'],
                        'messageId' => $payload['messageId'],
                        'threadId' =>  $payload['threadId'],
                        'result' => $result
                    ]
                ];

                $this->instagramToErpQuery->publish(json_encode($request));

                $this->logger->info(sprintf('Ack send reaction to direct thread %s', $payload['threadId']), $result);
            });
    }

    /**
     * @param array $payload
     */
    public function deleteReactionFromDirect(array $payload): void
    {
        $this->logger->info(sprintf('Delete reaction to direct thread %s', $payload['threadId']), $payload);

        $this->handleRealtimeContext($this->realtime->deleteReactionFromDirect($payload['threadId'], $payload['threadItemId'], 'like'))
            ->then(function ($result) use ($payload) {
                $this->logger->info(sprintf('Ack delete reaction to direct thread %s', $payload['threadId']), $result);
            });
    }
}