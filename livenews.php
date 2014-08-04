<?php
use BCA\CURL\CURL;

include './init_autoloader.php';

define('F_NEWS_LIST_URL','http://wscn.dev/apiv1/migrate/livenews.json');
define('F_NEWS_DETAIL_URL','http://wscn.dev/apiv1/node/%s.json');

define('T_NEWS_DETAIL_URL','http://api.goldtoutiao.com/v2/post');
define('MAX_PAGE',689);
//define('MAX_PAGE',10);


define('LOG_DIR','backup/');
$currentMaxPage = getMaxNumber(LOG_DIR);
$currentMaxNid = getMaxNumber(LOG_DIR.$currentMaxPage.'/');
print_r($currentMaxNid);

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
        $row['text']['content'] = getImg($detail['body']['und'][0]['safe_value'],$dir);
        $row['text']['content'] = removeImg($row['text']['content']);
        $row['summary'] = $detail['body']['und'][0]['safe_summary'];

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

//        print_r($row);
//        exit;
    }
//    exit;

}

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

