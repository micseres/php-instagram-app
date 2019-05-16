<?php


namespace App\Rabbit;


use GepurIt\RabbitMqBundle\Configurator\SimpleDeadDeferredConfigurator;
use GepurIt\RabbitMqBundle\RabbitInterface;

/**
 * Class InstagramToErpQuery
 * @package App\Rabbit
 */
class InstagramToErpQuery extends SimpleDeadDeferredConfigurator
{
    const QUEUE_NAME          = 'instagram_to_erp';
    const QUEUE_NAME_DEFERRED = 'instagram_to_erp_deferred';

    /**
     * ErpToInstagramQuery constructor.
     * @param RabbitInterface $rabbit
     */
    public function __construct(RabbitInterface $rabbit)
    {
        parent::__construct($rabbit, self::QUEUE_NAME, self::QUEUE_NAME_DEFERRED, 600000);
    }
}