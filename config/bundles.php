<?php

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use GepurIt\RabbitMqBundle\RabbitMqBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle;

return [
    MonologBundle::class => ['all' => true],
    SwiftmailerBundle::class => ['all' => true],
    FrameworkBundle::class => ['all' => true],
    RabbitMqBundle::class => ['all' => true],
];
