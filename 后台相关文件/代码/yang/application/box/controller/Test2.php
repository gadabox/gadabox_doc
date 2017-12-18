<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/27
 * Time: 15:32
 */
namespace app\box\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
//require_once ('Test.php');
class Test2
{
    public function test()
    {
        // 建立socket连接到内部推送端口
        $client = stream_socket_client('tcp://47.94.157.157:60969', $errno, $errmsg, 1);
// 推送的数据，包含uid字段，表示是给这个uid推送
        $data = 'x1234567892abc';
// 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client,$data);
// 读取推送结果
        echo fread($client, 8192);

    }
}

