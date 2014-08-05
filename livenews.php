<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

define('F_NEWS_LIST_URL','http://wscn.dev/apiv1/migrate_livenews.json');
define('F_NEWS_DETAIL_URL','http://wscn.dev/apiv1/node/%s.json');

define('T_NEWS_DETAIL_URL','http://api.goldtoutiao.com/v2/livenews');
define('MAX_PAGE',689);
//define('MAX_PAGE',10);


define('LOG_DIR','backup/livenews');

$logData = getData();
$currentMaxPage = !empty($logData['live_news']['currentMaxPage']) ? $logData['live_news']['currentMaxPage'] : 1;
$currentMaxNid = !empty($logData['live_news']['$currentMaxNid']) ? $logData['live_news']['currentMaxNid'] : 0;

print_r($currentMaxNid);


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

$color = array(
    'red'=>'红色',
    'blue'=>'蓝色',
    'black'=>'黑色',
);

//格式
$format = array(
    'bold' => '加粗'
);

//图标
//'alert' 提醒
//'calendar' 日程
//'chart' 折线
//'chart_pie' 柱状
//'download' 下载
//'rumor' 传言
//'warning' 警告
//'news' 消息
$icon = array(
    'alert' => '提醒',
    'calendar' => '日程',
    'chart' => '折线',
    'chart_pie' => '柱状',
    'download' => '下载',
    'rumor' => '传言',
    'warning' => '警告',
    'news' => '消息',
);


$img_arr = array('.jpg', '.png', '.jpeg', '.gif');

//exit;

for($page=$currentMaxPage;$page<=MAX_PAGE;$page++){
//    echo $i.PHP_EOL;
    $request = new CURL(F_NEWS_LIST_URL);
    $response = $request->param('page', $page)
                        ->get();


    $data = json_decode($response,true);
    foreach($data as $k=>$v){

        $nid = $v['nid'];
        $dir = LOG_DIR.$page.'/'.$nid.'/';

//        if($nid<$currentMaxNid)
        $request = new CURL(sprintf(F_NEWS_DETAIL_URL,$nid));
        $response = $request->get();
        $articleContent = $response;
        $detail = json_decode($response,true);


        $row = array();
//        $row['id']   = $nid;
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
        $row['text']['content'] = getImg($detail['body']['und'][0]['safe_value'],$dir);
        $row['text']['content'] = removeImg($row['text']['content']);

        //保存附件图片
        if(!empty($detail['upload']['und'][0]['filename'])){
            $arr = uploadPostImage($detail['upload']['und'][0]['filename'],$dir);

            $row['imageId'] = $arr['id'];
            $row['image'] = $arr['localUrl'];
        }


        $row = json_encode($row);
        $request = new CURL(T_NEWS_DETAIL_URL);
        $response = $request->post($row);
//        $d = json_decode($response,true);


        logContent($dir.'content.old',$articleContent);
        logContent($dir.'content.new',$response);

        print_r($row);
        exit;
    }
//    exit;

}

/**
 * 更新文章附件图片
 * @param $imageName
 * @param $dir
 * @return mixed
 */
function uploadPostImage($imageName,$dir)
{
    $prefix = 'http://img.wallstreetcn.com/sites/default/files/';
    $imageUrl = $prefix.$imageName;
    echo 'upload img:'.$imageUrl.PHP_EOL;
    $imagePath = $dir.'img/'.$imageName;
    return $arr = imageTransfer($imageUrl,$imagePath);
}

/**
 * 将图片从线上转存到新的服务器
 * @param $imageUrl
 * @param $imageLocalPath
 * @return mixed
 */
function imageTransfer($imageUrl,$imageLocalPath){
    createDir($imageLocalPath);
    copy($imageUrl,$imageLocalPath);

    $data = array('file'=>'@'.$imageLocalPath);
    $response = uploadImage($data);
    $response = json_decode($response,true);
    if(!isset($response['localUrl'])){
        print_r($response);
    }
    return $response;
}

/**
 * 更新文章中的图片链接
 * @param $result
 * @param $dir
 * @return mixed
 */
function getImg($result,$dir){
    $result = preg_replace_callback('/src=([\'"])?(.*?)\\1/i',function ($matches) use($dir) {
            $value = $matches[2];

            echo 'content img:'.$value.PHP_EOL;
            $arr = explode('/',$value);
            $imageName = end($arr);
            $imagePath = $dir.'img/'.$imageName;

            if(stripos($value,'http://') === 0){
                $imageUrl = $value;
            }else{
                $imageUrl = 'http://img.wallstreetcn.com/'.$value;
            }

            if(!isImage($imageUrl)){
                echo 'not img!'.PHP_EOL;
                return $matches[0];
            }

            $arr = imageTransfer($imageUrl,$imagePath);

            if(isset($arr['localUrl'])){

                //todo 添加域名
                return str_replace($value,$arr['localUrl'],$matches[0]);
            }else{
                return $matches[0];
            }
        },$result);
    return $result;
}

function removeImg($result){
    $result = preg_replace_callback('/href=([\'"])?(.*?)\\1/i',function ($matches) {
            $value = $matches[0];
            $value = str_ireplace('http://www.wallstreetcn.com/','',$value);
            $value = str_ireplace('http://wallstreetcn.com/','',$value);
            $value = str_ireplace('www.wallstreetcn.com/','',$value);
            $value = str_ireplace('wallstreetcn.com/','',$value);
            return $value;
        },$result);
    return $result;
}


function isImage($imageUrl){
    global $img_arr;

    foreach($img_arr as $v){
        if(stripos($imageUrl,$v) !== false){
            return true;
        }
    }

    return false;
//            print_r($img_arr);
}

/**
 * 调用上传图片api
 * @param $data
 * @return mixed
 */
function uploadImage($data){
    $url = 'http://api.goldtoutiao.com/v2/media';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    return $response;

}

function createDir($path){
    $dir = dirname($path);
    if(!is_dir($dir)){
        mkdir($dir,0777,true);
    }
}

function logContent($path,$articleContent){
    createDir($path);
    $fp = fopen($path,'w');
    fwrite($fp, $articleContent);
    fclose($fp);
    echo 'log:'.$path.PHP_EOL;
    return $path;
}

//获取文件列表
function getMaxNumber($dir) {
    $max = 1;
    if(!is_dir($dir)){
        return $max;
    }

    if (false != ($handle = opendir ( $dir ))) {
        $i=0;
        while ( false !== ($file = readdir ( $handle )) ) {
            //去掉"“.”、“..”以及带“.xxx”后缀的文件
            if ($file != "." && $file != "..") {
                $max = $file>$max ? $file : $max;
            }
        }
        //关闭句柄
        closedir ( $handle );
    }
    return $max;
}

