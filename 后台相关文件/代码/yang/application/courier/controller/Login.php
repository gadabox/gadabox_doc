<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/22
 * Time: 16:37
 */
namespace app\courier\controller;
use app\index\controller\Getrong;
use app\index\controller\Response;
header ( "Content-Type: application/json; charset=utf-8" );
use app\index\controller\Token;
use \think\Controller;
use \think\Db;

class Login
{
    public function login()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $courier_phone = $data['courier_phone'];

        $is_register = Db::table('gada_register')
            ->where('cphone',$courier_phone)
            ->value('is_register');
        $courier = Db::table('gada_courier')
            ->where('cphone',$courier_phone)
            ->find();

        if(empty($is_register))
        {
            Response::returnApiError(201,'当前账号未注册,请先注册');
        }
        elseif($is_register == 2)
        {
            Response::returnApiError(201,'您的申请信息还未通过审核，请耐心等待通知');
        }
        elseif(empty($courier))
        {
            Response::returnApiError(201,'登录失败');
        }
        else
        {
            $ctoken = Token::ctoken($courier);
            $cid = Db::table('gada_ctoken')
                ->where('courier_token', $ctoken)
                ->value('id_courier');
            $courier_msg = Db::table('gada_courier')
                ->where('id_courier', "$cid")
                ->find();
            $courier_name = $courier_msg['cname'];
            $phone = $courier_msg['cphone'];
            $photo = $courier_msg['pic_id'];
            $photo = Db::table('gada_cphoto')
                ->where('pic_id',$photo)
                ->value('content');
            // $data = ['userName'=>$username,'avatar'=>$photo,'phone'=>$phone,'gadaToken'=>$token];
            // $photo = '';
            $data = ['username'=>$courier_name,'phone'=>$phone,'photo'=>$photo];
            $imtoken = Getrong::getrong($data); /*Getrong::getrong($data);*/
            $imtoken = json_decode($imtoken,true);
            if($imtoken['code'] !== 200)
            {
                Response::returnApiError(400,'融云连接失败');
            }
            else
            {
                $token = $ctoken . '!' . $imtoken['token'];
                $data = ['token' => $token];
            }
            Response::returnApiSuccess(200,'登录成功',$data);
        }
    }

}