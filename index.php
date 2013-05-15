<?php
/**
 * EvaThumber
 * URL based image transformation php library
 *
 * @link      https://github.com/AlloVince/EvaThumber
 * @copyright Copyright (c) 2012-2013 AlloVince (http://avnpc.com/)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @author    AlloVince
 */

error_reporting(E_ALL & ~E_NOTICE);

// Check php version
if( version_compare(phpversion(), '5.3.0', '<') ) {
    die(printf('PHP 5.3.0 is required, you have %s', phpversion()));
}


$dir = __DIR__;
$autoloader = $dir . '/vendor/autoload.php';
$localConfig = $dir . '/config.php';

if (file_exists($autoloader)) {
    $loader = include $autoloader;
} else {
    die('Dependent library not found, run "composer install" first.');
}

/** Debug functions */
function p($r, $usePr = false)
{
    echo sprintf("<pre>%s</pre>", var_dump($r));
}

use RedBean_Facade as R;
R::setup('mysql:host=localhost;dbname=wslive', 'root', '123456'); //mysql

$cateMapping = array(
    '股市' => 9493,
    '债市' => 9494,
    '公司' => 9500,
    '商品期货' => 9497,
    '外汇' => 9495,
    '央行' => 9496,
    '时政与官员言论' => 9499,
    '经济' => 9501,
    '经济数据' => 9498,
    '见闻早餐' => 9503,
    '货币市场' => 9502,
);

$locationMapping = array(
    '中东' => 9488,
    '中国' => 9479,
    '亚洲' => 9490,
    '俄罗斯' => 9484,
    '加拿大' => 9482,
    '印度' => 9486,
    '巴西' => 9485,
    '拉美' => 9491,
    '日本' => 9480,
    '欧洲' => 9478,
    '澳洲' => 9492,
    '瑞士' => 9487,
    '美国' => 9477,
    '英国' => 9483,
    '非洲' => 9489,
    '香港' => 9481,
);

$res = Requests::post('http://www.dp.com/apiv1/user/login.json', array(
    'Content-type' => 'application/json'
), json_encode(array('username' => 'AlloVince', 'password' => '123456')));

$loginRes = json_decode($res->body);

function createNode($nid){
    global $loginRes, $locationMapping, $cateMapping;

    $news = R::findOne('news', 'nid = ? ', array($nid));
    echo "News $nid imported\n";

    if(!$news){
        return;
    }

    $tags = R::getAll( "select * from tags where nid = $nid" );
    $ids = array();
    if($tags) {
        foreach($tags as $tag){
            $ids[] = $tag['cateid'];
        }
    }

    $cates = array();
    if($ids) {
        $cates = R::getAll( "select * from cate where cateid IN (" . implode(",", $ids) . ")" );
    }
    //p($tags);
    //p($cates);

    //$node = Requests::get('http://www.dp.com/apiv1/node/23337.json');


    $obj = new stdClass();
    $obj->title = $news->title;
    $obj->status = 1;
    $obj->comment = 1;
    $obj->sticky = 0;
    $obj->type = 'livenews';
    $obj->language = 'zh-hans';
    $obj->date = date('Y-m-d H:i:s +0000', $news->created);
    //$obj->created = $news->created;
    //$obj->changed = $news->created;
    $body = new stdClass();
    $body->value = $news->content ? $new->content : $news->title;
    $body->format = 2;
    $obj->body = new stdClass();
    $obj->body->und[] = $body;

    $taxonomy = new stdClass();

    //单tag
    /*
    $obj->field_category = new stdClass();
    $obj->field_category->und = array(
    );
    $obj->field_category->und->value = 9494;
    */


    $obj->field_category = new stdClass();
    $category = array();
    $location = array();
    if($cates) {
        foreach($cates as $cate){
            if(isset($cateMapping[$cate['cate']])) {
                $id = $cateMapping[$cate['cate']];
                $category[$id] = $id;
            } else {
                if(isset($locationMapping[$cate['cate']])) {
                    $id = $locationMapping[$cate['cate']];
                    $location[$id] = $id;
                }
            }
        }
    }

    $obj->field_category->und =  $category;
    if($location) {
        $obj->field_location = new stdClass();
        $obj->field_location->und =  $location;
    }

    $obj->field_color = new stdClass();
    $obj->field_color->und = new stdClass();
    $obj->field_color->und->value = $news->color;
    $obj->field_format = new stdClass();
    $obj->field_format->und = new stdClass();
    $obj->field_format->und->value = $news->bold == 'bold' ? 'bold' : '_none';
    $obj->field_icon = new stdClass();
    $obj->field_icon->und = new stdClass();
    $obj->field_icon->und->value = $news->icon ? $news->icon  : '_none';

    $res = Requests::post('http://www.dp.com/apiv1/node', array(
        'Cookie' => $loginRes->session_name . '=' . $loginRes->sessid,
        'Content-type' => 'application/json'
    ), json_encode($obj));

    p($obj);

    return $res;
}


$res = createNode(20314);
p($res);

/*
$nid = 350;
while($nid <= 20314){
    createNode($nid);
    $nid++;
}
*/
