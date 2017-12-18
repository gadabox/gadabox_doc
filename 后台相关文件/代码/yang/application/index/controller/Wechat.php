<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/28
 * Time: 11:51
 */
namespace app\index\controller;
require_once('Response.php');
//require_once ('Showdata.php');
use \think\Db;
use \think\Controller;
//use think\Response;

Class Wechat extends controller
    {
        public function wechat()
        {
            $data = file_get_contents('php://input');
            $data = json_decode($data, true);
            $imei = $data['imei'];
            $wopenid = $data['openid'];

            //print_r($data['openid']);
            /*  $imei = $data['imei'];

              //防止手机不发送识别号
              if(empty($imei) or $imei ==000000000000000)
              {
                  exit;
              }
              //print_r($token);
              $aesKey = Db::table('gada_aes')
                  ->where('imei', "$imei")
                  ->find();


              $openid = base64_decode($openid);
              $nickname = base64_decode($nickname);
              $avatar = base64_decode($avatar);

              //$token = base64_decode(str_replace(" ","+",$token));


              $aes = $aesKey['aes_key'];
              $iv = $aesKey['iv'];
              $openid =  openssl_decrypt($openid, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
              $nickname =  openssl_decrypt($nickname, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
              $avatar =  openssl_decrypt($avatar, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);*/

            //Response::returnApiSuccess(200,'反馈成功',$token);
            //截取掉尾部多余的部分
            /* $len = strlen($openid);
             $pad = ord($openid[$len-1]);
             $openid = substr($openid, 0, strlen($openid) - $pad);

             $len = strlen($nickname);
             $pad = ord($nickname[$len-1]);
             $nickname = substr($nickname, 0, strlen($nickname) - $pad);

             $len = strlen($avatar);
             $pad = ord($avatar[$len-1]);
             $avatar = substr($avatar, 0, strlen($avatar) - $pad);*/

             $uid = Db::table('gada_user')
                 ->where('wopenid', "$wopenid")
                 ->find();
             //未注册
             if(empty($uid)) {
                     $data = ['token' => ''];
                     Response::returnApiSuccess(200,'可绑定手机号',$data);

             }
            //已注册过
               else
            {
                /* $data =Showdata::wechat($uid);
                 $data['userName'] = base64_encode(openssl_encrypt($data['username'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['phone'] = base64_encode(openssl_encrypt($data['phone'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['avatar'] = base64_encode(openssl_encrypt($data['avatar'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['card'] = base64_encode(openssl_encrypt($data['card'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['balance'] = base64_encode(openssl_encrypt($data['balance'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['score'] = base64_encode(openssl_encrypt($data['score'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
                 $data['order'] = base64_encode(openssl_encrypt($data['order'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));*/

                //刷新token生存周期(清理老token)
                 Db::table('gada_token')
                     ->where('user_id',$uid['user_id'])
                     ->delete();
                 $data = ['phone' => $uid['user_phone'],'uid' => $uid['user_id']];
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
                     //$token = Aes::enaes($imei,$token);
                     $phone = Aes::enaes($imei,$phone);
                     $data = ['token' => $token,'phone'=>$phone];
                 }
                 Response::returnApiSuccess(200,'登录成功',$data);
             }
            }

        }