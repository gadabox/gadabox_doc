<?php
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Aes.php');
require_once ('Token.php');
Class Showdata extends controller
{

    //变量函数，选择具体接口功能
    public function showdata()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);

        //$data = [ 'content' => $data['token']];
        $token = $data['token'];
        //判断token是否有效


            $imei = $data['imei'];
            //防止手机不发送识别号
            if (empty($imei) or $imei == 000000000000000) {
                exit;
            }
            //print_r($token);
            /* $aesKey = Db::table('gada_aes')
                 ->where('imei', "$imei")
                 ->find();


             $token = base64_decode($token);*/


            //$token = base64_decode(str_replace(" ","+",$token));


            // $aes = $aesKey['aes_key'];
            // $iv = $aesKey['iv'];
            // $token =  openssl_decrypt($token, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
            $token = Aes::deaes($imei, $token);
        $is_token = Token::doToken($token);
        if($is_token == 0)
        {
            Response::returnApiError(302,'快速登录失败');
        }
        else {
            //Response::returnApiSuccess(200,'反馈成功',$token);
            //截取掉尾部多余的部分
            /* $len = strlen($token);
             $pad = ord($token[$len-1]);
             $token = substr($token, 0, strlen($token) - $pad);*/

            //print_r($token);

            /* $a = '123456';
             $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $aes, $a, MCRYPT_MODE_CBC, $iv);
             $encrypted = base64_encode($encrypted);
             $encrypted = ['a' => $encrypted];*/


            $uid = Db::table('gada_token')
                ->where('user_token', "$token")
                ->value('user_id');
            $user = Db::table('gada_user')
                ->where('user_id', "$uid")
                ->find();
            // $phone = $user['user_phone'];
            // $card_id = $user['card_id'];
            $pic_id = $user['pic_id'];

            $score = $user['score'];
            $card = Db::table('gada_card')
                ->where(['uid'=>$uid,'is_use'=>0,'expired'=>0])
                ->select();
            $card_num = count($card);
            $photo = Db::table('gada_photo')
                ->where('pic_id', "$pic_id")
                ->value('content');
            $balance = Db::table('gada_balance')
                ->where('uid', "$uid")
                ->find();
            //print_r($balance);
            $balance = $balance['reserves'] + $balance['amount'];
            $order = Db::table('gada_order')
                ->where('uid', "$uid")
                ->order('order_num desc')
                ->select();
            $t_openid = Aes::enaes($imei, $user['topenid']);
            $w_openid = Aes::enaes($imei, $user['wopenid']);
            if (empty($order)) {
                //无订单
                $order = 0;
            } else {
                //有订单
                $order = 1;
            }

            //判定是否该用户有未完成的异常订单（以箱子当前有无占用为基准）
            $box_code = Db::table('gada_box')
                ->where('is_user', $uid)
                ->value('box_code');

            if (empty($box_code)) {
                //判定是否有被快递强关订单或者已完成订单
                //查是否有强关订单
                $order_num = Db::table('gada_order')
                    ->join('gada_box', 'gada_order . order_box = gada_box . bid')
                    ->where(['uid' => $uid, 'order_type' => 2, 'order_status' => 5])
                    ->value('order_num');
                if (empty($order_num)) {
                    //不存在强关的订单
                    //存在已完成订单 $force_close = 0   $box_code = null  这里的order=0表示无使用中的订单
                    $box_code = '';
                    $order = 0;
                    $force_close = 0;
                    $force_close = Aes::enaes($imei, $force_close);
                    $user['username'] = Aes::enaes($imei, $user['username']);
                    $user['user_phone'] = Aes::enaes($imei, $user['user_phone']);
                    $photo = Aes::enaes($imei, $photo);
                    $card_num = Aes::enaes($imei, $card_num);
                    $balance = Aes::enaes($imei, $balance);
                    $score = Aes::enaes($imei, $score);
                    $order = Aes::enaes($imei, $order);
                    $box_code = Aes::enaes($imei, $box_code);
                    $data = ['force_close' => $force_close, 'userName' => $user['username'], 'phone' => $user['user_phone'], 'avatar' => $photo, 'card' => $card_num, 'balance' => $balance, 'score' => $score, 'order' => $order, 'box_code' => $box_code, 'w_openid' => $w_openid, 't_openid' => $t_openid];
                } else {
                    //存在快递强关而未读的订单 $force_close = 1
                    $force_close = 1;
                    $force_close = Aes::enaes($imei, $force_close);
                    $box_code = Db::table('gada_order')
                        ->join('gada_box', 'gada_order . order_box = gada_box . bid')
                        ->where('order_num', $order_num)
                        ->value('box_code');
                    $order_create_time = Db::table('gada_order')
                        ->where('order_num', $order_num)
                        ->value('order_create_time');
                    $order_create_time = strtotime($order_create_time) * 1000;
                    $current_time = time() * 1000;
                    $hourly_rate = 1;

                    $order_create_time = Aes::enaes($imei, $order_create_time);
                    $current_time = Aes::enaes($imei, $current_time);
                    $hourly_rate = Aes::enaes($imei, $hourly_rate);
                    $user['username'] = Aes::enaes($imei, $user['username']);
                    $user['user_phone'] = Aes::enaes($imei, $user['user_phone']);
                    $photo = Aes::enaes($imei, $photo);
                    $card_num = Aes::enaes($imei, $card_num);
                    $balance = Aes::enaes($imei, $balance);
                    $score = Aes::enaes($imei, $score);
                    $order = Aes::enaes($imei, $order);
                    $box_code = Aes::enaes($imei, $box_code);
                    $data = ['force_close' => $force_close, 'order_create_time' => $order_create_time, 'current_time' => $current_time, 'hourly_rate' => $hourly_rate, 'userName' => $user['username'], 'phone' => $user['user_phone'], 'avatar' => $photo, 'card' => $card_num, 'balance' => $balance, 'score' => $score, 'order' => $order, 'box_code' => $box_code, 'w_openid' => $w_openid, 't_openid' => $t_openid];
                }
            } else {
                //有正在被打开用着的箱子
                $order_num = Db::table('gada_order')
                    ->join('gada_box', 'gada_order . order_box = gada_box . bid')
                    ->where(['uid' => $uid, 'order_type' => 2, 'order_status' => 0])
                    ->value('order_num');
                if (!empty($order_num)) {
                    //存在正打开未完成的订单 $force_close = 0
                    $force_close = 0;
                    $force_close = Aes::enaes($imei, $force_close);
                    $order_create_time = Db::table('gada_order')
                        ->where(['uid' => $uid, 'order_type' => 2, 'order_status' => 0])
                        ->value('order_create_time');
                    $order_create_time = strtotime($order_create_time) * 1000;
                    $current_time = time() * 1000;
                    $hourly_rate = 1;
                    $order_create_time = Aes::enaes($imei, $order_create_time);
                    $current_time = Aes::enaes($imei, $current_time);
                    $hourly_rate = Aes::enaes($imei, $hourly_rate);
                    $user['username'] = Aes::enaes($imei, $user['username']);
                    $user['user_phone'] = Aes::enaes($imei, $user['user_phone']);
                    $photo = Aes::enaes($imei, $photo);
                    $card_num = Aes::enaes($imei, $card_num);
                    $balance = Aes::enaes($imei, $balance);
                    $score = Aes::enaes($imei, $score);
                    $order = Aes::enaes($imei, $order);
                    $box_code = Aes::enaes($imei, $box_code);
                    $data = ['force_close' => $force_close, 'order_create_time' => $order_create_time, 'current_time' => $current_time, 'hourly_rate' => $hourly_rate, 'userName' => $user['username'], 'phone' => $user['user_phone'], 'avatar' => $photo, 'card' => $card_num, 'balance' => $balance, 'score' => $score, 'order' => $order, 'box_code' => $box_code, 'w_openid' => $w_openid, 't_openid' => $t_openid];
                } else {
                    //正在使用中（配送中）的订单  $force_close = 0   $box_code = null
                    $box_code = '';
                    $force_close = 0;
                    $force_close = Aes::enaes($imei, $force_close);
                    $user['username'] = Aes::enaes($imei, $user['username']);
                    $user['user_phone'] = Aes::enaes($imei, $user['user_phone']);
                    $photo = Aes::enaes($imei, $photo);
                    $card_num = Aes::enaes($imei, $card_num);
                    $balance = Aes::enaes($imei, $balance);
                    $score = Aes::enaes($imei, $score);
                    $order = Aes::enaes($imei, $order);
                    $box_code = Aes::enaes($imei, $box_code);
                    $data = ['force_close' => $force_close, 'userName' => $user['username'], 'phone' => $user['user_phone'], 'avatar' => $photo, 'card' => $card_num, 'balance' => $balance, 'score' => $score, 'order' => $order, 'box_code' => $box_code, 'w_openid' => $w_openid, 't_openid' => $t_openid];
                }
            }


            /* $user['username'] = base64_encode(openssl_encrypt($user['username'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $user['user_phone'] = base64_encode(openssl_encrypt($user['user_phone'], 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $photo = base64_encode(openssl_encrypt($photo, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $card_num = base64_encode(openssl_encrypt($card_num, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $balance = base64_encode(openssl_encrypt($balance, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $score = base64_encode(openssl_encrypt($score, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
             $order = base64_encode(openssl_encrypt($order, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));*/


            Response::returnApiSuccess(200, '反馈成功', $data);
        }
        //Response::returnApiSuccess(200,'登陆成功',$token);
    }
     function addPkcs7Padding($string, $blocksize = 16)
     {
        $len = strlen($string); //取得字符串长度
        $pad = $blocksize - ($len % $blocksize); //取得补码的长度
        $string .= str_repeat(chr($pad), $pad); //用ASCII码为补码长度的字符， 补足最后一段
        return $string;
    }
        //$this->addPkcs7Padding($str)
    public static function wechat($uid)
    {
        $user = Db::table('gada_user')
            ->where('user_id', "$uid")
            ->find();
        //print_r($user);
         $phone = $user['user_phone'];
       // $card_id = $user['card_id'];
        $pic_id = $user['pic_id'];

        $score = $user['score'];
        $card =  Db::table('gada_card')
            ->where('uid', "$uid")
            ->select();
        $card_num = count($card);
        $photo =  Db::table('gada_photo')
            ->where('pic_id', "$pic_id")
            ->value('content');
        $balance =  Db::table('gada_balance')
            ->where('uid', "$uid")
            ->find();
        //print_r($balance);
        $balance = $balance['reserves'] + $balance['amount'];
        $order = Db::table('gada_order')
            ->where('uid', "$uid")
            ->select();
        if(empty($order))
        {
            $order = 0;
        }
        else
        {
            $order = 1 ;
        }


        $data = ['userName' => $user['username'],'phone' => $phone,'avatar' => $photo,'card' => $card_num,'balance' => $balance,'score' => $score,'order' => $order];
        return $data;
    }
}