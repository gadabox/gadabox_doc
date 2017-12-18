<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Aes.php');

//require_once ('Token.php');
Class Userinfo extends controller
{

    //变量函数，选择具体接口功能
    public function modify()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $token = Aes::deaes($imei, $data['token']);
        $value = Aes::deaes($imei, $data['value']);
        $event = Aes::deaes($imei, $data['event']);
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        if ($event == 'phone') {
            $phone = $value;
            $isuid = Db::table('gada_user')
                ->where('user_phone', $phone)
                ->value('user_id');
            if (!empty($isuid)) {
                Response::returnApiError('201', '该手机号已被注册');
            } else {
                Db::table('gada_user')
                    ->where('user_id', $uid)
                    ->update(['user_phone' => $phone]);
                $value = Aes::enaes($imei, $phone);
                Response::returnApiSuccess(200, '更换绑定手机成功', $value);
            }
        }
        if ($event == 'nickname') {
            $nickname = ['username' => $value];
            Db::table('gada_user')
                ->where('user_id', $uid)
                ->update($nickname);
            $value = Aes::enaes($imei, $value);
            Response::returnApiSuccess(200, '设置成功', $value);
        }
        /* if($event == 'phone')
         {
             $phone = ['user_phone'=>$value];
             Db::table('gada_user')
                 ->where('user_id',$uid)
                 ->update($phone);
             $value =  Aes::enaes($imei,$value);
             Response::returnApiSuccess(200,'设置成功',$value);
         }*/
    }

    //三方登录绑定手机
    public function binding()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $phone = Aes::deaes($imei, $data['phone']);
        $uid = Db::table('gada_user')
            ->where('user_phone', $phone)
            ->value('user_id');
        if (!empty($uid)) {
            Response::returnApiError('201', '该手机号已绑定');
        } else {
            $nickname = Aes::deaes($imei, $data['nickname']);
            $avatar = Aes::deaes($imei, $data['avatar']);
            $photo = ['content' => $avatar];
            Db::table('gada_photo')->insert($photo);
            $pic_id = Db::table('gada_photo')
                ->where('content', $avatar)
                ->value('pic_id');
            if (empty($data['w_openid'])) {
                //腾讯接口绑定
                $topenid = Aes::deaes($imei, $data['t_openid']);
                $user = ['username' => $nickname, 'pic_id' => $pic_id, 'topenid' => $topenid, 'user_phone' => $phone];
            } else {
                //微信接口绑定
                $wopenid = Aes::deaes($imei, $data['w_openid']);
                $user = ['username' => $nickname, 'pic_id' => $pic_id, 'wopenid' => $wopenid, 'user_phone' => $phone];
            }

            Db::table('gada_user')->insert($user);
            //生成新的token
            $uid = Db::table('gada_user')
                ->where('pic_id', $pic_id)
                ->value('user_id');
            $data = ['phone' => $phone, 'uid' => $uid];
            $token = Token::token($data);
            // $data = ['token' => $token];
            //给用户这个token
            //换取融云token
            //$photo = '';
            $data = ['username' => $nickname, 'phone' => $phone, 'photo' => $avatar];
            $imtoken = Getrong::getrong($data);
            $imtoken = json_decode($imtoken, true);
            if ($imtoken['code'] !== 200) {
                Response::returnApiError(400, '融云连接失败');
            } else {
                $token = $token . '!' . $imtoken['token'];
                $data = ['token' => $token];
                Response::returnApiSuccess(200, '绑定手机号成功', $data);
            }
        }
    }

    //手机登录绑定三方账号
    public function bindingWechat()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        //$imei = $data['imei'];
        //$token = Aes::deaes($imei, $data['token']);
        $token = $data['token'];
        $w_openid = $data['w_openid'];
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        Db::table('gada_user')
            ->where('user_id',$uid)
            ->update(['wopenid'=>$w_openid]);
        Response::returnApiSuccess(200,'绑定成功',$w_openid);
    }
    public function bindingQQ()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        //$imei = $data['imei'];
        //$token = Aes::deaes($imei, $data['token']);
        $token = $data['token'];
        $t_openid = $data['t_openid'];
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        Db::table('gada_user')
            ->where('user_id',$uid)
            ->update(['topenid'=>$t_openid]);
        Response::returnApiSuccess(200,'绑定成功',$t_openid);
    }

    public function isPassword()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $token = Aes::deaes($imei, $data['token']);
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        $ispassword = Db::table('gada_user')
            ->where('user_id', $uid)
            ->value('password');
        if (empty($ispassword)) {
            $i = 0;
            $value = Aes::enaes($imei, $i);
            // $value = ['data' => $value];

            Response::returnApiSuccess('200', '无密码', $value);
        } else {
            $i = 1;
            $value = Aes::enaes($imei, $i);
            //$value = ['data' => $value];

            Response::returnApiSuccess('200', '已设置密码', $value);
        }

    }

    public function setPassword()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $token = Aes::deaes($imei, $data['token']);
        $psw = Aes::deaes($imei, $data['password']);
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        $psw = md5($psw);
        Db::table('gada_user')
            ->where('user_id', $uid)
            ->update(['password' => $psw]);
        Response::returnApiOk('200', '设置密码成功');
    }

    public function updatePassword()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $token = Aes::deaes($imei, $data['token']);
        $psw = Aes::deaes($imei, $data['old_password']);
        $newpsw = Aes::deaes($imei, $data['new_password']);
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        $password = Db::table('gada_user')
            ->where('user_id', $uid)
            ->value('password');
        if (md5($psw) == $password) {
            $newpsw = md5($newpsw);
            Db::table('gada_user')
                ->where('user_id', $uid)
                ->update(['password' => $newpsw]);
            Response::returnApiOk('200', '新密码设置成功');
        }
        else
        {
            Response::returnApiError('201','原密码输入错误');
        }
    }

    public function upload()
    {
        // if(empty($_FILES))
        $dirPath = './images/';   //设置文件保存的目录

        $imei = $_POST['imei'];
        $token = Aes::deaes($imei, $_POST['token']);
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        $pic_id = Db::table('gada_user')
            ->where('user_id', $uid)
            ->value('pic_id');
        //echo $token;
        //if(!is_dir($dirPath)){
        //目录不存在则创建目录
        //@mkdir($dirPath);
        //  echo '目录不存在';
        //}
        //$count = count($_FILES);//所有文件数
        //if($count<1) die('{"status":0,"msg":"错误提交"}');//没有提交的文件
        //$success = $failure = 0;

        foreach ($_FILES as $key => $value) {
            //循环遍历数据
            //$token = $value['token'];
            $tmp = $value['name'];//获取上传文件名
            $tmpName = $value['tmp_name'];//临时文件路径
            $create_time = date('YmdHis');
            //上传的文件会被保存到php临时目录，调用函数将文件复制到指定目录
            move_uploaded_file($tmpName, $dirPath . $create_time . '_' . $tmp);
            $content = 'http://47.94.157.157:85/public/images/' . $create_time . '_' . $tmp;
            Db::table('gada_photo')
                ->where('pic_id', $pic_id)
                ->update(['content' => $content, 'create_time' => date("Y-m-d H:i:s")]);

            /* if(move_uploaded_file($tmpName,$dirPath.date('YmdHis').'_'.$tmp))
             {
                 $success++;
             }
             else
             {
                 $failure++;
             }*/
        }
        $content = Aes::enaes($imei, $content);
        Response::returnApiSuccess(200, '更换成功', $content);
        //Response::returnApiOk('200','更换成功');
        /*$arr['status'] = 1;
        $arr['msg']   = '提交成功';
        $arr['success'] = $success;
        $arr['failure'] = $failure;
        echo json_encode($arr);*/

    }

    //配送时间选择列表
    public  function timeTable()
    {
        $et = date("H:i ");
        $et0 = date( "H:i ",strtotime("+30 min"));
        //当前时间的分针
        $min = date("i");
        if ($min < 30) {
            $et1 = intval($et);
            // $n = 0;
            $m = 21 - $et1;
            for ($i = 0; $i < $m; $i++) {
                if($i==0)
                {
                    $et2[] = '立即配送（预计' . $et0 . '送达）';
                }
                $et2[] = $et1 . ':' . '30' . '-' . ($et1 + 1) . ':' . '00';
                //$n++;
                $et1++;
            }
        } else {
            $et1 = intval($et)+1;
            //$n = 0;
            $m = 21 - $et1;
            for ($i = 0; $i < $m; $i++) {
                if($i==0)
                {
                    $et2[] = '立即配送（预计' . $et0 . '送达）';
                }
                $et2[] = $et1 . ':' . '00' . '-' . $et1 . ':' . '30';
                //$n++;
                $et1++;
            }
        }
        $data = ['time'=>$et2];
        Response::returnApiSuccess(200,'时间反馈成功',$data);
    }
}