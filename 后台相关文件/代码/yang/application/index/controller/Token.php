<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/7/26
 * Time: 15:04
 */
namespace app\index\controller;
header("Content-Type: application/json; charset=utf-8");
require_once('Response.php');
use \think\Controller;
use \think\Db;
use think\Session;
use think\Request;
Class Token extends controller
{
    public static function token($phoneid)
    {
        $phone = $phoneid['phone'];
        $uid = $phoneid['uid'];
        $time = time();
        $token = md5($time . $phone . $uid);
        $data = ['user_token'=> $token,'user_id'=>$uid];
        Db::table('gada_token')->insert($data);
        return $token;

    }
    public static function ctoken($courier)
    {
        $phone = $courier['cphone'];
        //清理已有ctoken
        Db::table('gada_ctoken')
            ->where('id_courier',$courier['id_courier'])
            ->delete();
        $identification_id = $courier['identification_id'];
        $time = time();
        $ctoken = md5($time . $phone . $identification_id);
        $id_courier = $courier['id_courier'];
        $data = ['courier_token'=> $ctoken,'id_courier'=>$id_courier];
        Db::table('gada_ctoken')->insert($data);
        return $ctoken;
    }
    public static function updatatoken($token)
    {
        $time = date("Y-m-d H:i:s");
        Db::table('gada_token')
            ->where('user_token',$token)
            ->update(['token_life' => $time]);
        Db::table('gada_user')
            ->join('gada_token','gada_user . user_id = gada_token . user_id')
            ->where('user_token',$token)
            ->update(['last_login_time'=>$time]);
    }
    public static function updatactoken($ctoken)
    {
        $time = date("Y-m-d H:i:s");
        Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->update(['token_life' => $time]);
    }
    public static function doToken($token)
    {

        $life = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('token_life');
        if(empty($life))
        {
            //跳普通登陆
            $is_token = 0;
            return $is_token;
        }
        else
        {
            $life = strtotime($life);
            $overtime = $life + (3600 * 24 * 7);
            $nowtime = date("Y-m-d H:i:s");
            if($nowtime > $overtime)
            {
                //token失效,跳普通登陆
                $is_token = 0;
                return $is_token;
            }
            else
            {
                //免登陆成功，刷新life_time字段
                Token::updatatoken($token);
                $is_token = 1;
                return $is_token;
            }
        }
    }
    public static function doCtoken($ctoken)
    {

        $life = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('token_life');
        if(empty($life))
        {
            //跳普通登陆
            $is_ctoken = 0;
            return $is_ctoken;
        }
        else
        {
            $life = strtotime($life);
            $overtime = $life + (3600 * 24 * 7);
            $nowtime = date("Y-m-d H:i:s");
            if($nowtime > $overtime)
            {
                //token失效,跳普通登陆
                $is_ctoken = 0;
                return $is_ctoken;
            }
            else
            {
                //免登陆成功，刷新life_time字段
                Token::updatactoken($ctoken);
                $is_ctoken = 1;
                return $is_ctoken;
            }
        }
    }
}