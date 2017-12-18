<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/31
 * Time: 10:51
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
//use think\Response;

require_once ('Token.php');
Class Tencent extends controller
{
    //变量函数，选择具体接口功能
    public function tencent()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);

        $topenid = $data['openid'];
        $imei = $data['imei'];
        $nickname = $data['nickname'];
        $avatar = $data['avatar'];

        $uid = Db::table('gada_user')
            ->where('topenid', "$topenid")
            ->find();
        //未注册
        if (empty($uid)) {
            $data = ['token' => ''];
            Response::returnApiSuccess(200,'可绑定手机号',$data);

        } else {
            //刷新token生存周期(清理老token)
            Db::table('gada_token')
                ->where('user_id', $uid['user_id'])
                ->delete();
            $data = ['phone' => $uid['user_phone'], 'uid' => $uid['user_id']];
            $token = Token::token($data);
            // $data = ['token' => $token];
            //给用户token

            /* $uid = Db::table('gada_token')
                 ->where('user_token', "$token")
                 ->value('user_id');*/
            /* $user = Db::table('gada_user')
                 ->where('user_id', "$uid")
                 ->find();*/
            $username = $uid['username'];
            $phone = $uid['user_phone'];
            $photo = $uid['pic_id'];
            $photo = Db::table('gada_photo')
                ->where('pic_id', $photo)
                ->value('content');
            // $data = ['userName'=>$username,'avatar'=>$photo,'phone'=>$phone,'gadaToken'=>$token];
            // $photo = '';
            $data = ['username' => $username, 'phone' => $phone, 'photo' => $photo];
            $imtoken = Getrong::getrong($data);
            $imtoken = json_decode($imtoken, true);
            if ($imtoken['code'] !== 200) {
                Response::returnApiError(400, '融云连接失败');
            } else {
                $token = $token . '!' . $imtoken['token'];
                $phone = Aes::enaes($imei,$phone);
                $data = ['token' => $token,'phone'=>$phone];
            }
            Response::returnApiSuccess(200, '登录成功', $data);
        }
    }
}
