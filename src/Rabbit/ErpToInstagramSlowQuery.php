<?php


namespace App\Rabbit;


use GepurIt\RabbitMqBundle\Configurator\SimpleDeadDeferredConfigurator;
use GepurIt\RabbitMqBundle\RabbitInterface;

/**
 * Class ErpToInstagramSlowQuery
 * @package App\Rabbit
 */
class ErpToInstagramSlowQuery extends SimpleDeadDeferredConfigurator
{
    const QUEUE_NAME          = 'erp_to_instagram_slow';
    const QUEUE_NAME_DEFERRED = 'erp_to_instagram_slow_deferred';

    /**
     * ErpToInstagramQuery constructor.
     * @param RabbitInterface $rabbit
     */
    public function __construct(RabbitInterface $rabbit)
    {
        parent::__construct($rabbit, self::QUEUE_NAME, self::QUEUE_NAME_DEFERRED, 600000);
    }
}