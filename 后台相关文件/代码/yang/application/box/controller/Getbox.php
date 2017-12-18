<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/30
 * Time: 16:37
 */
namespace app\box\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
class Getbox
{
    //该类接口方法主要负责接收主动访问箱子后的箱子反馈信息
    public function send_Msg($data)
    {

    }
    public function send_GPS($data)
    {
        //主动请求读取箱子坐标
        $data = '#' . $box_code . ',' . 'READ_GPS';
        $this->send_Msg($data);
    }
}