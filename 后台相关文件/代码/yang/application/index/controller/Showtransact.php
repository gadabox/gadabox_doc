<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/6
 * Time: 14:30
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Aes.php');
Class Showtransact extends controller
{
    public function transactDetail()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $list = Db::table('gada_transact')
            ->where('user_id',$uid)
            ->order('transact_id desc')
            ->field('transact_content,transact_create_time,transact_detail,current_balance')
            ->select();
        $data = ['transactList' => $list];
        Response::returnApiSuccess('200','反馈成功',$data);
    }
}