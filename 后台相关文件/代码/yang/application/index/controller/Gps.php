<?php
/**
 * 用户周围箱子坐标.
 * User: yang
 * Date: 2017/8/11
 * Time: 11:57
 */
namespace app\index\controller;
use \think\Db;
use \think\Controller;
Class Gps extends controller
{
    public function gps()
    {
        $add = file_get_contents('php://input');
        for($i=0;$i<20;$i++)
        {
            //纬度
            $latiude[$i] ='116.27'. rand(4234, 8329);
            //经度
            $longitude[$i] = '39.93'. rand(1321, 5161);
            $boxes[$i] = ['latiude' => $latiude[$i],'longitude' => $longitude[$i]];
        }
        $data = ['box' => $boxes];
        Response::returnApiSuccess(200,'成功获取周围箱子',$data);
    }
}