<?php
/**
 * WscnThemis
 *
 * @link      https://github.com/wallstreetcn/themis
 * @copyright Copyright (c) 2010-2014 WallstreetCN
 * @author    WallstreetCN Team: shao<liujaysen@gmail.com>
 * @version   2.0
 */

include './init_autoloader.php';
$config = new \Doctrine\DBAL\Configuration();
//..
$connectionParams = array(
    'dbname' => 'scrapy_dev',
    'user' => 'root',
    'password' => 'password',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
    'charset' => 'utf8',
);

$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

$maxId = $conn->fetchColumn('SELECT max(postId) as maxId FROM eva_blog_texts');


for ($currentMaxId = 0; $currentMaxId <= $maxId;) {

    $sql = "SELECT postId,content FROM `eva_blog_texts` WHERE postId > :currentMaxId ORDER BY postId ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxId', $currentMaxId);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        $content = $row['content'];
        $currentMaxId = $row['postId'];
//        echo $currentMaxId.PHP_EOL;
        $newContent = fixHrefHost($content, $currentMaxId);

        if($newContent != $content){
            $updateSql = "update `eva_blog_texts` set `content` = :content WHERE postId = :currentMaxId";
            $count = $conn->executeUpdate($updateSql,array('content'=>$newContent,'currentMaxId'=>$currentMaxId));

        }


//        removeImgHost($content,$currentMaxId);
    }

    if ($currentMaxId >= $maxId) {
        break;
    }

}


echo 'eva_livenews_news' . PHP_EOL;
$maxId = $conn->fetchColumn('SELECT max(id) as maxId FROM eva_livenews_news');
for ($currentMaxId = 0; $currentMaxId <= $maxId;) {

    $sql = "SELECT id,content FROM `eva_livenews_news` WHERE id > :currentMaxId ORDER BY id ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxId', $currentMaxId);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        $content = $row['content'];
        $currentMaxId = $row['id'];
//        echo $currentMaxId.PHP_EOL;
        $newContent = fixHrefHost($content, 'livenews-' . $currentMaxId);
        if($newContent != $content){
            $updateSql = "update `eva_livenews_news` set `content` = :content WHERE id = :currentMaxId";
            $count = $conn->executeUpdate($updateSql,array('content'=>$newContent,'currentMaxId'=>$currentMaxId));
        }
//        removeImgHost($content,'livenews-'.$currentMaxId);
    }

    if ($currentMaxId >= $maxId) {
        break;
    }

}

echo 'eva_livenews_texts' . PHP_EOL;

$maxId = $conn->fetchColumn('SELECT max(newsId) as maxId FROM eva_livenews_texts');
for ($currentMaxId = 0; $currentMaxId <= $maxId;) {

    $sql = "SELECT newsId,contentExtra FROM `eva_livenews_texts` WHERE newsId > :currentMaxId ORDER BY newsId ASC LIMIT 0 , 500;";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('currentMaxId', $currentMaxId);
    $stmt->execute();

    while ($row = $stmt->fetch()) {
        $content = $row['contentExtra'];
        $currentMaxId = $row['newsId'];
//        echo $currentMaxId.PHP_EOL;
        $newContent = fixHrefHost($content, 'livenews-' . $currentMaxId);
        if($newContent != $content){
            $updateSql = "update `eva_livenews_texts` set `content` = :content WHERE newsId = :currentMaxId";
            $count = $conn->executeUpdate($updateSql,array('content'=>$newContent,'currentMaxId'=>$currentMaxId));
        }

//        removeImgHost($content,'livenews-'.$currentMaxId);
    }

    if ($currentMaxId >= $maxId) {
        break;
    }

}

/**
 * 移除图片前缀host
 * @param $result
 * @return mixed
 */
function removeImgHost($result, $postId)
{
    $result = preg_replace_callback(
        '/src=([\'"])?(.*?)\\1/i',
        function ($matches) use ($postId) {
            $value = $matches[0];
            if (strpos($value, 'wallstreetcn.com') != false) {
                echo $postId . ':     ' . $value . PHP_EOL;
            }
//            $value = str_ireplace('http://www.wallstreetcn.com/','/',$value);
//            $value = str_ireplace('http://img.wallstreetcn.com/','/',$value);
//            $value = str_ireplace('http://wallstreetcn.com/','/',$value);
//            $value = str_ireplace('www.wallstreetcn.com/','/',$value);
//            $value = str_ireplace('wallstreetcn.com/','/',$value);
            return $value;
        },
        $result
    );
    return $result;
}

/**
 * 移除图片前缀host
 * @param $result
 * @return mixed
 */
function fixHrefHost($result, $postId)
{
    $result = preg_replace_callback(
        '/href=([\'"])?(.*?)\\1/i',
        function ($matches) use ($postId) {
            $value = $matches[0];
            $url = trim($matches[2]);

            if (strpos($url, 'http') !== 0) {
                if (strpos($url, '/') !== 0) {
                    if (strpos($url, '#') !== 0) {
                        if (strpos($url, 'mailto') !== 0) {
                            echo $postId . ':   ' . $url . PHP_EOL;
                            echo $value.PHP_EOL;
                            $value = 'href="/' . $url . '"';
                            echo $value.PHP_EOL;
//                              echo $url . PHP_EOL;
                        }
                    }

                    //                exit;
                }
            }
            return $value;
        },
        $result
    );

    return $result;
}