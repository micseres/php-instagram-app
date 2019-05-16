<?php


namespace App\Processor\Erp;

use App\Rabbit\InstagramToErpQuery;
use InstagramAPI\Instagram;
use \InstagramAPI\Realtime;
use InstagramAPI\Realtime\Payload\Action\AckAction;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Class CommandProcessor
 * @package App\Processor\Erp
 */
class CommandProcessor
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
     * CommandProcessor constructor.
     * @param Realtime $realtime
     * @param LoopInterface $loop
     * @param Instagram $instagram
     * @param InstagramToErpQuery $instagramToErpQuery
     */
    public function __construct(
        Realtime $realtime,
        LoopInterface $loop,
        Instagram $instagram,
        InstagramToErpQuery $instagramToErpQuery
    ) {
        $this->realtime = $realtime;
        $this->loop = $loop;
        $this->instagram = $instagram;
        $this->instagramToErpQuery = $instagramToErpQuery;
    }

    /**
     * @param $context
     * @return PromiseInterface
     */
    protected function handleRealtimeContext($context): PromiseInterface
    {
        var_dump($context);
        $deferred = new Deferred();
        $this->contexts[$context] = $deferred;

        $timeout = $this->loop->addTimer(self::TIMEOUT, function () use ($deferred, $context) {
            $deferred->reject();
            unset($this->contexts[$context]);
        });

        return $deferred->promise()
            ->then(function (AckAction $ack) use ($timeout) {
                var_dump(1);
                var_dump($ack->getPayload()->asJson());
                $timeout->cancel();
                return true;
            })
            ->otherwise(function ($result) {
                var_dump(2);
                var_dump($result);
                return false;
            });
    }

    /**
     * @param array $payload
     */
    public function ping(array $payload): void
    {
        $this->handleRealtimeContext(printf('[Command] ping%s', PHP_EOL))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function indicateActivityInDirectThread(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->indicateActivityInDirectThread($payload['threadId'], (bool) $payload['flag']))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendTextToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendTextToDirect($payload['threadId'], $payload['text']))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendPostToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendPostToDirect($payload['threadId'], $payload['storyId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendStoryToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendStoryToDirect($payload['threadId'], $payload['storyId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendProfileToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendProfileToDirect($payload['threadId'], $payload['locationId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendLocationToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendLocationToDirect($payload['threadId'], $payload['locationId'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendHashtagToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendHashtagToDirect($payload['threadId'], $payload['hashtag'], [
            'text' => isset($payload['text']) ? $payload['text'] : null,
        ]))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendLikeToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendLikeToDirect($payload['threadId']))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function sendReactionToDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->sendReactionToDirect($payload['threadId'], $payload['threadItemId'], 'like'))->then(function ($result) {
            var_dump($result);
        });
    }

    /**
     * @param array $payload
     */
    public function deleteReactionFromDirect(array $payload): void
    {
        $this->handleRealtimeContext($this->realtime->deleteReactionFromDirect($payload['threadId'], $payload['threadItemId'], 'like'))->then(function ($result) {
            var_dump($result);
        });
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
        $mediaInfo = $this->instagram->media->getInfo($payload['mediaId'])->getHttpResponse()->getBody();

        $message = json_decode($mediaInfo, true);

        var_dump($message);

        $request = [
            'method' => 'getMediaInfo',
            'payload' => $message
        ];

        $this->instagramToErpQuery->publish(json_encode($request));
    }
}