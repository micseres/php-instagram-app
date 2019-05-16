<?php

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use GepurIt\RabbitMqBundle\RabbitMqBundle;

return [
    FrameworkBundle::class => ['all' => true],
    RabbitMqBundle::class => ['all' => true],
];
