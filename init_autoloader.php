<?php
/**
 * WscnWechat
 *
 * @link      https://github.com/wallstreetcn/wechat
 * @copyright Copyright (c) 2010-2014 WallstreetCN
 * @author    WallstreetCN Team
 * @version   1.0
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $loader = include __DIR__ . '/vendor/autoload.php';
} else {
    throw new RuntimeException('Unable to find loader. Run `php composer.phar install` first.');
}