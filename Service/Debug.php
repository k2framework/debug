<?php

namespace K2\Debug\Service;

use KumbiaPHP\Kernel\Event\ResponseEvent;
use KumbiaPHP\ActiveRecord\Event\AfterQueryEvent;
use KumbiaPHP\ActiveRecord\Event\BeforeQueryEvent;

/**
 * Description of Debug
 *
 * @author maguirre
 */
class Debug
{

    protected $queries;
    protected $queryTimeInit;

    public function onResponse(ResponseEvent $event)
    {
        //var_dump($this->queries);die;
    }

    public function onBeforeQuery(BeforeQueryEvent $event)
    {
        $this->queryTimeInit = microtime();
    }

    public function onAfterQuery(AfterQueryEvent $event)
    {
        //$this->addQuery($event, microtime() - $this->queryTimeInit);
    }

    protected function addQuery(AfterQueryEvent $event, $runtime)
    {
        $this->queries[] = array(
            'runtime' => $runtime,
            'query' => $event->getQuery(),
            'parameters' => $event->getParameters(),
            'type' => $event->getQueryType(),
            'result' => $event->getResult(),
        );
    }

}
