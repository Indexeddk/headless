<?php
spl_autoload_register('nameSpaceLoader');

function nameSpaceLoader($cls)
{
    $cls = trim($cls, "\\");

    $reverseCls = str_replace('\\', DIRECTORY_SEPARATOR, $cls);

    $file = __DIR__.'/src/'.$reverseCls.'.php';

    if (is_readable($file)) {
        include_once $file;
    }
}