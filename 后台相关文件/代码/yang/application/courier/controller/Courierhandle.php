<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/30
 * Time: 16:15
 */
namespace app\courier\controller;
use app\index\controller\Response;
require ('Constants.php');
header ( "Content-Type: application/json; charset=utf-8" );
use app\index\controller\Test;
use \think\Controller;
use \think\Db;
include_once(dirname(__FILE__) . './../../index/controller/autoload.php');

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

class Courierhandle
{
    public function acceptOrder()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $ctoken = $data['ctoken'];
        $type0_status1_time = date("H:i");
        $current_time = date("Y-m-d H:i:s");
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $is_updata = Db::table('gada_order')
            ->where(['order_num'=>$order_num,'courier'=>$id_courier])
            ->update(['order_status'=>PUSHED,'type0_status1_time' => $type0_status1_time,'order_current_time'=>$current_time]);
        if($is_updata == 1)
        {
            $type = 6;
            $msg = '您的嘎哒箱订单已被快递员接单';
            Test::changeMsg($msg,$type);
            Response::returnApiOk(200,'接单成功');
        }
        else
        {
            Response::returnApiError(201,'接单失败');
        }
    }
    public function takeBox()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $box_code = $data['box_code'];
        $time = date('H:m');
        $current_time = date("Y-m-d H:i:s");
        $order_box = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        $is_updata = Db::table('gada_order')
            ->where(['order_num'=>$order_num,'order_box'=>$order_box])
            ->update(['order_status'=>DISPATCH,'type0_status2_time'=>$time,'order_current_time'=>$current_time]);
        if($is_updata == 1)
        {
            $type = 6;
            $msg = '嘎哒哥已接收到您的嘎哒箱,正火速朝您赶来';
            Test::changeMsg($msg,$type);
            Response::returnApiOk(200,'提箱成功');
        }
        else
        {
            Response::returnApiError(201,'提箱失败');
        }
    }
    public function arrive()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $second_create_time =  Db::table('gada_order')
            ->where('order_num',$order_num)
            ->value('second_create_time');
        $time = date("Y-m-d H:i:s");
        if(empty($second_create_time)) {
            //首次配送的订单
            $is_updata = Db::table('gada_order')
                ->where(['order_num' => $order_num, 'courier' => $id_courier])
                ->update(['order_delivered_time' => $time, 'order_status' => 3, 'order_current_time' => $time]);
        }
        else{
            //二次配送的订单
            $is_updata = Db::table('gada_order')
                ->where(['order_num' => $order_num, 'courier' => $id_courier])
                ->update(['second_delivered_time' => $time, 'order_status' => 6, 'order_current_time' => $time]);
        }
        if($is_updata == 1)
        {
            $type = 6;
            $msg = '快递员已确认送达,您可以收货开箱了';
            Test::changeMsg($msg,$type);
            Test::userPassThrough($msg,8);
            Response::returnApiOk(200,'送达登记成功');
        }
        else
        {
            Response::returnApiError(201,'送达登记失败');
        }
    }
    public function work()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $ctoken = $data['ctoken'];
        $is_working = $data['is_working'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        //设置工作状态
        $is_set = Db::table('gada_courier')
            ->where('id_courier',$id_courier)
            ->update(['is_working'=>$is_working]);
        $current = date('Y-m-d H:i:s');
        $current_date = date('Y-m-d');
        if($is_set == 1)
        {
            if($is_working == 1)
            {
                //设置开工状态,记录设置开工的时间
                Db::table('gada_setwork')
                    ->insert(['id_courier'=>$id_courier,'current_time'=>$current,'is_working'=>$is_working]);
            }
            else
            {
                //设置停工状态，记录设置停工的时间
                Db::table('gada_setwork')
                    ->insert(['id_courier'=>$id_courier,'current_time'=>$current,'is_working'=>$is_working]);
                //计算出此次停工时的工作时长
                $start_working_time = Db::table('gada_setwork')
                    ->where(['id_courier'=>$id_courier,'is_working'=>1])
                    ->order('current_time desc')
                    ->value('current_time');
                $start_working_time = strtotime($start_working_time);
                $current = strtotime($current);
                $worktime = $current - $start_working_time;
                $hour = intval(($worktime%(3600*24))/3600);
                $minute = (intval(($worktime%(3600*24))/60))/60;
                $minute = number_format($minute,2);
                //此次工作的小时数
                $time = $hour + $minute;
                //记录当前的工作时长
                $workingtime = Db::table('gada_worktime')
                    ->where(['workdate'=>$current_date,'id_courier'=>$id_courier])
                    ->value('worktime');
                if(empty($workingtime))
                {
                    //当日首次记录工作时长
                    Db::table('gada_worktime')
                        ->insert(['id_courier'=>$id_courier,'worktime'=>$time,'workdate'=>$current_date]);

                }
                else
                {
                    //当日非首次记录工作时长
                    $time = $workingtime + $time;
                    Db::table('gada_worktime')
                        ->where(['id_courier'=>$id_courier,'workdate'=>$current_date])
                        ->update(['worktime'=>$time]);
                }
            }
            Response::returnApiOk(200,'设置成功');
        }

    }
    public function confirmReturn()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];

        $box_code = $data['box_code'];
        //查询当前扫描箱子的id
        $box_id = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        //查询订单中箱子的id
        $bid = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->value('order_box');
        $is_use = Db::table('gada_box')
            ->where('bid',$bid)
            ->field('is_close,is_lock')
            ->find();
        if($is_use['is_close'] == 0||$is_use['is_lock']==0)
        {
            Response::returnApiError(201,'请先确认盖上箱盖,并完成扫码锁箱');
        }
        elseif($bid != $box_id)
        {
            //扫描和归还不是一个箱子
            Response::returnApiError(201,'扫描和归还不是同一个箱子,换个箱子扫扫看');
        }
        else
        {
            //判定该订单是无人签收还箱还是正常签收还箱
            $order_status = Db::table('gada_order')
                ->where('order_num',$order_num)
                ->value('order_status');
            $over_time = date("Y-m-d H:i:s");
            if($order_status == RECEIVED || $order_status == SECOND_RECEIVED || $order_status == CHANGE) {
                //该订单是正常已签收订单

                //以后接入坐标的判定比对

                //模拟通过比对,解除箱子的绑定
                Db::table('gada_box')
                    ->where('bid', $bid)
                    ->update(['is_courier' => '', 'order_number' => '']);
                //对快递员完成该订单的信息进行记录

                $order_msg = Db::table('gada_order')
                    ->where('order_num', $order_num)
                    ->field('order_price,courier')
                    ->find();
                $order_msg['order_price'] = $order_msg['order_price'] - 2;
                Db::table('gada_corder')
                    ->insert(['order_number' => $order_num, 'income' => $order_msg['order_price'], 'over_time' => $over_time, 'id_courier' => $order_msg['courier']]);

            }
            elseif($order_status ==NO_RECEIVE)
            {
                //该订单为未签收订单
                $msg = '您的嘎哒箱由于无人签收已被送回';
                Test::changeMsg($msg,11);
                //Test::userPassThrough($msg,8);
            }

            Db::table('gada_order')
                ->where('order_num', $order_num)
                ->update(['goback_return_time'=>$over_time]);

            Response::returnApiOk(200,'还箱成功');
        }
    }
    public function getAllOrders()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $paid = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>PAID])
            ->field('order_num')
            ->select();
        $pushed = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>PUSHED])
            ->field('order_num')
            ->select();
        $dispatch = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>DISPATCH])
            ->field('order_num')
            ->select();
        $delivered = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>DELIVERED])
            ->field('order_num')
            ->select();
        $no_receive = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
            ->field('order_num')
            ->select();
        $return = Db::table('gada_box')
            ->where('is_courier',$id_courier)
            ->field('order_number')
            ->select();
        //整合该快递员持有的订单数组
        $all_order = array_merge($paid,$pushed,$dispatch,$no_receive,$return,$delivered);
        $data = ['all_orders'=>$all_order];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function reportException()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $order_number = $data['order_number'];
        $change_address = Db::table('gada_order')
            ->where('order_num',$order_number)
            ->value('change_address');
        $exception_type = $data['exception_type'];
        $content = $data['content'];
        $create_time = date('Y-m-d H:i:s');
        $status = Db::table('gada_order')
            ->where('order_num',$order_number)
            ->value('order_status');
        if($exception_type == 'change_address')
        {
            //更改地址的异常订单

            if($status == PUSHED ||$status ==DISPATCH)
            {
                if($change_address != 1) {
                    //仅限该订单状态下可上报更改地址异常
                    $is_update = Db::table('gada_order')
                        ->where('order_num', $order_number)
                        ->update(['change_address' => 1, 'order_current_time' => $create_time]);
                    if ($is_update == 1) {
                        $msg = '您有待更改配送地址的订单';
                        $type = 5;
                        Test::changeMsg($msg, $type);
                        Db::table('gada_exception')
                            ->insert(['exception_type' => $exception_type, 'order_number' => $order_number, 'create_time' => $create_time, 'content' => $content, 'courier_id' => $id_courier]);
                        //建立异步通道
                        $php_Path = '47.94.157.157';
                        $fp = fsockopen($php_Path, 80);
                        if (!$fp) {
                            //LMLog::error("fsockopen:err" );
                        } else {
                            $out = "GET /courier/Courierhandle/timedelay?order_number={$order_number}   HTTP/1.1\r\n";
                            $out .= "Host: " . $php_Path . "\r\n";
                            $out .= "Connection: Close\r\n\r\n";
                            stream_set_blocking($fp, true);
                            stream_set_timeout($fp, 1);
                            fwrite($fp, $out);
                            usleep(1000);
                            fclose($fp);
                        }
                        //$this->timeDelay();
                        Response::returnApiOk(200, '上报成功,等待用户更改地址');

                    } else {
                        Response::returnApiError(201, '上报失败');
                    }
                }
                else
                {
                    Response::returnApiError(201, '请耐心等待用户修改完配送地址');
                }
            }
          else
          {
              /*Db::table('gada_exception')
                  ->insert(['exception_type'=>$exception_type,'order_number'=>$order_number,'create_time'=>$create_time,'content'=>$content,'courier_id'=>$id_courier]);*/
              Response::returnApiError(201,'当前订单状态已不可再更改地址');
          }
        }
        else {
            //无人接收异常
            if ($status != DISPATCH) {
                Response::returnApiError(201, '当前订单状态不可上报无人签收');
            }
            else
            {
                $is_update = Db::table('gada_order')
                    ->where('order_num', $order_number)
                    ->update(['order_type' => SPECIAL, 'order_status' => NO_RECEIVE, 'order_current_time' => $create_time, 'goback_time' => $create_time]);
                if ($is_update == 1)
                {
                    $msg = '您的订单由于无人签收,我们已将箱子遣回';
                    $type = 9;
                    Test::changeMsg($msg, $type);
                    Db::table('gada_exception')
                        ->insert(['exception_type' => $exception_type, 'order_number' => $order_number, 'create_time' => $create_time, 'content' => $content, 'courier_id' => $id_courier]);
                    Response::returnApiOk(200, '上报成功,可开始折返还箱');
                }
                else
                {
                    Response::returnApiError(201, '上报失败');
                }
            }
        }
    }
    public function timeDelay()
    {
        //修改地址的10分钟延时方法
        $order_number = $_GET['order_number'];
        //$uid = $_GET['uid'];
        sleep(600);
        $order_msg = Db::table('gada_change_address_log')
            ->where('order_number',$order_number)
            ->find();
        if(!empty($order_msg))
        {
            //用户已经修改完地址
        }
        else
        {
            //10分钟内用户未进行任何操作，对其修改地址申请进行锁定(change_address = 0 :未修改地址)
            Db::table('gada_order')
                ->where('order_num',$order_number)
                ->update(['change_address'=>0]);
            $uid = Db::table('gada_order')
                ->where('order_num',$order_number)
                ->value('uid');
            Db::table('gada_change_address_log')
                ->insert(['order_number'=>$order_number,'change_address'=>0,'uid'=>$uid]);
            Test::userPassThrough('十分钟内您未对配送地址进行修改',13);
            Test::courierNotice(14,'用户未更改地址，请按原地址配送',$order_number);
        }
    }
    public function forceClose()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token', $ctoken)
            ->value('id_courier');
        $box_code = $data['box_code'];
        $is_use = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->field('is_user,is_courier,is_close,bid')
            ->find();
        if ($id_courier == $is_use['is_courier'])
        {
            //归还锁箱
            if($is_use['is_close']==0)
            {
                Response::returnApiError(201,'请先盖箱');
            }
            else
            {
                Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_lock' => 1]);
                Response::returnApiOk(200, '锁箱成功，赶快归还箱子吧！');
            }
        }
        else
        {
            //强关锁箱操作
            if (empty($is_use['is_user'])) {
                if (empty($is_use['is_courier'])) {
                    Response::returnApiError(201, '该嘎哒箱已锁');
                } else {
                    Response::returnApiError(201, '该嘎哒箱正由快递员放回中');
                }
            } else {
                //用户占用中
                if ($is_use['is_close'] == 0) {
                    Response::returnApiError(201, '请先盖上嘎哒箱的盖子');
                } else {

                    $order_num = Db::table('gada_order')
                        ->where(['order_type' => 2, 'order_status' => 0, 'order_box' => $is_use['bid']])
                        ->value('order_num');

                    //进行强制扣费处理
                    $order_create_time = Db::table('gada_order')
                        ->where('order_num', $order_num)
                        ->value('order_create_time');
                    $hourly_rate = 1;
                    $order_create_time = strtotime($order_create_time);
                    $current_time = time();
                    $over_time = date('Y:m:d H:i:s', $current_time);
                    //十分钟之内不允许强制关箱，十分钟之后开始计时
                    $time = $current_time - $order_create_time;
                    if ($time <= 600) {
                        //不允许强制关箱
                        Response::returnApiError(201, '用户还在使用保护时间内,强制关箱失败');
                    } else {
                        //超过十分钟，收取费用

                        $hourly_rate = 1;
                        $price = $hourly_rate * ceil(($time - 600) / 3600);
                        //占用时每小时的计费标准（1元）走账
                        $balance = Db::table('gada_balance')
                            ->where('uid', $is_use['is_user'])
                            ->find();
                        if ($balance['reserves'] > $price || $balance['reserves'] = $price) {
                            //优先扣除冻结账户
                            $balance['reserves'] = $balance['reserves'] - $price;
                            //用于记录是否成功计费
                            $is_pay = Db::table('gada_balance')
                                ->where('uid', $is_use['is_user'])
                                ->update($balance);
                        } else {
                            //冻结账户金额不足，直接扣除余额（无论是否够用）
                            $balance['amount'] = $balance['amount'] - $price;
                            $is_pay = Db::table('gada_balance')
                                ->where('uid', $is_use['is_user'])
                                ->update($balance);
                        }
                        //记录交易明细
                        $current_balance = $balance['amount'] + $balance['reserves'];
                        //保留两位小数方便观看
                        $current_balance = number_format($current_balance, 2);
                        //price为实际要支付的价格
                        Db::table('gada_transact')
                            ->insert(['user_id' => $is_use['is_user'], 'transact_content' => '截止快递关闭箱子时产生的占用费用', 'transact_detail' => '-' . $price, 'current_balance' => $current_balance, 'force_close_courier' => $id_courier]);
    //更新订单状态
                        if ($is_pay != 1) {
                            //走账失败
                            print_r('扣费失败');
                        } else {
                            Db::table('gada_box')
                                ->where('box_code', $box_code)
                                ->update(['is_user' => '', 'is_lock' => 1]);
                            Db::table('gada_order')
                                ->where(['order_num' => $order_num])
                                ->update(['order_status' => 5, 'order_price' => $price, 'order_over_time' => $over_time]);
                            //扣分记录
                            Db::table('gada_score')
                                ->insert(['score_content' => '被监管员关闭嘎哒箱', 'user_id' => $is_use['is_user'], 'score_detail' => '-5']);
                            $score = Db::table('gada_user')
                                ->where('user_id', $is_use['is_user'])
                                ->value('score');
                            $score = $score - 5;
                            Db::table('gada_user')
                                ->where('user_id', $is_use['is_user'])
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

                            $phone = 'u18210968916';

                            $title = '你好,maomin';
                            $desc = '这是一条来自神秘服务器的推送消息';
                            $payload = '{"type":2,"order_create_time":' . $order_create_time . ',"current_time":' . $current_time . ',"hourly_rate":' . $hourly_rate . '}';

    //透传消息
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
                            $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
    //通知消息
                            $message = new Builder();
                            $message->title($title);
                            $message->description($desc);
                            $message->notifyType(1);
                            $message->passThrough(0);
                            $message->payload($payload);
                            // $message->extra(Builder::notifyEffect, 1);
                            $message->extra(Builder::notifyForeground, 1);
                            $message->notifyId(1);
                            $message->timeToSend(0);
                            $message->build();

                            $targetMessage = new TargetedMessage();
                            $targetMessage->setTarget($phone, TargetedMessage::TARGET_TYPE_ALIAS); // 设置发送目标。可通过regID,alias和topic三种方式发送
                            $targetMessage->setMessage($message);

                            $sender = new Sender();
                            $sender->sendToAlias($message, $phone); //给指定的别名发送透传消息
                            Response::returnApiOk(200, '强制关箱成功,谢谢您的配合');
                        }
                    }
                }
            }
        }
    }
}