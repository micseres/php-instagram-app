<?php


namespace App\Processor\App;


use App\Rabbit\InstagramToErpMediaQuery;
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
    private $instagramToErpMediaQuery;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CommandProcessor constructor.
     * @param Instagram $instagram
     * @param InstagramToErpMediaQuery $instagramToErpMediaQuery
     * @param LoggerInterface $logger
     */
    public function __construct(
        Instagram $instagram,
        InstagramToErpMediaQuery $instagramToErpMediaQuery,
        LoggerInterface $logger
    )
    {
        $this->instagram = $instagram;
        $this->instagramToErpMediaQuery = $instagramToErpMediaQuery;
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

        $this->instagramToErpMediaQuery->publish(json_encode($request));

        $this->logger->info('Got recent activity inbox', $message);
    }
}