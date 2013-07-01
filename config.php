<?php

namespace K2\Backend;

use K2\Debug\Service\Debug;
use K2\Kernel\Event\K2Events as E;
use ActiveRecord\Event\Events as AREvents;

return array(
    'name' => 'K2Debug',
    'namespace' => __NAMESPACE__,
    'path' => __DIR__,
    'services' => array(
        'k2_debug' => array(
            'callback' => function() {
                \K2\Kernel\App::addSerciveToRequest('k2_debug');
                return new Debug();
            },
            'tags' => array(
                array('name' => 'event.listener', 'event' => E::RESPONSE, 'method' => 'onResponse'),
                array('name' => 'event.listener', 'event' => AREvents::QUERY, 'method' => 'onQuery'),
            ),
        ),
    )
);


