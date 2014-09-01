<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

define('F_NEWS_LIST_URL','http://wallstreetcn.com/apiv1/migrate_post.json');
define('F_NEWS_DETAIL_URL','http://wallstreetcn.com/apiv1/node/%s.json');

define('T_NEWS_DETAIL_URL','http://api.goldtoutiao.com/v2/admin/posts');
define('MAX_PAGE',689);
//define('MAX_PAGE',10);


define('LOG_DIR','backup/posts/');

//$currentMaxPage = getMaxNumber(LOG_DIR);
//$currentMaxNid = getMaxNumber(LOG_DIR.$currentMaxPage.'/');

$logData = getData();
$currentMaxPage = !empty($logData['post']['currentMaxPage']) ? $logData['post']['currentMaxPage'] : 1;
$currentMaxNid = !empty($logData['post']['currentMaxNid']) ? $logData['post']['currentMaxNid'] : 0;

//命令行中可用参数指定开始id
//var_dump($argv); var_dump($argc);
if(($argc>1)&&is_numeric($argv[1])){
    $currentMaxPage = intval($argv[1]);
    if($currentMaxPage == 0) $currentMaxPage = 1;
    $currentMaxNid = 0;
}

if(($argc>2)&&is_numeric($argv[2])){
    $currentMaxNid = $argv[2];
}

echo 'current max page:'.$currentMaxPage.PHP_EOL;
echo 'current max nid:'.$currentMaxNid.PHP_EOL;

//print_r($currentMaxNid);

//$arr = array(
//    13761 => '世界杯',
//    13762 => '世界杯头条',
//    13079 => '博客',
//    13080 => '博客头条',
//    3118  => '头条',
//    11212 => '特刊',
//    11213 => '特刊头条',
//    11142 => '黄金头条',
//    3120  => '专题',
//    3119  => '编辑推荐'
//);

//taxonomy_vocabulary_6 特别展示
$category_6 = array(
    13761 => 2,
    13762 => 3,
    13079 => 4,
    13080 => 5,
    3118  => 6,
    11212 => 7,
    11213 => 8,
    11142 => 9,
    3120  => 10,
    3119  => 11
);


//3562 见闻早餐
//3917 见闻图表
//7349 欧洲
//7350 美国
//7351 中国
//7352 其他地区
//4  经济
//7353 央行
//48 市场
//7354 公司

//taxonomy_vocabulary_2 文章分类
$category_2 = array(
    3562 => 13,
    3917 => 14,
    7349 => 15,
    7350 => 16,
    7351 => 17,
    7352 => 18,
    4  => 19,
    7353 => 20,
    48 => 21,
    7354 => 22,
);

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

        if($nid<=$currentMaxNid) continue;

        echo 'nid:'.$nid.PHP_EOL;
        $row = array();
        $row['count'] = $v['node_counter_totalcount'];

        $updatedAt = $v['node_changed'];

        $dir = LOG_DIR.$page.'/'.$nid.'/';

//        if($nid<$currentMaxNid)
        $response = downContent($nid,$dir,F_NEWS_DETAIL_URL,$updatedAt);
        $detail = json_decode($response,true);

        $row['id']   = $nid;
        $row['userId']   = $detail['uid'];
        $row['title'] = $detail['title'];
        $row['createdAt'] = $detail['created'];
        $row['updatedAt'] = $detail['changed'];

//        暂时还不能保存相关文章
//        $row['field_related'] = $detail['field_related']['und'];


          //特别展示
//        $row['taxonomy_vocabulary_6'] = $detail['taxonomy_vocabulary_6']['und'];
        if(!empty($detail['taxonomy_vocabulary_6']['und'])){
            foreach($detail['taxonomy_vocabulary_6']['und'] as $v){
//                echo $v['tid'].PHP_EOL;
                $row['categories'][] = $category_6[$v['tid']];
            }
        }


        //文章分类
        if(!empty($detail['taxonomy_vocabulary_2']['und'])){
            foreach($detail['taxonomy_vocabulary_2']['und'] as $v){
//                echo $v['tid'].PHP_EOL;
                $row['categories'][] = $category_2[$v['tid']];
            }
        }

        //标签
        if(!empty($detail['taxonomy_vocabulary_3']['und'])){
            foreach($detail['taxonomy_vocabulary_3']['und'] as $v){
                $row['tags'][] = $v['name'];
            }
        }

        //
        //保存文章中的图片 并转换路径
        $value = isset($detail['body']['und'][0]['safe_value']) ? $detail['body']['und'][0]['safe_value'] : $detail['body']['und'][0]['value'];
        $summary = isset($detail['body']['und'][0]['safe_summary']) ? $detail['body']['und'][0]['safe_summary'] : $detail['body']['und'][0]['summary'];

        $row['text']['content'] = getImg($value,$dir);
        $row['text']['content'] = removeImgHost($row['text']['content']);

        $row['text']['content'] = str_replace('点击下载 华尔街见闻App，随时随地把握全球金融市场脉搏。','',$row['text']['content']);

        $row['summary'] = $summary;

        //保存附件图片
        if(!empty($detail['upload']['und'][0]['filename'])){
            $arr = uploadFileImage($detail['upload']['und'][0]['filename'],$dir);

            $row['imageId'] = $arr['id'];
            $row['image'] = $arr['localUrl'];
        }

//        print_r($row);
        $row = json_encode($row);
        $request = new CURL(T_NEWS_DETAIL_URL);
        $response = $request->post($row);

//        echo $response;
//        $d = json_decode($response,true);
//        var_dump($d);


        logContent($dir.'content.new',$response);

//        print_r($row);
//        exit;
    }
    echo 'page:'.$page.PHP_EOL;


//    exit;

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


