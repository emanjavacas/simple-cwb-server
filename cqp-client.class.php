<?php

require("cqp.inc.php");

/**
 * General utility functions
 */
function startswith($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
}

function endswith($string, $suffix) {
    $len = strlen($string);
    return substr($string, $len - strlen($suffix), $len) === $suffix;
}

/**
 * Print utility functions (mainly for testing purposes)
 */

function print_available_corpora($cqp) {
    $corpora = $cqp->available_corpora();
    print "Available corpora\n";
    print "-----------------\n";
    print join("\n", $corpora);
    print "\n\n";
}

function print_results($results) {
    print "Results\n";
    print "-------\n";
    foreach ($results as $result) {
        print "$result\n";
    }
    print "\n\n";
}

/**
 * JSON utility functions on results by cqp.client
 */

function hit_to_json($attrs) {
    $f = function ($result) use ($attrs) {
        $split = explode("\t", $result);
        $out = [];
        for ($i = 0; $i < count($attrs); $i++) {
            $out[$attrs[$i]] = array_slice($split, $i * 3, 3);
        }
        $out["_scope"] = end($split);
        return $out;
    };
    return $f;
}

function window_to_json($results, $attrs) {
    return array_map(hit_to_json($attrs), $results);
}

/**
 * CQPClient class
 */

class CQPClient
{
    private $cqp;
    private $corpus;
    private $key;

    public function __construct($path_to_cqp, $cwb_registry)
    {
        $this->cqp = new CQP($path_to_cqp, $cwb_registry);
    }

    private function query_results($query_name, $from, $to, $window, $attrs)
    {
        if (in_array("_scope", $attrs)) {
            throw new Exception("_scope can't be an attribute");
        }
        $to = $to - 1; // cqp takes inclusive ranges
        $cmd = "tabulate $query_name $from $to";
        foreach ($attrs as $attr) {
            $cmd = $cmd .
                 " match[-$window]..match[-1] $attr," .     // left side
                 " match $attr," .                          // match
                 " matchend[1]..matchend[$window] $attr,";  // right side
        }
        $cmd = rtrim($cmd, ",") . ";";
        return $this->cqp->execute($cmd);
    }

    private function show_attrs($corpus, $filter_prefix = "p-Att")
    {
        if (!$filter_prefix === "p-Att") {
            throw new Exception($filter_prefix);
        }
        $this->ensure_corpus($corpus);
        $attrs = $this->cqp->execute("show cd");
        $attrs = array_filter($attrs, function ($attr) use ($filter_prefix) {
            return startswith($attr, $filter_prefix);
        });
        $attrs = array_map(
            function($attr) {
                return explode("\t", $attr)[1];
            },
            $attrs);
        return array_values($attrs); // remove array index reference (JSON)
    }

    public function show_p_attrs($corpus)
    {
        return $this->show_attrs($corpus);
    }

    public function show_s_attrs($corpus)
    {
        return $this->show_attrs($corpus, $filter_prefix = "s-Att");
    }

    private function word_ids($query_name, $from, $to)
    {
        return $this->cqp->dump($query_name, $from = $from, $to = $to);
    }

    private function with_word_ids($query_name, $from, $to, $window, $attrs)
    {
        $results = $this->query_results($query_name, $from, $to, $window, $attrs);
        $ids = $this->word_ids($query_name, $from, $to);
        for ($i = 0; $i < count($results); $i++) {
            $id = join(" ", $ids[$i]);
            $results[$i] = $results[$i] . "\t$id";
        }
        return $results;
    }

    private function ensure_corpus($corpus)
    {
        if ($corpus != $this->corpus) {
            $this->corpus = $corpus;
            $this->cqp->set_corpus($corpus);
        }
        return;
    }

    public function run_query($query, $corpus, $from, $to, $window, $attrs)
    {
        $this->ensure_corpus($corpus);
        $this->cqp->query($query);
        $results = $this->with_word_ids("Last", $from, $to, $window, $attrs);
        return window_to_json($results, $attrs);
    }
}
