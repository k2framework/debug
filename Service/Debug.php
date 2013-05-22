<?php

namespace K2\Debug\Service;

use K2\Kernel\App;
use K2\Kernel\Kernel;
use \Twig_Environment;
use K2\Kernel\Collection;
use K2\Kernel\Event\ResponseEvent;
use ActiveRecord\Event\QueryEvent;
use K2\Kernel\Session\SessionInterface;
use K2\Security\Acl\Role\RoleInterface;

/**
 * Description of Debug
 *
 * @author maguirre
 */
class Debug
{

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
        $this->session->set(App::getRequest()->getRequestUrl() . ' (' . date('d-m-Y H:i:s') . ')'
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
                                
                $dataQueries['count'] = $this->session->get('numQueries','k2_debug_queries');
                $this->session->delete('numQueries','k2_debug_queries');
                $dataQueries['queries'] = $this->session->all('k2_debug_queries');
                
                $this->session->delete(null, 'k2_debug_queries');

                $html = $this->twig->render('@K2Debug/banner.twig', array(
                    'queries' => $dataQueries,
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

                $content = $substrFunction($content, 0, $pos) . $html . $substrFunction($content, $pos);
                $response->setContent($content);
            }
        }
    }

    public function onQuery(QueryEvent $event)
    {
        if (!App::getRequest()->isAjax()) {
            $this->addQuery($event);
        }
    }

    public function dump($title, $var)
    {
        if (isset($this->dumps[$title])) {
            $title .= '_' . microtime();
        }
        $this->dumps[$title] = $var;
    }

    protected function addQuery(QueryEvent $event)
    {
        $numQueries = (int) $this->session->get('numQueries', 'k2_debug_queries');
        $data = array(
            'sql' => $event->getStatement()->getSqlQuery(),
            'type' => $event->getQueryType(),
            'result' => $event->getResult(),
        );
        $this->queries->set(++$numQueries, $data);
        $this->session->set('numQueries', $numQueries, 'k2_debug_queries');
    }

}
