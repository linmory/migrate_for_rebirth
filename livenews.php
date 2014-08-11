<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

define('F_NEWS_LIST_URL','http://wscn.dev/apiv1/migrate_livenews.json');
define('F_NEWS_DETAIL_URL','http://wscn.dev/apiv1/node/%s.json');

define('T_NEWS_DETAIL_URL','http://api.goldtoutiao.com/v2/livenews');
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
    '1294'=> 3
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
        echo 'nid:'.$nid.PHP_EOL;

        $dir = LOG_DIR.$nid.'/';

//        if($nid<$currentMaxNid)
        $response = downContent($nid,$dir,F_NEWS_DETAIL_URL);
        $detail = json_decode($response,true);


        $row = array();
        $row['id']   = $nid;
        $row['userId']   = $detail['uid'];
        $row['title'] = $detail['title'];
        $row['createdAt'] = $detail['created'];
        $row['updatedAt'] = $detail['changed'];

//        暂时还不能保存相关文章
//        $row['field_related'] = $detail['field_related']['und'];


        //新闻分类
        if(!empty($detail['field_category']['und'])){
            foreach($detail['field_category']['und'] as $v){
                $row['categories'][] = $category[$v['tid']];
            }
        }


        //地区分类
        if(!empty($detail['field_location']['und'])){
            foreach($detail['field_location']['und'] as $v){
//                echo $v['tid'].PHP_EOL;
                $row['categories'][] = $location[$v['tid']];
            }
        }


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

        //
        //保存文章中的图片 并转换路径
        $row['content'] = getImg($detail['body']['und'][0]['safe_value'],$dir);
        $row['content'] = removeImgHost($row['content']);
        $row['commentType'] = 'html';


        //保存附件图片
        if(!empty($detail['upload']['und'][0]['filename'])){
            $arr = uploadImage($detail['upload']['und'][0]['filename'],$dir);

            $row['imageId'] = $arr['id'];
            $row['image'] = $arr['localUrl'];
        }

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
