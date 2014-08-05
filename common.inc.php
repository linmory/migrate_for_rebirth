<?php

function saveData($data)
{
    $str = '<?php'.PHP_EOL.'return ';
    $str .= var_export($data,TRUE);
    $str .= ';';

    file_put_contents('./data.log',$str);
}

function getData()
{
    if(file_exists('./data.log')){
        $data = include './data.log';
    }

    if(empty($data)) $data = array();
    return $data;
}