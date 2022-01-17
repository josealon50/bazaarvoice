<?php

function BazaarAutoload($className){ 
    $array_paths = array(
        'libs',
        'src',
        'db'
    );
    foreach($array_paths as $path)
    {
        $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        $filename = __DIR__ . DIRECTORY_SEPARATOR .$path . DIRECTORY_SEPARATOR . $className . ".php";

        if(is_file($filename))
        {
            include_once $filename;
            break;
        }

    }
}


spl_autoload_register('BazaarAutoload');
