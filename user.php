<?php
use BCA\CURL\CURL;

include './init_autoloader.php';

define('O_LIST_URL','http://wscn.dev/apiv1/user.json');
define('O_DETAIL_URL','http://wscn.dev/apiv1/user/%s.json');

define('N_DETAIL_URL','http://api.goldtoutiao.com/v2/user');
define('MAX_PAGE',689);
//define('MAX_PAGE',10);


define('LOG_DIR','backup/user/');

//$img_arr = array('.jpg', '.png', '.jpeg', '.gif');
//
//for($page=$currentMaxPage;$page<=MAX_PAGE;$page++){
//    $user = login();
//    $cookie = $user['session_name'] . '=' . $user['sessid'];
//
//    $request = new CURL(O_LIST_URL);
//    $request->header( 'Cookie' , $cookie);
//    $response = $request->param('page', $page)
//        ->get();
//    $data = json_decode($response,true);
//    echo '<pre>';
////    print_r($data);
//
//
//
//    exit;
//}


$config = new \Doctrine\DBAL\Configuration();
//..
$connectionParams = array(
    'dbname' => 'wscn',
    'user' => 'root',
    'password' => 'password',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
);

$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

$maxId = $conn->fetchColumn('SELECT max(uid) as maxId FROM users');


$logData = getData();
$currentMaxUid = !empty($logData['user']['currentMaxUid']) ? $logData['user']['currentMaxUid'] : 0;
//$currentMaxUid = 0;

$sqlHeader = <<<SQL
    INSERT INTO `eva_user_users` (`id`,`username`, `email`, `status`, `accountType`, `screenName`, `password`, `avatarId`, `avatar`, `emailStatus`,  `createdAt`, `loginAt`, `providerType`)
    VALUES
SQL;

$path = './user.sql';
if($currentMaxUid == 0){
    unlink($path);
}

//    createDir($path);
//fwrite($fp, $sqlHeader);
//fclose($fp);
$fp = fopen($path,'a');

for($uid=$currentMaxUid;$uid<=$maxId;){

    if($currentMaxUid>$maxId) $currentMaxUid = $maxId;
    $sql = "SELECT * FROM  `users` WHERE uid > :currentMaxUid ORDER BY uid ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxUid', $currentMaxUid);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        $currentMaxUid = $row['uid'];
        $str = $sqlHeader;
        $str .= postUser($row);
        $str .= ';'.PHP_EOL;
        fwrite($fp,$str);
        echo $currentMaxUid.PHP_EOL;
        $logData['user']['currentMaxUid'] = intval($currentMaxUid);

        saveData($logData);
    }


    if($currentMaxUid>=$maxId) break;

}

fclose($fp);

//var_dump($currentMaxUid);


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

function postUser($v)
{
    $row = array();
    $row['id']   = $v['uid'];
    $row['email'] = $v['mail'];


    if(preg_match('/^[0-9a-zA-Z]+$/',$v['name'])){
        $row['username'] = $v['name'];
    }else{
        $row['username'] = $v['mail'];
    }
    $row['screenName'] = $v['name'];
    $row['password'] = $v['pass'];
    $row['createdAt'] = $v['created'];
//        $row['access'] = $v['access'];
    $row['loginAt'] = $v['login'];

    $status_arr = array(0=>'inactive',1=>'active');
    $row['status'] = $status_arr[$v['status']];

    $row['avatarId'] = '';
    $row['avatar'] = '';
    if(!empty($v['picture'])){
        $user = login();
        $cookie = $user['session_name'] . '=' . $user['sessid'];

        $request = new CURL(sprintf(O_DETAIL_URL,$row['id']));
        $request->header( 'Cookie' , $cookie);
        $response = $request->get();
        $data = json_decode($response,true);
        $imgUrl = str_replace('wscn.dev','img.wallstreetcn.com',$data['picture']['url']);
        $imageLocalPath = LOG_DIR.$data['picture']['filename'];
        $img = imageTransfer($imgUrl,$imageLocalPath);
        $row['avatarId'] = $img['id'];
        $row['avatar'] = $img['localUrl'];
    }

//    $row = json_encode($row);
//    $request = new CURL(N_DETAIL_URL);
//    $response = $request->post($row);
//        $d = json_decode($response,true);


    $str = "('%d','%s', '%s', 'active', 'basic', '%s', '%s', '%s', '%s', 'inactive', '%d', '%d', 'ADMIN')";
    $str = sprintf($str,$row['id'],$row['username'],$row['email'],$row['screenName'],$row['password'],$row['avatarId'], $row['avatar'],$row['createdAt'],$row['loginAt'],true);

    return $str;

    $str .= ','.PHP_EOL;


    $path = './user.sql';
//    createDir($path);
    $fp = fopen($path,'a');
    fwrite($fp, $str);
    fclose($fp);
//    echo $response.PHP_EOL;
}

function createSQL($path,$articleContent){
    createDir($path);
    $fp = fopen($path,'a');
    fwrite($fp, $articleContent);
    fclose($fp);
//    echo 'log:'.$path.PHP_EOL;
    return $path;
}

function login()
{
    static $user=null;

    if($user === null){
        $url = 'http://wscn.dev/apiv1/user/login.json';
        $requestData = array(
            'username' => 'AlloVince',
            'password' => '123456',
        );

        $request = new CURL($url);
        $response = $request->header('Content-type','application/json')
            ->post(json_encode($requestData));
        $response = json_decode($response,true);
        $user = $response;

    }
    return $user;
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

