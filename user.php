<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

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

//命令行中可用参数指定开始id
if(($argc>1)&&is_numeric($argv[1])){
    $currentMaxUid = $argv[1];
//    $currentMaxNid = 0;
}
//$currentMaxUid = 0;

$sqlHeader = <<<SQL
    INSERT INTO `eva_user_users` (`id`,`username`, `email`, `status`, `accountType`, `screenName`, `oldPassword`, `avatarId`, `avatar`, `emailStatus`,  `createdAt`, `loginAt`, `providerType`)
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

function logContent($path,$articleContent){
    createDir($path);
    $fp = fopen($path,'w');
    fwrite($fp, $articleContent);
    fclose($fp);
    echo 'log:'.$path.PHP_EOL;
    return $path;
}

