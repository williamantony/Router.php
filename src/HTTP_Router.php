<?php

namespace WA\Router;

use WA\Request\HTTP_Request as Request;

class HTTP_Router
{
    private $request;
    private $routes = array();
    private $templates = array();

    private $last_added_route;
    private $continue = true;

    public function __construct()
    {
        $this->request = new Request();
    }

    public function route(string $template = "/")
    {
        if (!in_array($template, $this->templates))
            array_push($this->templates, $template);

        $this->last_added_route = $template;

        return $this;
    }



    public function listen()
    {
        $template_matches = $this->validate_templates($this->templates, $this->request->path);

        foreach ($this->routes as $route) {
            if (in_array($route->template, $template_matches)) {
                if ($this->request->method == $route->method || $route->method === "USE")
                    $this->runRouteHandler($route);
            }
        }
    }



    public function use(...$handlers)
    {
        return $this->setHandlers("USE", ...$handlers);
    }

    public function get(...$handlers)
    {
        return $this->setHandlers("GET", ...$handlers);
    }

    public function post(...$handlers)
    {
        return $this->setHandlers("POST", ...$handlers);
    }

    public function put(...$handlers)
    {
        return $this->setHandlers("PUT", ...$handlers);
    }

    public function patch(...$handlers)
    {
        return $this->setHandlers("PATCH", ...$handlers);
    }

    public function delete(...$handlers)
    {
        return $this->setHandlers("DELETE", ...$handlers);
    }



    private function setHandlers($method = "GET", ...$handlers)
    {
        foreach ($handlers as $handler)
        {
            $route = (object) array(
                "method" => $method,
                "template" => $this->last_added_route,
                "handler" => $handler,
            );
    
            array_push($this->routes, $route);
        }

        return $this;
    }

    private function runRouteHandler(object $route)
    {
        if (!$this->continue)
            return false;

        $this->continue = false;

        static::match($this->request->path, $route->template, $params);

        $next = function() {
            $this->continue = true;
        };

        $request = (object) array(
            "params" => $params,
            "data" => $this->request->params,
            "body" => $this->request->body,
        );

        $cb = $route->handler;

        if ($return = $cb($request, $next)) {
            static::printResponse($return);
        }
    }

    /**
     * Helper Methods related to Router
     */

    static private function split(string $path = '')
    {
        $regex = '/[:]?[A-Za-z0-9\-_]+/';

        if (preg_match_all($regex, $path, $results))
            return $results[0];

        return null;
    }

    static private function match(string $path, string $template, &$matches = array())
    {
        $matches = array();

        $path = static::split($path);
        $template = static::split($template);

        if (!is_array($path) || !is_array($template))
            return false;

        if (count($path) !== count($template))
            return false;

        for ($i = 0; $i < count($path); $i++)
        {
            $param_name = ($template[$i][0] === ":") ? substr($template[$i], 1) : null;

            if (is_string($param_name))
                $matches[$param_name] = $path[$i];

            if (is_null($param_name) && ($path[$i] != $template[$i]))
                return false;
        }

        return true;
    }

    static private function validate_templates(array $templates, string $path)
    {
        if (in_array($path, $templates))
            return array( $path );
        
        $matches = array();

        foreach ($templates as $template)
        {
            if (static::match($path, $template))
                array_push($matches, $template);
        }

        return $matches;
    }

    static private function printResponse($response)
    {
        try {
            print_r(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Throwable $th) {
            print_r($th);
        }
    }

}
