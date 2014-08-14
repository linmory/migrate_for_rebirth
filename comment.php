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

$maxId = $conn->fetchColumn('SELECT max(cid) as maxId FROM comment');


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

$path = './sql/user.sql';
if($currentMaxUid == 0){
    unlink($path);
}

$fp = fopen($path,'a');

for($uid=$currentMaxUid;$uid<=$maxId;){

    if($currentMaxUid>$maxId) $currentMaxUid = $maxId;
    $sql = "SELECT * FROM  `users` WHERE uid > :currentMaxUid ORDER BY uid ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxUid', $currentMaxUid);
    $stmt->execute();

    $str = $sqlHeader;
    $end = ','.PHP_EOL;
    while ($row = $stmt->fetch()) {
        $currentMaxUid = $row['uid'];
        $str .= postUser($row);
        $str .= $end;
    }

    $str = rtrim($str,$end);
    $str .= ';'.PHP_EOL;

    fwrite($fp,$str);

    echo $currentMaxUid.PHP_EOL;
    $logData['user']['currentMaxUid'] = intval($currentMaxUid);
    saveData($logData);

    if($currentMaxUid>=$maxId) break;

}

fclose($fp);

function postUser($v)
{
    $row = array();
    $row['id']   = $v['uid'];
    $row['email'] = mysql_real_escape_string($v['mail']);


    if(preg_match('/^[0-9a-zA-Z]+$/',$v['name'])){
        $row['username'] = $v['name'];
    }else{
        $row['username'] = $row['email'];
    }
    $row['screenName'] = mysql_real_escape_string($v['name']);
    $row['password'] = $v['pass'];
    $row['createdAt'] = $v['created'];
//        $row['access'] = $v['access'];
    $row['loginAt'] = $v['login'];

    $status_arr = array(0=>'inactive',1=>'active');
    $row['status'] = $status_arr[$v['status']];

    $row['avatarId'] = '';
    $row['avatar'] = '';
    if(!empty($v['picture'])){

        $nid = $row['id'];

        $dir = LOG_DIR.$nid.'/';

        $response = downUserContent($nid,$dir,O_DETAIL_URL);

        $data = json_decode($response,true);

        $imgUrl = str_replace('wscn.dev','img.wallstreetcn.com',$data['picture']['url']);
        $imageLocalPath = $dir.$data['picture']['filename'];
        $img = imageTransfer($imgUrl,$imageLocalPath);
        $row['avatarId'] = $img['id'];
        $row['avatar'] = $img['localUrl'];
    }

    $str = "('%d','%s', '%s', 'active', 'basic', '%s', '%s', '%s', '%s', 'inactive', '%d', '%d', 'ADMIN')";
    $str = sprintf($str,$row['id'],$row['username'],$row['email'],$row['screenName'],$row['password'],$row['avatarId'], $row['avatar'],$row['createdAt'],$row['loginAt'],true);

    return $str;
}

/**
 * 下载用户详细信息
 * @param $nid
 * @param $dir
 * @param $url
 * @return string
 */
function downUserContent($nid,$dir,$url){
    $file = $dir.'content.old';
    if(file_exists($file)){
        return file_get_contents($file);
    }

    $user = login();
    $cookie = $user['session_name'] . '=' . $user['sessid'];

    $request = new CURL(sprintf($url,$nid));
    $request->header( 'Cookie' , $cookie);
    $response = $request->get();

    logContent($file,$response);
    return $response;
}

/**
 * 模拟登陆，获得权限
 * @return mixed
 */
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

