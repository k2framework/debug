<?php

namespace K2\Debug\Service;

use KumbiaPHP\View\View;
use KumbiaPHP\Kernel\Event\ResponseEvent;
use KumbiaPHP\Kernel\Session\SessionInterface;
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

    /**
     *
     * @var View 
     */
    protected $view;

    /**
     *
     * @var SessionInterface 
     */
    protected $session;

    function __construct(View $view, SessionInterface $session)
    {
        $this->view = $view;
        $this->session = $session;
    }

    public function onResponse(ResponseEvent $event)
    {
        if (function_exists('mb_stripos')) {
            $posrFunction = 'mb_strripos';
            $substrFunction = 'mb_substr';
        } else {
            $posrFunction = 'strripos';
            $substrFunction = 'substr';
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if (false !== $pos = $posrFunction($content, '</body>')) {

            $html = $this->view->render('K2/Debug:banner', null, array(
                        'queries' => $this->session->all('k2_debug_queries')
                    ))->getContent();
            
            $this->session->delete(null,'k2_debug_queries');

            $content = $substrFunction($content, 0, $pos) . $html . $substrFunction($content, $pos);
            $response->setContent($content);
        }
    }

    public function onBeforeQuery(BeforeQueryEvent $event)
    {
        $this->queryTimeInit = microtime();
    }

    public function onAfterQuery(AfterQueryEvent $event)
    {
        $this->addQuery($event, microtime() - $this->queryTimeInit);
    }

    protected function addQuery(AfterQueryEvent $event, $runtime)
    {
        //$this->queries[] = array(
        $data = array(
            'runtime' => $runtime,
            'query' => $event->getQuery(),
            'parameters' => $event->getParameters(),
            'type' => $event->getQueryType(),
            'result' => $event->getResult(),
        );
        $this->session->set(md5(microtime()), $data, 'k2_debug_queries');
    }

}
