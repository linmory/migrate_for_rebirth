<?php

define('MEDIA_URL','http://api.goldtoutiao.com/v2/admin/media');

/**
 * 下载新闻内容
 * @param $nid
 * @param $dir
 * @param $url
 * @param $updatedAt
 * @return string
 */
function downContent($nid,$dir,$url,$updatedAt){
    $file = $dir.'content.old.'.$updatedAt;
    if(file_exists($file)){
        return file_get_contents($file);
    }

    $request = new \BCA\CURL\CURL(sprintf($url,$nid));
    $response = $request->get();

    logContent($file,$response);
    return $response;
}

/**
 * 保存log data
 * @param $data
 */
function saveData($data)
{
    $str = '<?php'.PHP_EOL.'return ';
    $str .= var_export($data,TRUE);
    $str .= ';';

    file_put_contents('./data.log',$str);
}

/**
 * 获得log data
 * @return array|mixed
 */
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
function uploadFileImage($imageName,$dir)
{
    $prefix = 'http://img.wallstreetcn.com/sites/default/files/';
    $imageName = rawurlencode($imageName);
    $imageUrl = $prefix.$imageName;
//    echo 'upload img:'.$imageUrl.PHP_EOL;
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

    //去除图片链接？后的字符
    $i = strpos($imageLocalPath,'?');
    if($i !== false){
        $imageLocalPath = substr($imageLocalPath,0,$i);
    }

    $a = downImage($imageUrl,$imageLocalPath);
    if(!$a){
        echoError($imageLocalPath);
        return array();
    }

    $response = uploadImage($imageLocalPath);
    $response = json_decode($response,true);
    if(!isset($response['localUrl'])){
        $str = var_export($response,TRUE).PHP_EOL.$imageLocalPath.PHP_EOL.$imageUrl.PHP_EOL;
        echoError($str);
    }
    return $response;
}

/**
 * 下载图片
 * @param $imageUrl
 * @param $imageLocalPath
 * @return bool
 */
function downImage($imageUrl,$imageLocalPath){
    if(file_exists($imageLocalPath)) return true;

    if(strpos($imageUrl,'http://wscnimg.storage.aliyun.com/') == true) return false;

    createDir($imageLocalPath);
    return copy($imageUrl,$imageLocalPath);
}

/**
 * 调用上传图片api
 * @param $imageLocalPath
 * @return mixed
 */
function uploadImage($imageLocalPath){
    $data = array('file'=>'@'.$imageLocalPath);

    $url = MEDIA_URL;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
//    var_dump($response);
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

//            echo 'content img:'.$value.PHP_EOL;
            $arr = explode('/',$value);
            $imageName = end($arr);
            $imagePath = $dir.'img/'.$imageName;

            if(stripos($value,'http://') === 0){
                $imageUrl = $value;
            }else{
                $imageUrl = 'http://img.wallstreetcn.com/'.$value;
            }

            if(!isImage($imageUrl)){
                $error = 'not img:'.$imageUrl.PHP_EOL;
//                echoError($error);
                echo $error;
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

/**
 * 移除图片前缀host
 * @param $result
 * @return mixed
 */
function removeImgHost($result){
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

/**
 * 判断是否为图片
 * @param $imageUrl
 * @return bool
 */
function isImage($imageUrl){
    $img_arr = array('.jpg', '.png', '.jpeg', '.gif');

    foreach($img_arr as $v){
        if(stripos($imageUrl,$v) !== false){
            return true;
        }
    }

    return false;
//            print_r($img_arr);
}


function createDir($path){
    $dir = dirname($path);
    if(!is_dir($dir)){
        mkdir($dir,0777,true);
    }
}

function echoError($error)
{
    $error .= PHP_EOL;
    fwrite(STDERR, $error);
}


function logContent($path,$articleContent){
    createDir($path);
    $fp = fopen($path,'w');
    fwrite($fp, $articleContent);
    fclose($fp);
//    echo 'log:'.$path.PHP_EOL;
    return $path;
}