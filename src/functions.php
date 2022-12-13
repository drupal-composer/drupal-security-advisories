<?php

namespace App;

function time(): int
{
    static $time;

    if (!$time) {
        $time = \time();
    }
    return $time;
}
