<?php


namespace App\Rabbit;


use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use GepurIt\RabbitMqBundle\Configurator\SimpleDeadDeferredConfigurator;
use GepurIt\RabbitMqBundle\RabbitInterface;
use InstagramAPI\Push\Fbns;

/**
 * Class ErpToInstagramQuery
 * @package App\Rabbit
 */
class ErpToInstagramQuery extends SimpleDeadDeferredConfigurator implements EventEmitterInterface
{
    use EventEmitterTrait;

    const QUEUE_NAME          = 'erp_to_instagram';
    const QUEUE_NAME_DEFERRED = 'erp_to_instagram_deferred';

    /**
     * ErpToInstagramQuery constructor.
     * @param RabbitInterface $rabbit
     */
    public function __construct(RabbitInterface $rabbit)
    {
        parent::__construct($rabbit, self::QUEUE_NAME, self::QUEUE_NAME_DEFERRED, 600000);
    }
}