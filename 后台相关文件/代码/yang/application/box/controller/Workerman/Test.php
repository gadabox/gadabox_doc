<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/25
 * Time: 16:07
 */

use Workerman\Worker;
require_once __DIR__ . '/Autoloader.php';

// 创建一个Worker监听2347端口，不使用任何应用层协议
$tcp_worker = new Worker("tcp://0.0.0.0:60000");

// 启动1个进程对外提供服务
$tcp_worker->count = 1;
$tcp_worker->uidConnections = array();
$tcp_worker->onConnect = function($connection)
{
    global $tcp_worker;
    $file = 'haha.txt';
    file_put_contents($file,$tcp_worker);
    //var_dump($tcp_worker);
};
// 运行worker
Worker::runAll();
//ip:119.80.123.153