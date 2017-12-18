<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/6
 * Time: 11:23
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
//use think\Response;

require_once ('Aes.php');
Class Showscore extends controller
{
    public function scoreDetail()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $list = Db::table('gada_score')
            ->where('user_id',$uid)
            ->order('score_id desc')
            ->field('score_content,score_create_time,score_detail')
            ->select();
        $data = ['creditScoreList' => $list];
        Response::returnApiSuccess('200','反馈成功',$data);
    }

}