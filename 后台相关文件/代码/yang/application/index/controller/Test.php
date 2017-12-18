<?php
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/7/19
 * Time: 15:41
 */
require_once ('Constants.php');
require_once ('Response.php');
require_once ('Token.php');
require_once ('Rongcloud.php');
require_once ('Aes.php');
include_once(dirname(__FILE__) . '/autoload.php');

//use app\imapi\controller;
use app\index\model\User;
use app\index\model\Version;
use \think\Db;
use \think\Controller;
//use \imapi\controller\Rongcloud;
use think\Session;
use xmpush\Builder;
use xmpush\HttpBase;
use xmpush\Sender;
use xmpush\Constants;
use xmpush\Stats;
use xmpush\Tracer;
use xmpush\Feedback;
use xmpush\DevTools;
use xmpush\Subscription;
use xmpush\TargetedMessage;
const S = 2;
Class Test
{

    /*public function forceClose()
    {
        $box_code = 123456;
        $is_close = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('is_close');

        $uid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('is_user');
        Db::table('gada_box')
            ->where('box_code', $box_code)
            ->update(['is_user' => '', 'is_lock' => 1]);
        $order_num = Db::table('gada_order')
            ->where(['order_type' => 2, 'order_status' => 0])
            ->value('order_num');


        //进行强制扣费处理
        $order_create_time = Db::table('gada_order')
            ->where('order_num', $order_num)
            ->value('order_create_time');
        $hourly_rate = 1;
        $order_create_time = strtotime($order_create_time);
        $current_time = time();
        $over_time = date('Y:m:d H:i:s', $current_time);
        //十分钟之内不收费，十分钟之后开始计时
        $time = $current_time - $order_create_time;
        if ($time <= 600) {
            //不收取费用
            $price = '';
            $is_pay = 1;
        } else {
            //超过十分钟，收取费用
            $hourly_rate = 1;
            $price = $hourly_rate * ceil(($time - 600) / 3600);
            //占用时每小时的计费标准（1元）走账
            $balance = Db::table('gada_balance')
                ->where('uid', $uid)
                ->find();
            if ($balance['reserves'] > $price || $balance['reserves'] = $price) {
                //优先扣除冻结账户
                $balance['reserves'] = $balance['reserves'] - $price;
                //用于记录是否成功计费
                $is_pay = Db::table('gada_balance')
                    ->where('uid', $uid)
                    ->update($balance);
            } else {
                //冻结账户金额不足，直接扣除余额（无论是否够用）
                $balance['amount'] = $balance['amount'] - $price;
                $is_pay = Db::table('gada_balance')
                    ->where('uid', $uid)
                    ->update($balance);
            }
            //记录交易明细
            $current_balance = $balance['amount'] + $balance['reserves'];
            //保留两位小数方便观看
            $current_balance = number_format($current_balance, 2);
            //price为实际要支付的价格
            Db::table('gada_transact')
                ->insert(['user_id' => $uid, 'transact_content' => '截止快递关闭箱子时产生的占用费用', 'transact_detail' => '-' . $price, 'current_balance' => $current_balance]);

        }
        //更新订单状态
        if ($is_pay != 1) {
            //走账失败
            print_r('扣费失败');
        } else {
            Db::table('gada_order')
                ->where(['order_num' => $order_num])
                ->update(['order_status' => 5, 'order_price' => $price, 'order_over_time' => $over_time]);
            //扣分记录
            Db::table('gada_score')
                ->insert(['score_content' => '被监管员关闭嘎哒箱', 'user_id' => $uid, 'score_detail' => '-5']);
            $score = Db::table('gada_user')
                ->where('user_id', $uid)
                ->value('score');
            $score = $score - 5;
            Db::table('gada_user')
                ->where('user_id', $uid)
                ->update(['score' => $score]);

            //配合前端格式
            $order_create_time = $order_create_time * 1000;
            $current_time = $current_time * 1000;


            //释放完箱子并更改完订单状态，再传送透传信息


            $secret = '/JSYm04FIlOV97gBklQcEw==';
            $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
            Constants::setPackage($package);
            Constants::setSecret($secret);

            //$aliasList = array('alia1', 'alias2');

            $phone = 18210968916;

            $title = '你好,maomin';
            $desc = '这是一条来自神秘服务器的推送消息';
            $payload = '{"type":2,"order_create_time":' . $order_create_time . ',"current_time":' . $current_time . ',"hourly_rate":' . $hourly_rate . '}';


            $message = new Builder();
            $message->title($title);
            $message->description($desc);
            $message->notifyType(1);
            $message->passThrough(1);
            $message->payload($payload);
            $message->extra(Builder::notifyEffect, 1);
            $message->notifyId(1);
            $message->timeToSend(0);
            $message->build();

            $targetMessage = new TargetedMessage();
            $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
            $targetMessage->setMessage($message);

            $sender = new Sender();
            $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
        }
    }*/

    public function openBox()
    {
        $box_code = 123456;
        Db::table('gada_box')
            ->where('box_code',$box_code)
            ->update(['is_close'=>0]);

        //盖被打开的透传测试接口
        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'u18210968916';

        $title = '你好,maomin';
        $desc = '这是一条来自神秘服务器的推送消息';
        $payload = '{"type":1,"content":"箱子已开盖"}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(1);
        $message->payload($payload);
        $message->extra(Builder::notifyEffect, 1);
        // $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        // $message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        //$sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
        //开盖

    }

    public function weight()
    {
        $box_code = 123456;
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        Db::table('gada_order')
            ->where(['order_box' => $bid, 'order_type' => 2, 'order_status' => 0])
            ->update(['order_weight' => 4]);

    }

    public function server()
    {
        $box_code = 123456;
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        $current_time = date("Y-m-d H:i:s");
        Db::table('gada_order')
            ->where(['order_box' => $bid, 'order_type' => 0, 'order_status' => 0])
            ->update(['order_status' => 0,  'courier' => 12,'order_current_time'=>$current_time]);

        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'c15935105767';

        $title = '你好,maomin';
        $desc = '这是一条来自神秘服务器的推送消息';
        $payload = '{"type":4}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(0);
        $message->payload($payload);
        //$message->extra(Builder::notifyEffect, 1);
         $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
         $message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
       // $sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    }

    public static function changeMsg($msg,$type)
    {
        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'u18210968916';

        $title = '你好,maomin';
        $desc = $msg;
        $payload = '{"type":'. $type .',"order_num":778861335}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(0);
        $message->payload($payload);
        //$message->extra(Builder::notifyEffect, 0);
         $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        //$message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        // $sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    }
    public static function userPassThrough($msg,$type)
    {
        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'u18210968916';

        $title = '你好,maomin';
        $desc = $msg;
        $payload = '{"type":'. $type .',"order_num":778861335}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(1);
        $message->payload($payload);
        //$message->extra(Builder::notifyEffect, 0);
        $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        //$message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        // $sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    }
    public static function courierNotice($type,$msg,$order_number)
    {
        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'c15935105767';

        $title = '你好,maomin';
        $desc = $msg;
        $payload = '{"type":'. $type .',"order_num": '. $order_number .'}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(0);
        $message->payload($payload);
        //$message->extra(Builder::notifyEffect, 0);
        $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        //$message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        // $sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    }
    public function test()
    {
        //$over_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s").'+1 year'));
       /* $recommend_card = Db::table('gada_card')
            ->where(['uid' => 5,'is_use'=>0,'expired'=>0])
            ->where('card_value<=10')
            ->order('card_value asc,over_time asc')
            ->field('card_value,card_id,over_time')
            ->select();
        print_r($recommend_card);*/
       /* Db::table('gada_version')
            ->where('vid',1)
            ->update(['create_time'=>'2017-11-20 12:00:00']);*/

        //$user -> save(['create_time'=>time()],['vid'=>2]);
      // $a = 1512984100;
        $box_num = 'gada008';
        $box_msg = Db::table('gada_box')
            ->where('box_num',$box_num)
            ->find('box_code,model_num');
        print_r($box_msg);
        //$a['create_time'] = intval(strtotime($a['create_time']));
        //$a ->toArray($a);


        /*$card = Db::table('gada_card')
            ->where(['is_use'=>0,'expired'=>0])
            ->field('uid,is_use,expired,count(card_id)')
            ->group('uid')
            ->select();
        var_dump($card);*/
        /*$order_msg = Db::table('gada_order')
            ->where('order_num',737722907)
            ->find();
        //默认送达时间随时取消均不扣费，调整时间的话以预计送达区间为基准，进入后则开始扣费
        $expected_time = substr($order_msg['order_dispatch_time'],0,5);
        //if($expected_time == '立') {
            print_r($expected_time);*/
        //}
        //
        //bbBfAcefBdZEvDfH
        //FD6888BF66919E5DF403E576871AA990
        /*$stringA="appid=wx92dd9aaefbfa74d8&attach=支付测试&body=gadabox&mch_id=1488123732&nonce_str=1add1a30ac87aa2db72f57a2375d8fec&notify_url=http://wxpay.wxutil.com/pub_v2/pay/notify.v2.php&out_trade_no=1415659990&spbill_create_ip=14.23.150.211&total_fee=1&trade_type=APP&key=bbBfAcefBdZEvDfHwxddAAefBfaGDbMW";
        $a = md5($stringA);
        print_r($a);*/
        /*$recharge_money = 10;
        $nonce_str = Test::nonce_str(24);
        $recharge_number = Test::rechargeNumber();
        $notify_url = '&' . 'notify_url';
        $stringA="appid=wx92dd9aaefbfa74d8&attach=支付充值&body=gadabox&mch_id=1488123732&nonce_str={$nonce_str}&notify_url=http://47.94.157.157/index/recharge/notify&out_trade_no={$recharge_number}&spbill_create_ip=14.23.150.211&total_fee={$recharge_money}&trade_type=APP&key=bbBfAcefBdZEvDfHwxddAAefBfaGDbMW";
        $sign = md5($stringA);
        //所有字符大写
        $sign =strtoupper($sign);
        $a = "<xml>
               <appid>wx92dd9aaefbfa74d8</appid>
               <attach>支付充值</attach>
               <body>gadabox</body>
               <mch_id>1488123732</mch_id>
               <nonce_str>{$nonce_str}</nonce_str>
               <notify_url>http://47.94.157.157/index/recharge/notify</notify_url>
               <out_trade_no>{$recharge_number}</out_trade_no>
               <spbill_create_ip>14.23.150.211</spbill_create_ip>
               <total_fee>{$recharge_money}</total_fee>
               <trade_type>APP</trade_type>
               <sign>{$sign}</sign>
                </xml>";
        $curl = curl_init();

//设置url
        curl_setopt($curl, CURLOPT_URL,"https://api.mch.weixin.qq.com/pay/unifiedorder");

//设置发送方式：post
        curl_setopt($curl, CURLOPT_POST, true);

//设置发送数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $a);

//TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

//执行cURL会话 ( 返回的数据为xml )
        $return_xml = curl_exec($curl);

//关闭cURL资源，并且释放系统资源
        curl_close($curl);

        $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $value_array = json_encode($value_array);
        print_r($value_array);*/


        /*$a  =  date( "H:i:s ",strtotime("+30 min"));
        print_r($a);*/
        /* $b = strtotime('2017-10-09 16:23:38');
         $a = date('Y-m-d H:i:s');
         $a = strtotime($a);
         $c = $a - $b;
         $d = intval($c/3600/24);
         $h = intval(($c%(3600*24))/3600);
         $m = (intval(($c%(3600*24))/60))/60;
         $m = number_format($m,2);
         $time = $h + $m;
         print_r($time);*/
        //echo "两个时间相差 $d 天 $h 小时 $m 分";
        //print_r($c);
       /* $area = '北京';
        $address = '海淀区慧科大厦9f';
        $amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address='.$area . $address);
        $amap = json_decode($amap,true);
        $str=explode(",",$amap['geocodes'][0]['location']);
        $address_longidute = $str[(count($str)-1)];
        $address_latiude = $str[0];
        echo $address_longidute;
        echo $address_latiude;*/
        /*$origin = '116.276062,39.932619';
        $destination = '116.276477,39.953957';
        $amap = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin='.$origin .'&destination=' .$destination);
        $amap = json_decode($amap,true);
        $distance = $amap['data']['paths'][0]['distance']/1000;
         print_r($distance);*/
        //['geocodes'][0]['location']   地址名的坐标信息位置
        //['data']['paths'][0]['distance']  两地址之间骑行距离计算
                /* $receiver_phone[$i] = Db::table('gada_user')
                     ->where('user_id', $paid[$i]['uid'])
                     ->value('user_phone');*/
               /* if($paid[$i]['change_address' != 2])
                {
                    $data[$i] = ['order_number' => $paid[$i]['order_num'], 'time' => $paid[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $paid[$i]['address'], 'receiver_area' => $paid[$i]['area'], 'receiver' => $paid[$i]['receiver_name'], 'receiver_phone' => $paid[$i]['receiver_phone'], 'distance' => $paid[$i]['order_distance'],'change_address'=>$paid[$i]['change_address']];
                }
                else
                {
                    $data[$i] = ['order_number' => $paid[$i]['order_num'], 'time' => $paid[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $paid[$i]['change_receiver_address'], 'receiver' => $paid[$i]['change_receiver_name'], 'receiver_phone' => $paid[$i]['change_receiver_phone'], 'distance' => $paid[$i]['order_distance'],'change_address'=>$paid[$i]['change_address']];
                }*/

    }
    public function test2()
    {
        $id_courier = 5;
        $dispatch = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>DISPATCH])
            ->field('order_num,order_box,order_price,second_price,address,area,receiver_name,receiver_phone,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address')
            ->select();
        print_r($dispatch);
    }
    public static function nonce_str($length)
    {
        //$length =32;
        $str = '0123456789abcdefghijklmnopstuvwxyz';//36个字符
        $str = str_shuffle($str);
        $str = substr($str, 0, $length);
        //print_r($str);
        return $str;
    }
    public static function rechargeNumber()
    {
        //生成充值订单编号
        //（时间+10位随机数）
        for($i = 0;$i<999;$i++)
        {
            $str = Test::nonce_str(3);
            $time = time();
            $time = substr($time, -6);
            $num = '';
            for ($i = 0; $i < 3; $i++)
            {
                $n = rand(0, 9);
                $num .= $n;
            }
            $order_num = $str . $time . $num;
            $is_exist = Db::table('gada_paybasic')
                ->where('pay_order_number',$order_num)
                ->value('create_time');
            if(empty($is_exist))
            {
                break;
            }
        }
        return $order_num;
    }
    /*$server_version = Db::table('gada_version')
        ->order('version_create_time desc')
        ->limit(1)
        ->field('version_code,version_url')
        ->find();
    print_r($server_version);*/

    /* $nickname = 'abc';
     $openid = '123';
     $avatar = 'http://wx.qlogo.cn/mmopen/picIicdnwNdvLBRGOX181SibETf4ThnWgqia4icaL9okVln8eCohNb';
     $photo = ['content' => $avatar];
     Db::table('gada_photo')->insert($photo);
     $pic_id = Db::table('gada_photo')
         ->where('content',$avatar)
         ->value('pic_id');

     $user = ['username' => $nickname, 'pic_id' => $pic_id, 'openid' => $openid];

     Db::table('gada_user')->insert($user);*/

    /* $token = 'C3n4B8F4OgUbbhcf05qBjgYHuKPZAIRpI3o5NGxVSRXymHAuaQpOGJTKmasmA2E7';
     $imei = '866260033645485';
     $key = Db::table('gada_aes')
         ->where('imei', $imei)
         ->find();
     $aes = $key['aes_key'];
     $iv = $key['iv'];
     $token = base64_decode($token);
     $encode = openssl_decrypt($token, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv);
     //$token = Aes::deaes($imei,$token);
     print_r($encode);*/
    /*$i = 0;
    $uid = 5;
    $list = Db::table('gada_order')
        ->where('uid' , $uid)
        ->where('order_type', 0)
        ->where('order_status' ,'between','1,3')
        ->field('order_num')
        ->select();
    print_r($list);*/
    /*$imei = 866260033645485;
    $card_num = 'UhtKMDY884OaJb9srjexYQ==';
    //$card_num = Aes::enaes($imei,$card_num);
    $card_num = Aes::deaes($imei,$card_num);
    print_r($card_num);*/


    public function test3()
    {
        $str = '立即配送（预计09:30送达）';
        $str = trim($str);
        $temp = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '0', ':');
        $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            if (in_array($str[$i], $temp)) {
                $result .= $str[$i];
            }
        }
        $str = substr($result, -5);
        print_r($str);
    }

    public function close()
    {
        $box_code = 123456;
        Db::table('gada_box')
            ->where('box_code', $box_code)
            ->update(['is_close' => 1]);
        $a = '已盖好盖';
        print_r($a);
    }

    public function dispatch()
    {
        //快递提上箱子开始配送
        $box_code = 123456;
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        $type0_status2_time = date("H:i");
        Db::table('gada_order')
            ->where(['order_box' => $bid, 'order_type' => 0, 'order_status' => 1])
            ->update(['order_status' => 2, 'type0_status2_time' => $type0_status2_time]);
    }

    public function delivered()
    {
        //送到
        $box_code = 123456;
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        $type0_status3_time = date("Y-m-d H:i:s");
        Db::table('gada_order')
            ->where(['order_box' => $bid, 'order_type' => 0, 'order_status' => 2])
            ->update(['order_type' => 0, 'order_status' => 3, 'first_arrive_time' => $type0_status3_time]);
    }
    public static function noticeMsg($type,$msg,$phone)
    {
        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'u'.$phone;

        $title = '来自嘎哒箱的友情提示';
        $desc = $msg;
        $payload = '{"type":'. $type .'}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(0);
        $message->payload($payload);
        //$message->extra(Builder::notifyEffect, 0);
        $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        //$message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        // $sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    }
    public function testtest()
    {

        $php_Path = '47.94.157.157';
        $fp = fsockopen($php_Path,80);
        if (!$fp) {
            //LMLog::error("fsockopen:err" );
        } else {
            $out = "GET /index/Test0/test?key=1&u=1   HTTP/1.1\r\n";
            $out .= "Host: ".$php_Path."\r\n";
            $out .= "Connection: Close\r\n\r\n";
            stream_set_blocking($fp,true);
            stream_set_timeout($fp,1);
            fwrite($fp, $out);
            usleep(1000);
            fclose($fp);
        }
        echo 666;



        /*$a = 'alipay_sdk=alipay-sdk-php-20161101&
        app_id=2017110309696034&
        biz_content=%7B%22body%22%3A%22%5Cu5145%5Cu503c%22%2C%22subject%22%3A%22XXXXX%22%2C%22out_trade_no%22%3A987654123%2C%22total_amount%22%3A1.5%2C%22product_code%22%3A%22QUICK_MSECURITY_PAY%22%7D&
        charset=UTF-8&
        format=json&
        method=alipay.trade.app.pay&
        notify_url=http%3A%2F%2F47.94.157.157%2Findex%2Frecharge%2Ftest&
        sign_type=RSA2&
        timestamp=2017-11-10+12%3A26%3A34&
        version=1.0&
        sign=ScZi%2FmbOU92ty5RrwpixXOV9LxtIayIsp79T5%2Fm5FaCPtIaFQeyYbyRjK437oQKTyUIlS6aY35UvQVIXkjUZMcU9%2FSePLdhNWKtlL4UKNdPw1f8ywIR4NmPDwkZu7NXVOaoS18NNcxIoDwRicIqDKucjO4hiBpmhPqEG4C2oa4yb%2Fiz1iQhTsqRWo2ZB8G%2FMvwEvA4J1is%2Bsmc4TmQq4Ry0C6M2kXuPbl3i%2BYWJIrChnC6IYebLZ3rTABkt9MoXXsFn0uHtSklIuTzg0CQCsod8xVdit%2FO1HSUpk%2BkJnXe2U5OE4lLVfZslkuxELQ3Px9GYTjPrOki5nwJrawqQxcg%3D%3D';

       $b = 'gmt_create=2017-11-10+13%3A53%3A08
       &charset=UTF-8
       &seller_email=gadabox%40163.com
       &subject=XXXXX
       &sign=C6EMnkd2drNwhZ1Bebqsbi22mERMwSWitTPBHKnGCC60%2FMdmAvSczpSSS4BwEV4Zay7Pvp3UHcG6rmhJsjm%2BwDrq5%2FYGfAt8yL%2B9qqFtFguw2MM2Jj3P2xVgBxUB7C9wukpCvb0QNeUPEHt451u4jwGB%2BmmCiybu17TG42ZpchbjKSmiZ1s91Ycb%2FSZoWNOFHHdcD2ViGNG7nc5L1abfiVtvj3n7ui2iZbupbT2aXur7mXGVlTZuEyJUylDxqH2V2W5ePPcT4ekJ8SrE%2F5WoJfjozxdRybrIc0qzOcWd2ov1NXn5gfZS%2FELMSLM%2BnnelYiMzrWNfm71dgf0PYzDqsw%3D%3D
       &body=%E5%85%85%E5%80%BC
       &buyer_id=2088822704123175
       &invoice_amount=0.10
       &notify_id=a5a826fc29ad14c1a48df9ba52f4b7dhbd
       &fund_bill_list=%5B%7B%22amount%22%3A%220.10%22%2C%22fundChannel%22%3A%22ALIPAYACCOUNT%22%7D%5D
       &notify_type=trade_status_sync
       &trade_status=TRADE_SUCCESS
       &receipt_amount=0.10
       &app_id=2017110309696034
       &buyer_pay_amount=0.10
       &sign_type=RSA2
       &seller_id=2088721932902599
       &gmt_payment=2017-11-10+13%3A53%3A09
       &notify_time=2017-11-10+13%3A53%3A09
       &version=1.0
       &out_trade_no=hlw292977699
       &total_amount=0.10
       &trade_no=2017111021001004170543546664
       &auth_app_id=2017110309696034
       &buyer_logon_id=136****7101
       &point_amount=0.00';
        $c = 'gmt_create=2017-11-10+13%3A53%3A08&charset=UTF-8&seller_email=gadabox%40163.com&subject=XXXXX&sign=C6EMnkd2drNwhZ1Bebqsbi22mERMwSWitTPBHKnGCC60%2FMdmAvSczpSSS4BwEV4Zay7Pvp3UHcG6rmhJsjm%2BwDrq5%2FYGfAt8yL%2B9qqFtFguw2MM2Jj3P2xVgBxUB7C9wukpCvb0QNeUPEHt451u4jwGB%2BmmCiybu17TG42ZpchbjKSmiZ1s91Ycb%2FSZoWNOFHHdcD2ViGNG7nc5L1abfiVtvj3n7ui2iZbupbT2aXur7mXGVlTZuEyJUylDxqH2V2W5ePPcT4ekJ8SrE%2F5WoJfjozxdRybrIc0qzOcWd2ov1NXn5gfZS%2FELMSLM%2BnnelYiMzrWNfm71dgf0PYzDqsw%3D%3D&body=%E5%85%85%E5%80%BC&buyer_id=2088822704123175&invoice_amount=0.10&notify_id=a5a826fc29ad14c1a48df9ba52f4b7dhbd&fund_bill_list=%5B%7B%22amount%22%3A%220.10%22%2C%22fundChannel%22%3A%22ALIPAYACCOUNT%22%7D%5D&notify_type=trade_status_sync&trade_status=TRADE_SUCCESS&receipt_amount=0.10&app_id=2017110309696034&buyer_pay_amount=0.10&sign_type=RSA2&seller_id=2088721932902599&gmt_payment=2017-11-10+13%3A53%3A09&notify_time=2017-11-10+13%3A53%3A09&version=1.0&out_trade_no=hlw292977699&total_amount=0.10&trade_no=2017111021001004170543546664&auth_app_id=2017110309696034&buyer_logon_id=136****7101&point_amount=0.00';
        $d = explode('&', $c);
        $z = [];
       /* $e = implode('',$d);
        $f = explode('=',$e);*/

        /*foreach($d as $v)
        {
            $m = explode('=',$v);
            $z[$m[0]] = $m[1];
        }*/
        //$f = array_map("test",$d);

       /* foreach($d as $v)
        {
            $n = strpos($v,'=');

            $m = explode('=',$v);
        }
        $e = implode('',$d);
        $f = explode('=',$e);
        $n = count($f);
        $n = $n -1;
        //$n = $n/2;
        for($i=0;$i<$n;$i++)
        {
            $x[$f[$i]] = $f[$i+1];
            $i++;
        }*/

        /*foreach($f as $v)
        {
            $f[$v[0]] = $v[1];
        }*/
        //print_r($z);

        /* $filename = 'testfile.txt';
        if (is_writable($filename)) {
            echo file_put_contents($filename, "This is another something.", FILE_APPEND);
        } else {
            echo "文件 $filename 不可写";
        }*/
        /*$myfile = fopen("./testfile.txt", "w");
        fwrite($myfile, $a);
       /* $txt = "Steve Jobs\n";
        fwrite($myfile, $txt);
        fclose($myfile);*/
    }

    public static function a()
    {

        $secret = '/JSYm04FIlOV97gBklQcEw==';
        $package = 'com.gadaboxapp.www';


// 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($package);
        Constants::setSecret($secret);

        //$aliasList = array('alia1', 'alias2');

        $phone = 'c15935105767';

        $title = '你好,maomin';
        $desc = '这是一条来自神秘服务器的推送消息';
        $payload = '{"type":3}';


        $message = new Builder();
        $message->title($title);
        $message->description($desc);
        $message->notifyType(1);
        $message->passThrough(1);
        $message->payload($payload);
        $message->extra(Builder::notifyEffect, 1);
        // $message->extra(Builder::notifyForeground, 1);
        //$message->extra(Builder::Constants.EXTRA_PARAM_SOUND_URI, 1);
        //android.resource://com.gadaboxapp.www/raw/order_reminder
        $message->extra("sound_uri", "android.resource://com.gadaboxapp.www/raw/order_reminder");
        $message->notifyId(1);
        $message->timeToSend(0);
        $message->build();

        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);

        $sender = new Sender();
        //$sender->broadcastAll($message);
        $res = $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
       /* $cname = '啦啦啦';
        $cphone = 150641381166;
        $courier_identification = 123;
        $identification_id = 7;
        $id = Db::table('gada_courier')
            ->insert(['cname'=>$cname,'cphone'=>$cphone,'courier_identification'=>$courier_identification,'identification_id'=>$identification_id]);
        print_r($id);*/

            } /*else {
                //助力卡价值刚好够,占用金额另外扣除
                $refund = 0 - $price;
                //记录交易明细
                $current_balance = $balance['amount'] + $balance['reserves'] + $refund;
                //保留两位小数方便观看
                $current_balance = number_format($current_balance, 2);
                Db::table('gada_transact')
                    //->where('user_id',$uid)
                    ->insert(['user_id' => $uid, 'transact_content' => '取消配送扣除金额', 'transact_detail' => $refund, 'current_balance' => $current_balance]);
                Db::table('gada_order')
                    ->where('order_num', $order_num)
                    ->update(['refund', $refund]);
            }

            //付费情况（两个账户，那个有钱扣哪个）
            if ($balance['reserves'] == 0) {
                //冻结账户为空，即直接扣除可动账户
                $amount = $balance['amount'] + $refund;
                Db::table('gada_balance')
                    ->where('uid', $order_msg['uid'])
                    ->update(['amount' => $amount]);

            } elseif ($balance['reserves'] != 0) {
                //冻结账户有余额，直接扣除即可（存在逻辑漏洞）
                $reserves = $balance['reserves'] + $refund;
                if ($reserves >= 0) {
                    //必须保证在不确定余额账户是否够支付之前，不能出现冻结账户中余额扣为负数
                    Db::table('gada_balance')
                        ->where('uid', $order_msg['uid'])
                        ->update(['reserves' => $reserves]);
                } else //冻结账户余额不够扣除占用费用，就用可动账户扣除，无论是否够用
                {
                    $amount = $balance['amount'] + $refund;
                    Db::table('gada_balance')
                        ->where('uid', $order_msg['uid'])
                        ->update(['amount' => $amount]);
                }
            }

            //取消成功，移入特殊订单 ，记录取消时间
            $current = date('Y-m-d H:i:s');
            Db::table('gada_order')
                ->where('order_num', $order_num)
                ->update(['order_type' => SPECIAL, 'order_status' => CANCEL_DISPATCH, 'order_over_time' => $current]);
            //配合前端返回时间
            $order_create_time = $order_create_time * 1000;
            $current_time = $current_time * 1000;
            //若返回金额为负，直接不展示返还金
            if ($refund < 0) {
                $refund = 0;
            }
            //打开箱子
            $bid = $order_msg['order_box'];
            Db::table('gada_box')
                ->where('bid', $bid)
                ->update(['is_close' => 0]);
            $data = ['order_create_time' => $order_create_time, 'current_time' => $current_time, 'price' => $price, 'return_card' => $card, 'refund' => $refund];
            print_r($data);*/
            //保留两位小数方便观看
            //$current_balance = number_format($current_balance,2);

}
       /* $uid = 5;
        $box_code = Db::table('gada_box')
            ->where('is_user',$uid)
            ->value('box_code');
        if(empty($box_code))
        {
            //判定是否有被快递强关订单
            $order_num = Db::table('gada_order')
                ->join('gada_box','gada_order . order_box = gada_box . bid')
                ->where(['uid'=>$uid,'order_type'=>2,'order_status'=>5])
                ->value('order_num');
            if(empty($order_num))
            {
                //无异常订单正常返回 $force_close = 0
                echo 0;

            }
            else
            {
                //存在快递强关而未读的订单 $force_close = 1
                echo 1;
            }
        }
        else
        {
            //存在未完成的订单 $force_close = 0
            echo 2;
        }*/

        /*$order_num = 150519365777123456;
        $msg = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->field('order_create_time','current_time')
            ->find();*/


        /*$bid  = 1;
        $box_code = Db::table('gada_order')
            //->join('gada_order','gada_box . bid = gada_order . order_box')
            ->where(['order_box' => $bid,'order_type' => 2,'order_status' => 0])
            ->update(['order_status' => 4]);
        print_r($box_code);*/

        //print_r($card);
        /* if ($this->weixin())
        {
            echo  "微信访问本网站";
        } else {
            echo "非微信访问本网址。";
        }
    }
    public function weixin(){
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false )
        {
            return true;
        }
        return false;*/




        $imei = '866260033645485';
       // $token = 'oTwecfqEQbjAv2pwF+EiBQShqLxw0WQFFl/pXyfa6A3Zum31ugJ3nLYGGPcPC7+p';
        //$token = base64_decode($token);
        //$token = 'a2891f08fae0863edbbb0d12065311cc';
        //$name = 'maomin';
        /*$token = '1234500000000000000000000000000000000000000000000000000000000000000000000000-----BEGIN PRIVATE KEY-----
MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAJkn+t5OZMgSRBS9
YVJdq5EP0uCQmopAhjATBBwyhi6BYEqlL/uVc0Kecx82A/uBbBI2T7qgsLVTLH9Z
dBhV3oVgGaIwvbudaUlDQCCxsH2WV/L8UTXTYjZa7YEh+m+zfms+B+Jr5RLT/PvY
TgkDJO0ZkEXJA0SO3SVMsPXRKCSXAgMBAAECgYA1ex2CJXPR3XcCmwLyBR2VASaN
HlGot1FkVi+YPRhYAvuB9V7lBlICUFw46N7JI29+iJKcw+IQ32NpcO42VT3nYMn/
W6d9RV3RLAnc43xyfw4lejVuKumBRHTVYjW2hOwNnAlESpv2hBBejqaGUUjxYRQD
ly054e/OraFg6xK8YQJBAMtTK3V2nKSmmIgvW7g5WwBouvX1Ew1FhA+O1x92+cR+
hBV6vKqYn4mQoIvYCiZVEEBw2uqd9IcSwOVsz+WOp+cCQQDA1YqXXC+NMQEYXt+a
9Y1ryciGvzK16vt0JJjzO1s8Xot8MXXZ0PlNlx17ix2jbEkQl22yXkaWWmYK+mEL
4EfRAkB+4wl1Ba+d5UW9f2iK4GhVKga7JdVc6+wNVYQU48fdg2LkkLMa96JgVDyM
6Sb0YxOAU62ayzZl8SMmSjC3vr4zAkBFiv1/VrSjc7/UXSrBBLtq2wuhZMTSDJuA
qE4ssgRWQjaFpIS+9/lgvRXZ3zLiJAQ5opLiF9PXF2TjoqZrFQhxAkB3BeY7UDFm
sKvkTdLcnXlq+Ja00vjw++kx4y2ofZwr51Dccy0Zu5HzOFWs2fVe+no0DCYav76f
diN7/H/GtXQN
-----END PRIVATE KEY----------BEGIN PRIVATE KEY-----
MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAJkn+t5OZMgSRBS9
YVJdq5EP0uCQmopAhjATBBwyhi6BYEqlL/uVc0Kecx82A/uBbBI2T7qgsLVTLH9Z
dBhV3oVgGaIwvbudaUlDQCCxsH2WV/L8UTXTYjZa7YEh+m+zfms+B+Jr5RLT/PvY
TgkDJO0ZkEXJA0SO3SVMsPXRKCSXAgMBAAECgYA1ex2CJXPR3XcCmwLyBR2VASaN
HlGot1FkVi+YPRhYAvuB9V7lBlICUFw46N7JI29+iJKcw+IQ32NpcO42VT3nYMn/
W6d9RV3RLAnc43xyfw4lejVuKumBRHTVYjW2hOwNnAlESpv2hBBejqaGUUjxYRQD
ly054e/OraFg6xK8YQJBAMtTK3V2nKSmmIgvW7g5WwBouvX1Ew1FhA+O1x92+cR+
hBV6vKqYn4mQoIvYCiZVEEBw2uqd9IcSwOVsz+WOp+cCQQDA1YqXXC+NMQEYXt+a
9Y1ryciGvzK16vt0JJjzO1s8Xot8MXXZ0PlNlx17ix2jbEkQl22yXkaWWmYK+mEL
4EfRAkB+4wl1Ba+d5UW9f2iK4GhVKga7JdVc6+wNVYQU48fdg2LkkLMa96JgVDyM
6Sb0YxOAU62ayzZl8SMmSjC3vr4zAkBFiv1/VrSjc7/UXSrBBLtq2wuhZMTSDJuA
qE4ssgRWQjaFpIS+9/lgvRXZ3zLiJAQ5opLiF9PXF2TjoqZrFQhxAkB3BeY7UDFm
sKvkTdLcnXlq+Ja00vjw++kx4y2ofZwr51Dccy0Zu5HzOFWs2fVe+no0DCYav76f
diN7/H/GtXQN
-----END PRIVATE KEY-----0';
        $encode ='';
        $aesKey = Db::table('gada_aes')
            ->where('imei', "$imei")
           // ->field('aes_key','iv')
            ->find();
       //print_r($aesKey['aes_key']);
        $aes = $aesKey['aes_key'];
        $iv  = $aesKey['iv'];
        $rsa = $aesKey['priv_key'];
        openssl_private_encrypt($token,$encode,$rsa);
       // print_r($iv);
      //  $token =  openssl_encrypt($token, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv);
       // $token =  openssl_decrypt($token, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
       // print_r($token);
       echo strlen($encode);*/

       // print_r($aesKey);
        //print_r($aesKey);
       /* $aes = $aesKey['aes_key'];
        $iv = $aesKey['iv'];
        $aes = '-c92I 6|SY0~B1w&';
        $iv = '.h,z|QN{V~ZXKRYp';
        $token =  openssl_encrypt($token, 'aes-256-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        $token =  openssl_decrypt($token, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        print_r($token);*/




        /*$myStr1 = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZJ/reTmTIEkQUvWFSXauRD9Lg
kJqKQIYwEwQcMoYugWBKpS/7lXNCnnMfNgP7gWwSNk+6oLC1Uyx/WXQYVd6FYBmi
ML27nWlJQ0AgsbB9llfy/FE102I2Wu2BIfpvs35rPgfia+US0/z72E4JAyTtGZBF
yQNEjt0lTLD10SgklwIDAQAB
-----END PUBLIC KEY-----
';
        $myStr = '-----BEGIN PRIVATE KEY-----
MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAJkn+t5OZMgSRBS9
YVJdq5EP0uCQmopAhjATBBwyhi6BYEqlL/uVc0Kecx82A/uBbBI2T7qgsLVTLH9Z
dBhV3oVgGaIwvbudaUlDQCCxsH2WV/L8UTXTYjZa7YEh+m+zfms+B+Jr5RLT/PvY
TgkDJO0ZkEXJA0SO3SVMsPXRKCSXAgMBAAECgYA1ex2CJXPR3XcCmwLyBR2VASaN
HlGot1FkVi+YPRhYAvuB9V7lBlICUFw46N7JI29+iJKcw+IQ32NpcO42VT3nYMn/
W6d9RV3RLAnc43xyfw4lejVuKumBRHTVYjW2hOwNnAlESpv2hBBejqaGUUjxYRQD
ly054e/OraFg6xK8YQJBAMtTK3V2nKSmmIgvW7g5WwBouvX1Ew1FhA+O1x92+cR+
hBV6vKqYn4mQoIvYCiZVEEBw2uqd9IcSwOVsz+WOp+cCQQDA1YqXXC+NMQEYXt+a
9Y1ryciGvzK16vt0JJjzO1s8Xot8MXXZ0PlNlx17ix2jbEkQl22yXkaWWmYK+mEL
4EfRAkB+4wl1Ba+d5UW9f2iK4GhVKga7JdVc6+wNVYQU48fdg2LkkLMa96JgVDyM
6Sb0YxOAU62ayzZl8SMmSjC3vr4zAkBFiv1/VrSjc7/UXSrBBLtq2wuhZMTSDJuA
qE4ssgRWQjaFpIS+9/lgvRXZ3zLiJAQ5opLiF9PXF2TjoqZrFQhxAkB3BeY7UDFm
sKvkTdLcnXlq+Ja00vjw++kx4y2ofZwr51Dccy0Zu5HzOFWs2fVe+no0DCYav76f
diN7/H/GtXQN
-----END PRIVATE KEY-----
';
        $encode = '';
        $decode = '';
        $data = 'catchbest凯视佳';
        openssl_public_encrypt($data,$encode,$myStr1);
        $time_start = $this -> getmicrotime();
        //openssl_public_encrypt($data,$encode,$myStr);
        openssl_private_decrypt($encode,$decode,$myStr);
        $time_end = $this-> getmicrotime();
        $time = $time_end - $time_start;
        print_r($decode);
        echo "执行时间 $time seconds";
    }
    function getmicrotime()
    {
        list($usec, $sec) = explode(" ",microtime());
        return ((float)$usec + (float)$sec);
    }*/

