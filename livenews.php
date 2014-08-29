<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

define('F_NEWS_LIST_URL','http://wscn.dev/apiv1/migrate_livenews.json');
define('F_NEWS_DETAIL_URL','http://wscn.dev/apiv1/node/%s.json');

define('T_NEWS_DETAIL_URL','http://api.goldtoutiao.com/v2/admin/livenews');
//define('MAX_PAGE',10);


define('LOG_DIR','backup/livenews/');

$logData = getData();
$currentMaxPage = !empty($logData['live_news']['currentMaxPage']) ? $logData['live_news']['currentMaxPage'] : 1;
//$currentMaxNid = !empty($logData['live_news']['$currentMaxNid']) ? $logData['live_news']['currentMaxNid'] : 0;

//命令行中可用参数指定开始id
if(($argc>1)&&is_numeric($argv[1])){
    $currentMaxPage = $argv[1];
//    $currentMaxNid = 0;
}

//print_r($currentMaxNid);


//
//category-und  新闻分类
//9494 债市
//9500 公司
//9497 商品期货
//9495 外汇
//9496 央行
//9499  时政与官员言论
//9501 经济
//9498 经济数据
//9493 股市
//9503 见闻早餐
//9502 货币市场
//12941 贵金属

$category = array(
    '9494'=> 4,
    '9500'=> 2,
    '9497'=> 3,
    '9495'=> 1,
    '9496'=> 5,
    '9499'=> 8,
    '9501'=> 8,
    '9498'=> 6,
    '9493'=> 2,
    '9503'=> 7,
    '9502'=> 4,
    '12941'=> 3
);


//location-und 地区分类
//
//
//'9488'=>'中东',
//'9479'=>'中国',
//'9490'=>'亚洲其他地区',
//'9484'=>'俄罗斯',
//'9482'=>'加拿大',
//'9486'=>'印度',
//'9485'=>'巴西',
//'9491'=>'拉美',
//'9480'=>'日本',
//'9478'=>'欧元区',
//'9492'=>'澳洲',
//'9487'=>'瑞士',
//'9477'=>'美国',
//'9483'=>'英国',
//'9489'=>'非洲',
//'9481'=>'香港',

$location = array(
    '9488'=>19,
    '9479'=>9,
    '9490'=>19,
    '9484'=>19,
    '9482'=>15,
    '9486'=>19,
    '9485'=>19,
    '9491'=>19,
    '9480'=>12,
    '9478'=>11,
    '9492'=>14,
    '9487'=>16,
    '9477'=>10,
    '9483'=>13,
    '9489'=>19,
    '9481'=>9
);

// 颜色
//$color
//"red"=>'红色',
//"blue"=>'蓝色',
//"black"=>'黑色',

//格式

//图标
//'alert' 提醒
//'calendar' 日程
//'chart' 折线
//'chart_pie' 柱状
//'download' 下载
//'rumor' 传言
//'warning' 警告
//'news' 消息


$img_arr = array('.jpg', '.png', '.jpeg', '.gif');

//exit;

