<?php

namespace K2\Debug;

use K2\Kernel\Module;
use K2\Kernel\Event\K2Events as E;
use ActiveRecord\Event\Events as AE;

class K2DebugModule extends Module
{

    public function init()
    {
        $this->container->set('k2_debug', function($c) {
                    $debug = new Service\Debug($c);
                });

        $this->dispatcher->addListener(E::RESPONSE, array('k2_debug', 'onResponse'));
        //$this->dispatcher->addListener(AE::, array('k2_debug', 'onBeforeQuery'));
        //$this->dispatcher->addListener(AE::, array('k2_debug', 'onAfterQuery'));
    }

}