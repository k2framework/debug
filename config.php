<?php

namespace K2\Backend;

use K2\Debug\Service\Debug;
use K2\Kernel\Event\K2Events as E;

return array(
    'name' => 'K2Debug',
    'namespace' => __NAMESPACE__,
    'path' => __DIR__,
    'services' => array(
        'k2_debug' => function() {
            return new Debug();
        },
    ),
    'listeners' => array(
        E::RESPONSE => array(
            array('k2_debug', 'onResponse')
        ),
    ),
);


