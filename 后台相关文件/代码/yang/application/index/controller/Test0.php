<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/11/20
 * Time: 14:57
 */
namespace app\index\controller;
require_once('Response.php');
require_once ('Userinfo.php');
require_once ('Constants.php');
use \think\Db;
use \think\Controller;
class Test0
{
    public function test()
    {
        sleep(10);
        Db::table('gada_change_address_log')
            ->insert(['order_number'=>6666666,'change_address'=>0,'uid'=>4]);
    }
}

