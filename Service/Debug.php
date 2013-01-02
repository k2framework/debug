<?php

namespace K2\Debug\Service;

use K2\View\View;
use K2\Kernel\Request;
use K2\Kernel\Collection;
use K2\Security\Security;
use K2\Kernel\KernelInterface;
use K2\Kernel\Event\ResponseEvent;
use K2\Security\Event\SecurityEvent;
use K2\Kernel\Session\SessionInterface;
use K2\Di\Container\ContainerInterface;
use K2\ActiveRecord\Event\AfterQueryEvent;
use K2\ActiveRecord\Event\BeforeQueryEvent;

/**
 * Description of Debug
 *
 * @author maguirre
 */
class Debug
{

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

    /**
     *
     * @var Request 
     */
    protected $request;

    /**
     *
     * @var array 
     */
    protected $dumps;

    /**
     *
     * @var Collection
     */
    protected $queries;

    function __construct(ContainerInterface $container)
    {
        $this->view = $container->get('view');
        $this->session = $container->get('session');
        $this->request = $container->get('request');
        $this->queries = new Collection();
        $this->session->set($this->request->getRequestUrl()
                , $this->queries, 'k2_debug_queries');
    }

    public function onResponse(ResponseEvent $event)
    {
        /* @var $response \K2\Kernel\Response */
        $response = $event->getResponse();
        if (KernelInterface::MASTER_REQUEST === $this->request->getAppContext()
                        ->getRequestType() && !$this->request->isAjax() &&
                !$response instanceof \K2\Kernel\RedirectResponse) {


            //preguntamos si el Content-Type de la respuesta es diferente de text/html
            if (0 !== strpos($response->headers->get('Content-Type', 'text/html'), 'text/html')) {
                //si no es un html lo que se responde no insertamos el banner
                return;
            }

            if (function_exists('mb_stripos')) {
                $posrFunction = 'mb_strripos';
                $substrFunction = 'mb_substr';
            } else {
                $posrFunction = 'strripos';
                $substrFunction = 'substr';
            }

            $content = $response->getContent();

            if (false !== $pos = $posrFunction($content, '</body>')) {

                $html = $this->view->render(
                                array(
                                    'template' => 'K2/Debug:banner',
                                    'params' => array(
                                        'queries' => $this->session->all('k2_debug_queries'),
                                        'dumps' => $this->dumps,
                                        'headers' => $response->headers->all(),
                                        'status' => $response->getStatusCode(),
                                        'charset' => $response->getCharset(),
                                    ),
                        ))->getContent();

                $this->session->delete(null, 'k2_debug_queries');

                $content = $substrFunction($content, 0, $pos) . $html . $substrFunction($content, $pos);
                $response->setContent($content);
            }
        }
    }

    public function onBeforeQuery(BeforeQueryEvent $event)
    {
        $this->queryTimeInit = microtime();
    }

    public function onAfterQuery(AfterQueryEvent $event)
    {
        if (!$this->request->isAjax()) {
            $this->addQuery($event, microtime() - $this->queryTimeInit);
        }
    }

    public function dump($title, $var)
    {
        if (isset($this->dumps[$title])) {
            $title .= '_' . microtime();
        }
        $this->dumps[$title] = $var;
    }

    protected function addQuery(AfterQueryEvent $event, $runtime)
    {
        $numQueries = (int) $this->session->get('numQueries', 'k2_debug_queries');
        $data = array(
            'runtime' => $runtime,
            'query' => $event->getQuery(),
            'parameters' => $event->getParameters(),
            'type' => $event->getQueryType(),
            'result' => $event->getResult(),
        );
        $this->queries->set(++$numQueries, $data);
        $this->session->set('numQueries', $numQueries, 'k2_debug_queries');
    }

}
