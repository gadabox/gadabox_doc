<?php
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/7/20
 * Time: 15:27
 */

require_once ('Response.php');
use \think\Controller;
use \think\Db;
use \app\index\model\Photo;
//use think\Response;

require_once ('Getrong.php');
require_once ('Token.php');
//use \app\index\model\User as UserModel;

      //  $ata = input('get.a');
      //  Login::recLogin($ata);
       // Login::login($ata);
        Class Login extends controller
        {

            //变量函数，选择具体接口功能
            public function login()
            {
                //登陆的判定
                $jsdata = file_get_contents('php://input');
                //  Response::returnApiSuccess(200, '快速登陆登陆成功');

                //  print_r($jsdata);
                //没接到数据直接默认调用失败

                if (empty($jsdata)) {
                    Response::returnApiError(203, '接口调用失败');
                    return;
                }
                //前端传来的json数据解析成object文件 务必转换从array否则无法提取数据
                $data = json_decode($jsdata, true);

                if (empty($data['password'])) {
                    //  echo $a = 2;
                    Login::fastLogin($data);
                } else {
                    //  echo $a = 3;
                    Login::recLogin($data);
                }
            }

            public static function recLogin($msg)
            {
                //  print_r($msg['user_phone']);  //测试

                //提取出数据中的手机号和登陆密码并进行比对

                // echo $msg;

                //  这数据库连接部分有问题(已解决)
                //  $user = $msg;
                $user = $msg['user_phone'];
                $pwd = $msg['password'];
                $password = Db::table('gada_user')
                    ->where('user_phone', "$user")
                    ->value('password');
                //  Response::returnApiError(200,'登陆成功');
               if(empty($password))
               {
                   Response::returnApiError(201,'账户未设置密码请转快速登陆');
                    return;
               }
               if(md5($pwd) !== $password)
               {
                   Response::returnApiError(202,'密码错误');
                   return;
               }
                   //比对成功，给用户分配token值

            //判定是否之前存在该用户的token （不需要）
            $uid = Db::table('gada_user')
                ->where('user_phone',$user)
                ->value('user_id');
        /*
            $tid = Db::table('gada_token')
                ->where('user_id',$uid)
                ->value('tid');*/
            /*$token = Db::table('gada_user')
                ->where('user_id',$uid)
                ->value('user_token');
        */


            //清理token缓存
                Db::table('gada_token')
                    ->where('user_id',$uid)
                    ->delete();
            //直接调用接口生成token
                $date = ['phone' => $user, 'uid' => $uid];
                $gadatoken = Token::token($date);
              //  $data = ['token' => $data];
            //给用户token

                $uid = Db::table('gada_token')
                    ->where('user_token', "$gadatoken")
                    ->value('user_id');
                $user = Db::table('gada_user')
                    ->where('user_id', "$uid")
                    ->find();
                $username = $user['username'];
                $phone = $user['user_phone'];
                $photo = $user['pic_id'];
                $photo = Db::table('gada_photo')
                    ->where('pic_id',$photo)
                    ->value('content');
                $data = ['username'=>$username,'avatar'=>$photo,'phone'=>$phone,'photo'=>$photo];
                //换取融云的token
                $imtoken = Getrong::getrong($data);
                $imtoken = json_decode($imtoken,true);
                if($imtoken['code'] !== 200)
                {
                    Response::returnApiError(400,'融云连接失败');
                }
                else
                {
                    $token = $gadatoken . '!' . $imtoken['token'];
                    $token = ['token' => $token];
                    //    Response::returnToken($token);
                    Response::returnApiSuccess(200, '登录成功', $token);
                }
            //存在的话 更新token生存时间（不需要）
        /*  else
            {
                Token::updatatoken($uid);
            }
        */
        }
            public function fastLogin($phone,Photo $pic)
            {
                //短信快速登陆的情况(接收手机号码)
               // $phone = file_get_contents('php://input');
               // $phone = json_decode($phone, true);
                $uid = Db::table('gada_user')
                    ->where('user_phone',$phone['user_phone'])
                    ->value('user_id');
                //若没注册过
                if(empty($uid))
                {
                   // $data = ['user_phone' => $phone['user_phone']];
                   //随机一个用户名
                    $num = rand(100000, 999999);
                    $username = 'gada' .$num;
                    //先申请一个头像库
                    //$pic = new Photo;
                    $pic->create_time = date("Y-m-d H:i:s");
                    $pic->save();
                    $pic_id = $pic->pic_id;

                    $phone = ['username' => $username,'user_phone' => $phone['user_phone'],'pic_id'=>$pic_id];
                    Db::table('gada_user')->insert($phone);
                    //生成新的token
                    $uid = Db::table('gada_user')
                        ->where('user_phone',$phone['user_phone'])
                        ->value('user_id');
                    $data = ['phone' => $phone['user_phone'],'uid' => $uid];
                    $token = Token::token($data);
                   // $data = ['token' => $token];
                    //给用户这个token
                    //换取融云token
                    $photo = 'http://47.94.157.157:85/public/images/3.png';
                    $data = ['username'=>$username,'phone'=>$phone['user_phone'],'photo'=>$photo];
                    $imtoken = Getrong::getrong($data);
                    $imtoken = json_decode($imtoken,true);
                    if($imtoken['code'] !== 200)
                    {
                        Response::returnApiError(400,'融云连接失败');
                    }
                    else
                    {
                        $token = $token . '!' . $imtoken['token'];
                        $data = ['token' => $token];
                    }


                }
                //注册过的老用户
                else
                {
                //刷新token生存周期(清理老token)
                    Db::table('gada_token')
                        ->where('user_id',$uid)
                        ->delete();
                    $data = ['phone' => $phone['user_phone'],'uid' => $uid];
                    $token = Token::token($data);
                   // $data = ['token' => $token];
                    //给用户token

                    $uid = Db::table('gada_token')
                        ->where('user_token', "$token")
                        ->value('user_id');
                    $user = Db::table('gada_user')
                        ->where('user_id', "$uid")
                        ->find();
                    $username = $user['username'];
                    $phone = $user['user_phone'];
                    $photo = $user['pic_id'];
                    $photo = Db::table('gada_photo')
                        ->where('pic_id',$photo)
                        ->value('content');
                   // $data = ['userName'=>$username,'avatar'=>$photo,'phone'=>$phone,'gadaToken'=>$token];
                   // $photo = '';
                    $data = ['username'=>$username,'phone'=>$phone,'photo'=>$photo];
                    $imtoken = Getrong::getrong($data);
                    $imtoken = json_decode($imtoken,true);
                    if($imtoken['code'] !== 200)
                    {
                        Response::returnApiError(400,'融云连接失败');
                    }
                    else
                    {
                        $token = $token . '!' . $imtoken['token'];
                        $data = ['token' => $token];
                    }

                }

                Response::returnApiSuccess(200, '快速登录成功',$data);


            }

}




