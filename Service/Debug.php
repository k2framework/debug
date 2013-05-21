<?php

namespace K2\Debug\Service;

use K2\Kernel\App;
use K2\Kernel\Kernel;
use \Twig_Environment;
use K2\Kernel\Collection;
use K2\Kernel\Event\ResponseEvent;
use K2\Kernel\Session\SessionInterface;
use K2\Security\Acl\Role\RoleInterface;
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
     * @var Twig_Environment 
     */
    protected $twig;

    /**
     *
     * @var SessionInterface 
     */
    protected $session;

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

    function __construct()
    {
        $this->twig = App::get('twig');
        $this->queries = new Collection();
        $this->session = App::get('session');
        $this->session->set(App::getRequest()->getRequestUrl()
                , $this->queries, 'k2_debug_queries');
    }

    public function onResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $request = App::getRequest();

        if (Kernel::MASTER_REQUEST === $request->getType() && !$request->isAjax() &&
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

                if (App::get('security')->isLogged()) {
                    $token = App::get('security')->getToken();
                    $tokenAttrs = array_merge((array) $token->getAttributes(), get_object_vars($token->getUser()));
                    $roles = array_map(function($rol) {
                                return $rol instanceof RoleInterface ? $rol->getName() : $rol;
                            }, (array) $token->getRoles());
                    $userClass = get_class($token->getUser());
                } else {
                    $token = null;
                    $tokenAttrs = array();
                    $roles = array();
                    $userClass = null;
                }

                $html = $this->twig->render('@K2Debug/banner.twig', array(
                    'queries' => $this->session->all('k2_debug_queries'),
                    'dumps' => $this->dumps,
                    'headers' => $response->headers->all(),
                    'status' => $response->getStatusCode(),
                    'charset' => $response->getCharset(),
                    'token' => $token,
                    'token_attrs' => $tokenAttrs,
                    'roles' => $roles,
                    'user_class' => $userClass,
                    'tiempo' => round((microtime(1) - START_TIME), 4),
                    'memoria' => number_format(memory_get_usage() / 1048576, 2),
                ));

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
