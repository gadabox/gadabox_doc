<?php
/**
 * 配送评价类.
 * User: yang
 * Date: 2017/9/20
 * Time: 11:32
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Token.php');
Class Evaluate extends controller
{
    public static function evaluate()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token', "$token")
            ->value('user_id');
        $order_num = $data['order_number'];
        $speed = $data['speed'];
        $service = $data['service'];
        $content = $data['content'];
        $evaluate_time = date("Y-m-d H:i:s");
        $evaluate = ['evaluate_speed'=>$speed,'evaluate_service'=>$service,'evaluate_content'=>$content,'evaluate_time'=>$evaluate_time];
        $is_evaluate = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->update($evaluate);
        if($is_evaluate ==1)
        {
            //增加积分
            Db::table('gada_score')
                ->insert(['score_content'=>'完成评价','user_id'=>$uid,'score_detail'=>'+1']);
            $score = Db::table('gada_user')
                ->where('user_id',$uid)
                ->value('score');
            $score = $score + 1;
            Db::table('gada_user')
                ->where('user_id',$uid)
                ->update(['score'=>$score]);
            Response::returnApiOk(200,'评价成功');
        }
        else
        {
            Response::returnApiError(201,'评价失败');
        }
    }
}