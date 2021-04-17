<?php

if (!function_exists('d')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function d($var, ...$moreVars)
    {
        \Symfony\Component\VarDumper\VarDumper::dump($var);

        foreach ($moreVars as $v) {
            \Symfony\Component\VarDumper\VarDumper::dump($v);
        }

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dd')) {
    function dd(...$vars)
    {
        foreach ($vars as $v) {
            \Symfony\Component\VarDumper\VarDumper::dump($v);
        }

        exit(1);
    }
}