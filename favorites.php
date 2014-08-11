<?php
use BCA\CURL\CURL;

include './init_autoloader.php';
include './common.inc.php';

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


$maxId = $conn->fetchColumn('SELECT max(fid) as maxId FROM favorites');

$sqlHeader = 'INSERT INTO `eva_blog_favors` (`userId`,`postId`,`createdAt`) VALUES ';

$path = './sql/favorites.sql';
unlink($path);
$currentMaxFid = 0;
$fp = fopen($path,'a');

for(;;){

//    if($currentMaxFid>$maxId) $currentMaxFid = $maxId;
    $sql = "SELECT fid,uid,path,`timestamp` FROM favorites WHERE fid > :currentMaxFid ORDER BY fid ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxFid', $currentMaxFid);
    $stmt->execute();
    $str = '';
    while ($row = $stmt->fetch()) {
        $currentMaxFid = $row['fid'];

        $postId = str_replace('node/','',$row['path']);
        if($postId == $row['path']) continue;

        $str .= sprintf("('%d','%d', '%d')",$row['uid'],$postId,$row['timestamp']);
        $str .= ',';
    }

    $str = rtrim($str,',');

    $str = $sqlHeader.$str.';'.PHP_EOL;
    fwrite($fp,$str);


    if($currentMaxFid>=$maxId) break;

}

fclose($fp);