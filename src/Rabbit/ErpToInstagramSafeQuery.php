<?php


namespace App\Rabbit;

use GepurIt\RabbitMqBundle\Configurator\SimpleDeadDeferredConfigurator;
use GepurIt\RabbitMqBundle\RabbitInterface;

/**
 * Class ErpToInstagramQuery
 * @package App\Rabbit
 */
class ErpToInstagramSafeQuery extends SimpleDeadDeferredConfigurator
{
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