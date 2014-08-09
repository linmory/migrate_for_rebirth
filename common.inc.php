<?php
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
                $error = 'not img!'.PHP_EOL;
                echoError($error);
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

function createDir($path){
    $dir = dirname($path);
    if(!is_dir($dir)){
        mkdir($dir,0777,true);
    }
}

function echoError($error)
{
    fwrite(STDERR, $error);
}