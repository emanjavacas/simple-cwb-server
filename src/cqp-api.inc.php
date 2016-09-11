<?php

require_once('api.class.php');
require_once('cqp-client.class.php');

/**
 * CQPApi class
 *
 */

class CQPApi extends API
{
    protected $cqp;
    
    public function __construct($request, $origin)
    {// args to CQPClient should be read from env
        $this->cqp = new CQPClient("/usr/local/bin/", "/usr/local/share/cwb/registry");
        parent::__construct($request);
    }

    protected function validate_attrs($input_attrs, $real_attrs, $base_attrs = ['word'])
    {
        $attrs = $input_attrs ? $input_attrs : Array();
        foreach ($attrs as $attr) {
            if (!in_array($attr, $real_attrs)) {
                throw new Exception("Invalid attribute [$attr]");
            }
        }
        return array_unique(array_merge($base_attrs, $attrs));
    }

    protected function query()
    {
        
        if ($this->method == 'GET') {
            $corpus = $this->verb;
            $query = $_GET["query"];
            $window = $_GET["window"] ? $_GET["window"] : 5;
            $from = $_GET["from"] || $_GET["from"] === 0 ? $_GET["from"] : 0;
            $to = $_GET["to"] || $_GET["to"] === 0 ? $_GET["to"] : 10;
            $real_attrs = $this->cqp->show_p_attrs($corpus);
            $attrs = $this->validate_attrs($_GET["attrs"], $real_attrs);
            return $this->cqp->run_query($query, $corpus, $from, $to, $window, $attrs);
        } else {
            return "Only accept GET requests";
        }
    }

    protected function pattrs()
    {
        if ($this->method == 'GET') {
            $corpus = $this->verb;
            return $this->cqp->show_p_attrs($corpus);
        } else {
            return "Only accept GET requests";
        }
    }

    protected function sattrs()
    {
        if ($this->method == 'GET') {
            $corpus = $this->verb;
            return $this->cqp->show_s_attrs($corpus);
        } else {
            return "Only accept GET requests";
        }
    }

    protected function debug() {
        return $_GET['args'];
    }
}