for($page=$currentMaxPage;;$page++){
//    echo $i.PHP_EOL;
    $request = new CURL(F_NEWS_LIST_URL);
    $response = $request->param('page', $page)
                        ->get();


    $data = json_decode($response,true);
    if(empty($data)){
        break;
    }

    foreach($data as $k=>$v){

        $nid = $v['nid'];
//        echo 'nid:'.$nid.PHP_EOL;

        $dir = LOG_DIR.$nid.'/';

        $updatedAt = $v['node_changed'];

//        if($nid<$currentMaxNid)
        $response = downContent($nid,$dir,F_NEWS_DETAIL_URL,$updatedAt);
        $detail = json_decode($response,true);

        $str = $detail['body']['und'][0]['value'];
        textTransformData($str);
        continue;


        $row = array();
        $row['id']   = $nid;
        $row['userId']   = $detail['uid'];
        $row['title'] = $detail['title'];


        //createdAt,updatedAt 都使用 created数据
        $row['createdAt'] = $detail['created'];
        $row['updatedAt'] = $detail['created'];

//        暂时还不能保存相关文章
//        $row['field_related'] = $detail['field_related']['und'];


        //新闻分类
        if(!empty($detail['field_category']['und'])){
            foreach($detail['field_category']['und'] as $v){
                $row['categories'][] = $category[$v['tid']];

                //经济数据
                if($v['tid'] == '9498'){
                    $row['type'] = 'data';
                }
            }
        }


        //地区分类
        if(!empty($detail['field_location']['und'])){
            foreach($detail['field_location']['und'] as $v){
//                echo $v['tid'].PHP_EOL;
                $row['categories'][] = $location[$v['tid']];
            }
        }

        //一般数据重要性1 红色：2 加粗：3
        $row['importance'] = 1;
        //颜色
        if(!empty($detail['field_color']['und'])){
            foreach($detail['field_color']['und'] as $v){
               if($v['value'] == 'red'){
                   $row['importance'] = 2;
               }
            }
        }

        //格式
        if(!empty($detail['field_format']['und'])){
            foreach($detail['field_format']['und'] as $v){
                if($v['value'] == 'bold'){
                    $row['importance'] = 3;
                }
            }
        }


        $summary = $detail['body']['und'][0]['safe_summary'];

        //保存文章中的图片 并转换路径
        $content = getImg($detail['body']['und'][0]['value'],$dir);
        $content = removeImgHost($content);


        if(!empty($summary)){
            $row['content'] = $summary;
            $row['commentType'] = 'html';
            $row['hasMore'] = 1;

            $row['text']['contentExtra'] = $content;
        }elseif(!empty($content)){
            $row['content'] = $content;
        }else{
            $row['content'] = $row['title'];
        }


        $row['content'] = str_replace('点击下载 华尔街见闻App，随时随地把握全球金融市场脉搏。','',$row['content']);



        //保存附件图片
        if(!empty($detail['upload']['und'][0]['filename'])){
            $arr = uploadFileImage($detail['upload']['und'][0]['filename'],$dir);

            $row['imageId'] = $arr['id'];
            $row['image'] = $arr['localUrl'];
        }

        $row['sourceName'] = isset($detail['field_source']['und'][0]['safe_value']) ? $detail['field_source']['und'][0]['safe_value'] : '';
        $row['sourceUrl'] = isset($detail['field_sourcelink']['und'][0]['safe_value']) ? $detail['field_sourcelink']['und'][0]['safe_value'] : '';

        $row = json_encode($row);
        $request = new CURL(T_NEWS_DETAIL_URL);
        $response = $request->post($row);

//        var_dump($response);
//        $d = json_decode($response,true);
//        print_r($d);


        logContent($dir.'content.new',$response);


//        print_r($row);
//        exit;
    }

    echo 'page:'.$page.PHP_EOL;
    $logData['live_news']['currentMaxPage'] = intval($page);

    saveData($logData);
//    exit;

}

function textTransformData($str)
{
    $arr = array("us"=>'美国',
        "eu"=>'欧元区',
        "de"=>'德国',
        "jp"=>'日本',
        "fr"=>'法国',
        "gb"=>'英国',
        "es"=>'西班牙',
        "it"=>'意大利',
        "cn"=>'中国',
        "ca"=>'加拿大',
        "tw"=>'中国台北',
        "za"=>'南非',
        "in"=>'印度',
        "id"=>'印度尼西亚',
        "br"=>'巴西',
        "no"=>'挪威',
        "sg"=>'新加坡',
        "au"=>'澳大利亚',
        "th"=>'泰国',
        "ch"=>'瑞士',
        "nz"=>'纽西兰',
        "kr"=>'韩国',
        "hk"=>'香港',
        "my"=>'马来西亚',
    );

    foreach($arr as $country){
        if(strpos($str,$country)){
            foreach(array('预期','前值') as $keyword){
                if(strpos($str,$keyword)){
                    preg_match_all('/\d+(\.\d+){0,1}\%/',$str,$matches);
                    if(!empty($matches[0])&&(count($matches[0])>2)){
                        $n = count($matches[0]);
                        if($n>2 && $n<5){
                            print_r($matches[0]);
                            echo $str.PHP_EOL;
                        }
                    }
                    break;
                }
            }
            break;
        }

    }
}
