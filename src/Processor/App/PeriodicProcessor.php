<?php


namespace App\Processor\App;


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

        $this->instagramToErpQuery->publish(json_encode($request));

        $this->logger->info('Got recent activity inbox', $message);
    }
}