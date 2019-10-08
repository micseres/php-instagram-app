<?php
declare(strict_types=1);

namespace App\Rabbit;

use GepurIt\RabbitMqBundle\Configurator\SimpleDeadDeferredConfigurator;
use GepurIt\RabbitMqBundle\RabbitInterface;

/**
 * Class InstagramToErpMediaQuery
 * @package Chat\InstagramBundle\Rabbit
 */
class InstagramToErpMediaQuery extends SimpleDeadDeferredConfigurator
{
    const QUEUE_NAME          = 'instagram_to_erp_media';
    const QUEUE_NAME_DEFERRED = 'instagram_to_erp_media_deferred';

    /**
     * ErpToInstagramQuery constructor.
     * @param RabbitInterface $rabbit
     */
    public function __construct(RabbitInterface $rabbit)
    {
        parent::__construct($rabbit, self::QUEUE_NAME, self::QUEUE_NAME_DEFERRED, 600000);
    }
}