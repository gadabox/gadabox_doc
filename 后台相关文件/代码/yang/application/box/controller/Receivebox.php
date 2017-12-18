<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/30
 * Time: 17:09
 */
namespace app\box\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
class Receivebox
{
    //该类接口方法主要负责接收箱子主动发送的信息
    public function send_Msg($data)
    {

    }
}