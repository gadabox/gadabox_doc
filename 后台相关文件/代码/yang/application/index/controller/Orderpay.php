<?php
/**
 * 订单支付类.
 * User: yang
 * Date: 2017/9/14
 * Time: 9:54
 */
namespace app\index\controller;
require_once('Response.php');
require_once ('Constants.php');
use \think\Db;
use \think\Controller;

Class Orderpay extends controller
{
    public static function depositPay()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $box_code = $data['box_code'];
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token', "$token")
            ->value('user_id');
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        //计算金额
        if (empty($data['order_number']))
        {
            //正常占用支付结账
            $order_create_time = Db::table('gada_order')
                ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                ->value('order_create_time');
        }
        else
        {
            $order_number = $data['order_number'];
            $order_msg = Db::table('gada_order')
                ->where(['order_num' => $order_number])
                ->find();
            if ($order_msg['order_type'] == UNFINISHED && $order_msg['order_status'] == OCCUPY)
            {
                //正常占用支付结账
                $order_create_time = Db::table('gada_order')
                    ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                    ->value('order_create_time');
            }
            else
            {
                //过夜自取订单
                //过夜后订单按当日开始工作时间开始计费
                $order_create_time = date("Y-m-d") . '8:00:00';
            }
        }

        $order_create_time = strtotime($order_create_time);
        $current_time = time();
        $order_over_time = date("Y-m-d H:i:s", $current_time);
        $hourly_rate = 1;
        //订单实际价格
        $price = $hourly_rate * ceil(($current_time - $order_create_time) / 3600);
        $card_id = $data['card_id'];
        $n = count($card_id);
        //助力卡总价值
        $sum = 0;
        //记录card_id的字符型 ,提取出数组中的助力卡id
        $card_str = implode(',', $card_id);
        if ($n !== 0)
        {
            for ($i = 0; $i < $n; $i++)
            {
                $card[$i] = Db::table('gada_card')
                    ->where('card_id', $card_id[$i])
                    ->value('card_value');
                $sum += $card[$i];

                //$card_str = $card_str .$card_id[$i] . ',';
            }
            if ($price > $sum)
            {
                //订单实际价格大于助力卡价值
                //余额中要扣除的金额
                $pay = $price - $sum;
                $money = Db::table('gada_balance')
                    ->where('uid', "$uid")
                    ->find();
                $balance = $money['reserves'] + $money['amount'];
                if ($balance < $pay)
                {
                    Response::returnApiError('303', '余额不足');
                }
                else
                {
                    $money = Orderpay::balanceComplementedPay($money,$pay);
                    for ($i = 0; $i < $n; $i++)
                    {
                        Db::table('gada_card')
                            ->where('card_id', $card_id[$i])
                            ->update(['is_use' => 1]);
                    }
                    Db::table('gada_balance')
                        ->where('uid', $uid)
                        ->update($money);
                    //记录交易明细记录
                    //$order_price为实际仍要支付的价格
                    $order_price = $price - $sum;
                    Orderpay::transactRecords($money,$order_price,$uid,'购买占用服务','-');
                    //完成占用支付，进行开锁操作 （暂定盖子一并打开）
                    Db::table('gada_box')
                        ->where('box_code', $box_code)
                        ->update(['is_close'=>0,'is_lock'=>0]);
                    //更改订单状态
                    if (empty($data['order_number'])) {
                        //正常占用订单
                        Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                            ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                    }
                    else
                    {
                        //在列表相中点击正常占用取物（此时知道order_num）
                        if ($order_msg['order_type'] == UNFINISHED && $order_msg['order_status'] == OCCUPY)
                        {
                            Db::table('gada_order')
                                ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                        //过夜订单
                        elseif ($order_msg['order_status'] == TIMEOUT)
                        {
                            //占用超时订单
                            Db::table('gada_order')
                                ->where(['order_num' => $order_number])
                                ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                        elseif ($order_msg['order_status'] == NO_RECEIVE_TIMEOUT)
                        {
                            //无人签收超时订单
                            $price = $price + $order_msg['order_price'];
                            Db::table('gada_order')
                                ->where(['order_num' => $order_number])
                                ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                    }
                    //生成一个特殊临时订单，以防用户没锁箱
                    //生成订单编号（时间+5位随机数+box_code）
                    $order_num = Ordermsg::createorder();
                    //生成特殊临时订单
                    Db::table('gada_order')
                        ->insert(['uid' => $uid, 'order_num' => $order_num, 'order_box' => $bid]);
                    //增加积分
                    Orderpay::scoreRecords($uid,'完成占用订单','+1');
                    Response::returnApiOk(200, '支付成功');
                }
            }
            else
            {
                //助力卡价值刚好够支付
                for ($i = 0; $i < $n; $i++) {
                    Db::table('gada_card')
                        ->where('card_id', $card_id[$i])
                        ->update(['is_use' => 1]);
                    //$card_str = $card_str .$card_id[$i] . ',';
                }
                //更改订单状态
                if (empty($data['order_number'])) {
                    //正常占用订单
                    Db::table('gada_order')
                        ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                        ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                }
                else
                {
                    //在列表相中点击正常占用取物（此时知道order_num）
                    if ($order_msg['order_type'] == UNFINISHED && $order_msg['order_status'] == OCCUPY)
                    {
                        Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                            ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                    }
                    //过夜订单
                    if ($order_msg['order_status'] == TIMEOUT)
                    {
                        //占用超时订单
                        Db::table('gada_order')
                            ->where(['order_num' => $order_number])
                            ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                    }
                    elseif ($order_msg['order_status'] == NO_RECEIVE_TIMEOUT)
                    {
                        //无人签收超时订单
                        $price = $price + $order_msg['order_price'];
                        Db::table('gada_order')
                            ->where(['order_num' => $order_number])
                            ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                    }
                }
                //完成占用支付，进行开锁操作 （暂定盖子一并打开）
                Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_close'=>0,'is_lock'=>0]);
                //生成一个特殊临时订单，以防用户没锁箱
                //生成订单编号（时间+5位随机数+box_code）
                $order_num = Ordermsg::createorder();
                //生成特殊临时订单
                Db::table('gada_order')
                    ->insert(['uid' => $uid, 'order_num' => $order_num, 'order_box' => $bid, 'use_card' => $card_str]);
                //增加积分
                Orderpay::scoreRecords($uid,'完成占用订单','+1');
                Response::returnApiOk(200, '支付成功');
            }
        }
        else
        {
            //无助力卡，用余额支付
            $money = Db::table('gada_balance')
                ->where('uid', "$uid")
                ->find();
            $balance = $money['reserves'] + $money['amount'];
            if ($balance < $price)
            {
                Response::returnApiError('303', '余额不足');
            }
            else
            {
                //余额充足
                $money = Orderpay::balanceComplementedPay($money,$price);
                $is_pay = Db::table('gada_balance')
                    ->where('uid', $uid)
                    ->update($money);
                //记录交易明细
                Orderpay::transactRecords($money,$price,$uid,'购买占用服务','-');


                if ($is_pay == 1) {
                    //更改订单状态
                    if (empty($data['order_number'])) {
                        //正常占用订单
                        Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                            ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                    }
                    else
                    {
                        //在列表相中点击正常占用取物（此时知道order_num）
                        if ($order_msg['order_type'] == UNFINISHED && $order_msg['order_status'] == OCCUPY)
                        {
                            Db::table('gada_order')
                                ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                ->update(['order_type' => COMPLETED, 'order_status' => OCCUPYED, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                        //过夜订单
                        if ($order_msg['order_status'] == TIMEOUT)
                        {
                            //占用超时订单
                            Db::table('gada_order')
                                ->where(['order_num' => $order_number])
                                ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                        elseif ($order_msg['order_status'] == NO_RECEIVE_TIMEOUT)
                        {
                            //无人签收超时订单
                            $price = $price + $order_msg['order_price'];
                            Db::table('gada_order')
                                ->where(['order_num' => $order_number])
                                ->update(['order_type' => COMPLETED, 'order_status' => TIMEOUT_TAKESTOCK, 'use_card' => $card_str, 'order_over_time' => $order_over_time, 'order_price' => $price, 'order_current_time' => $order_over_time]);
                        }
                    }
                    //完成占用支付，进行开锁操作 （暂定盖子一并打开）
                    Db::table('gada_box')
                        ->where('box_code', $box_code)
                        ->update(['is_close'=>0,'is_lock'=>0]);
                    //生成一个特殊临时订单，以防用户没锁箱
                    //生成订单编号（时间+5位随机数+box_code）
                    $order_num = Ordermsg::createorder();
                    //生成特殊临时订单
                    Db::table('gada_order')
                        ->insert(['uid' => $uid, 'order_num' => $order_num, 'order_box' => $bid]);
                    //增加积分
                    Orderpay::scoreRecords($uid,'完成占用订单','+1');
                    Response::returnApiOk(200, '支付成功');
                }
            }
        }
    }

    public function dispatchPay()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        if (!empty($data['order_number'])) {
            $order_number = $data['order_number'];
        }
        $box_code = $data['box_code'];
        //判定是否是转配送（0：正常配送，1：转配送）
        //判定是否是二次配送（0：非二次配送，1：二次配送）
        $is_second = $data['is_second'];
        $change_deliver = $data['change_deliver'];
        //$is_close表示箱子是否盖上。0表示打开状态，1表示已盖好。
        $is_close = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('is_close');
        if (empty($data['address_id']))
        {
            $address_id = '';
        }
        else
        {
            $address_id = $data['address_id'];
        }
        $dispatch_time = $data['dispatch_time'];
        $card_id = $data['card_id'];
        $current_time = date("Y-m-d H:i:s");
        //统计是否使用助力卡
        $n = count($card_id);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token', "$token")
            ->value('user_id');
        if ($is_close == 0)
        {
            Response::returnApiError(201, '请先盖上箱盖，再进行支付');
        }
        else
        {
            if ($is_second == 0)
            {
                if ($change_deliver == 0)
                {
                    //正常配送的订单价格
                    //缓存价格写入订单价格
                    $order_cache = Db::table('gada_order')
                        ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                        ->field('cache_price,cache_distance')
                        ->find();
                    $order_price = $order_cache['cache_price'];
                    $order_distance = $order_cache['cache_distance'];
                    Db::table('gada_order')
                        ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                        ->update(['order_price' => $order_price, 'order_distance' => $order_distance]);
                }
                else
                {
                    if (empty($data['order_number']))
                    {
                        //正常占用转配送订单中查询价格
                        $order_cache = Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                            ->field('cache_price,cache_distance')
                            ->find();
                        $order_price = $order_cache['cache_price'];
                        $order_distance = $order_cache['cache_distance'];
                        Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                            ->update(['order_price' => $order_price, 'order_distance' => $order_distance]);
                    }
                    else
                    {
                        //过夜占用订单中转配送
                        $order_cache = Db::table('gada_order')
                            ->where(['order_num' => $order_number])
                            ->field('cache_price,cache_distance')
                            ->find();
                        $order_price = $order_cache['cache_price'];
                        $order_distance = $order_cache['cache_distance'];
                        //清理送达时间，防止前端置灰
                        Db::table('gada_order')
                            ->where(['order_num' => $order_number])
                            ->update(['order_delivered_time' => '', 'order_price' => $order_price, 'order_distance' => $order_distance]);
                    }
                }
            }
            else
            {
                //二次配送订单
                $order_price = Db::table('gada_order')
                    ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
                    ->value('order_price');
                //二次配送半价
                $order_price = $order_price * 0.5;
            }
            $sum = 0;
            $card_str = implode(',', $card_id);
            if ($n == 0)
            {
                //无助力卡，用余额支付
                $money = Db::table('gada_balance')
                    ->where('uid', "$uid")
                    ->find();
                $balance = $money['reserves'] + $money['amount'];
                if ($balance < $order_price)
                {
                    Response::returnApiError('303', '余额不足');
                }
                else
                {
                    //余额充足
                    $money = Orderpay::balanceComplementedPay($money,$order_price);
                    $is_pay = Db::table('gada_balance')
                        ->where('uid', $uid)
                        ->update($money);
                    //记录交易明细
                    //$order_price即为实际要支付的价格
                    if ($is_second == 0)
                    {
                        Orderpay::transactRecords($money,$order_price,$uid,'购买配送服务','-');
                    }
                    else
                    {
                        Orderpay::transactRecords($money,$order_price,$uid,'购买二次配送服务','-');
                    }
                    if ($is_pay == 1)
                    {
                        if ($is_second == 0)
                        {
                            //更改订单状态并录入期望派送时间,记录时间节点
                            $type0_status0_time = date("H:i");
                            //记录收货人的信息
                            $receiver_msg = Db::table('gada_address')
                                ->where('address_id', $address_id)
                                ->find();
                            $receiver_name = $receiver_msg['receiver_name'];
                            $receiver_phone = $receiver_msg['receiver_phone'];
                            $address = $receiver_msg['address'];
                            $area = $receiver_msg['area'];
                            if ($change_deliver == 0)
                            {
                                Db::table('gada_order')
                                    ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                                    ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'order_current_time' => $current_time]);
                            }
                            elseif ($change_deliver == 1)
                            {
                                $change_deliver_time = date("Y-m-d H:i:s");
                                if (empty($data['order_number']))
                                {
                                    Db::table('gada_order')
                                        ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                        ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                                }
                                else
                                {
                                    //过夜转配送支付
                                    Db::table('gada_order')
                                        ->where(['order_num' => $order_number])
                                        ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                                }
                            }
                            Response::returnApiSuccess(200, '支付并锁箱成功，请等待收货', $receiver_msg);
                        }
                        else
                        {
                            //二次配送订单
                            Db::table('gada_order')
                                ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
                                ->update(['order_type' => UNFINISHED, 'order_status' => SECOND, 'order_dispatch_time' => $dispatch_time, 'second_create_time' => $current_time, 'second_price' => $order_price, 'order_current_time' => $current_time]);
                            //给快递员通知再配送消息
                            Test::courierNotice(7, '您有用户发起再配送的新订单',999999);
                            Response::returnApiOk(200, '再配送申请成功，请务必保持收货人联系方式畅通');
                        }
                    }
                    else
                    {
                        Response::returnApiError(201, '支付失败');
                    }
                }
            }
            else
            {
                //有助力卡结账
                for ($i = 0; $i < $n; $i++)
                {
                    $card[$i] = Db::table('gada_card')
                        ->where('card_id', $card_id[$i])
                        ->value('card_value');
                    $sum += $card[$i];
                }
                if ($order_price > $sum)
                {
                    //订单实际价格大于助力卡价值
                    //余额中要扣除的金额
                    $pay = $order_price - $sum;
                    $money = Db::table('gada_balance')
                        ->where('uid', $uid)
                        ->find();
                    $balance = $money['reserves'] + $money['amount'];
                    if ($balance < $pay)
                    {
                        Response::returnApiError('303', '余额不足');
                    }
                    else
                    {
                        //余额充足
                        $money = Orderpay::balanceComplementedPay($money,$pay);
                        for ($i = 0; $i < $n; $i++)
                        {
                            Db::table('gada_card')
                                ->where('card_id', $card_id[$i])
                                ->update(['is_use' => 1]);
                        }
                        Db::table('gada_balance')
                            ->where('uid', $uid)
                            ->update($money);
                        //记录交易明细
                        //$order_price为实际仍要支付的价格
                        $order_price = $order_price - $sum;
                        Orderpay::transactRecords($money,$order_price,$uid,'购买配送服务','-');
                        //更改订单状态并录入期望派送时间,记录时间节点
                        $type0_status0_time = date("H:i");
                        //记录收货人的信息
                        $receiver_msg = Db::table('gada_address')
                            ->where('address_id', $address_id)
                            ->find();
                        $receiver_name = $receiver_msg['receiver_name'];
                        $receiver_phone = $receiver_msg['receiver_phone'];
                        $address = $receiver_msg['address'];
                        $area = $receiver_msg['area'];
                        if ($is_second == 0) {
                            if ($change_deliver == 0) {
                                Db::table('gada_order')
                                    ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                                    ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'use_card' => $card_str]);
                            } elseif ($change_deliver == 1) {
                                $change_deliver_time = date("Y-m-d H:i:s");
                                if (empty($data['order_number'])) {
                                    Db::table('gada_order')
                                        ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                        ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                                } else {
                                    //过夜转配送支付
                                    Db::table('gada_order')
                                        ->where(['order_num' => $order_number])
                                        ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                                }
                            }
                            Response::returnApiOk(200, '支付并锁箱成功，请等待收货');
                        }
                        else
                        {
                            //二次配送
                            Db::table('gada_order')
                                ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
                                ->update(['order_type' => UNFINISHED, 'order_status' => SECOND, 'order_dispatch_time' => $dispatch_time, 'second_create_time' => $current_time, 'second_price' => $order_price, 'order_current_time' => $current_time]);
                            //给快递员通知再配送消息
                            Test::courierNotice(7, '您有用户发起再配送的新订单',999999);
                            Response::returnApiOk(200, '再配送申请成功，请务必保持收货人联系方式畅通');
                        }

                    }
                }
                else
                {
                    //卡券即能完全支付
                    for ($i = 0; $i < $n; $i++)
                    {
                        Db::table('gada_card')
                            ->where('card_id', $card_id[$i])
                            ->update(['is_use' => 1]);
                    }
                    //更改订单状态并录入期望派送时间,记录时间节点
                    $type0_status0_time = date("H:i");
                    //记录收货人的信息
                    $receiver_msg = Db::table('gada_address')
                        ->where('address_id', $address_id)
                        ->find();
                    $receiver_name = $receiver_msg['receiver_name'];
                    $receiver_phone = $receiver_msg['receiver_phone'];
                    $address = $receiver_msg['address'];
                    $area = $receiver_msg['area'];
                    if ($is_second == 0)
                    {
                        if ($change_deliver == 0)
                        {
                            Db::table('gada_order')
                                ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                                ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'use_card' => $card_str]);
                        }
                        elseif ($change_deliver == 1)
                        {
                            $change_deliver_time = date("Y-m-d H:i:s");
                            if (empty($data['order_number']))
                            {
                                Db::table('gada_order')
                                    ->where(['uid' => $uid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                    ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                            }
                            else
                            {
                                //过夜转配送支付
                                Db::table('gada_order')
                                    ->where(['order_num' => $order_number])
                                    ->update(['order_type' => UNFINISHED, 'order_status' => PAID, 'order_dispatch_time' => $dispatch_time, 'type0_status0_time' => $type0_status0_time, 'address' => $address, 'area' => $area, 'receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'change_deliver_time' => $change_deliver_time, 'order_current_time' => $current_time]);
                            }
                        }
                        Response::returnApiOk(200, '支付并锁箱成功，请等待收货');
                    } else {
                        Db::table('gada_order')
                            ->where(['uid' => $uid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
                            ->update(['order_type' => UNFINISHED, 'order_status' => SECOND, 'order_dispatch_time' => $dispatch_time, 'second_create_time' => $current_time, 'second_price' => $order_price, 'order_current_time' => $current_time]);
                        //给快递员通知再配送消息
                        Test::courierNotice(7, '您有用户发起再配送的新订单',9999999);
                        Response::returnApiOk(200, '再配送申请成功，请务必保持收货人联系方式畅通');
                    }
                }
            }
        }
    }

    public function changeAddressPay()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $order_number = $data['order_number'];
        $card_id = $data['card_id'];
        $current_time = date("Y-m-d H:i:s");
        //统计是否使用助力卡
        $n = count($card_id);
        $order_cache = Db::table('gada_order')
            ->where('order_num', $order_number)
            ->find();
        $uid = $order_cache['uid'];
        $money = Db::table('gada_balance')
            ->where('uid', $order_cache['uid'])
            ->find();
        //订单的实际总价格
        $order_price = $order_cache['cache_price'] + $order_cache['order_price'];
        $order_distance = $order_cache['cache_distance'];
        if ($order_cache['cache_price'] < 0)
        {
            //多退的金额
            $refund = abs($order_cache['cache_price']);
            $reserves = $money['reserves'] + $refund;
            Db::table('gada_balance')
                ->where('uid', $order_cache['uid'])
                ->update(['reserves' => $reserves]);
            $current_balance = $money['amount'] + $reserves;
            //多退的金额记录
            Db::table('gada_transact')
                ->insert(['user_id' => $uid, 'transact_content' => '更改配送服务', 'transact_detail' => '+' . $refund, 'current_balance' => $current_balance]);
            //更新订单实际总金额和配送距离
            Db::table('gada_order')
                ->where('order_num', $order_number)
                ->update(['order_price' => $order_price, 'change_distance' => $order_distance, 'change_address' => 2,'change_receiver_name'=>$order_cache['cache_receiver'],'change_receiver_phone'=>$order_cache['cache_phone'],'change_receiver_address'=>$order_cache['cache_address']]);
        }
        elseif ($order_cache['cache_price'] == 0)
        {
            //更新订单实际总金额和配送距离
            Db::table('gada_order')
                ->where('order_num', $order_number)
                ->update(['order_price' => $order_price, 'change_distance' => $order_distance, 'change_address' => 2,'change_receiver_name'=>$order_cache['cache_receiver'],'change_receiver_phone'=>$order_cache['cache_phone'],'change_receiver_address'=>$order_cache['cache_address']]);
        }
        else
        {
            //少补的金额
            $payment = $order_cache['cache_price'];
            $sum = 0;
            $card_str = implode(',', $card_id);
            if ($n == 0)
            {
                //无助力卡，用余额支付
                $balance = $money['reserves'] + $money['amount'];
                if ($balance < $payment)
                {
                    Response::returnApiError('303', '余额不足');
                    return;
                }
                else
                {
                    //余额充足
                    $money = Orderpay::balanceComplementedPay($money,$payment);
                    $is_pay = Db::table('gada_balance')
                        ->where('uid', $uid)
                        ->update($money);
                    //记录交易明细
                    Orderpay::transactRecords($money,$payment,$uid,'更改配送服务','-');
                    //更新订单实际总金额和配送距离
                    Db::table('gada_order')
                        ->where('order_num', $order_number)
                        ->update(['order_price' => $order_price, 'change_distance' => $order_distance, 'change_address' => 2,'change_receiver_name'=>$order_cache['cache_receiver'],'change_receiver_phone'=>$order_cache['cache_phone'],'change_receiver_address'=>$order_cache['cache_address']]);
                }
            }
            else
            {
                //有助力卡结账
                for ($i = 0; $i < $n; $i++)
                {
                    $card[$i] = Db::table('gada_card')
                        ->where('card_id', $card_id[$i])
                        ->value('card_value');
                    $sum += $card[$i];
                }
                if ($payment > $sum)
                {
                    //订单实际价格大于助力卡价值
                    //余额中要扣除的金额
                    $pay = $payment - $sum;
                    $balance = $money['reserves'] + $money['amount'];
                    if ($balance < $pay)
                    {
                        Response::returnApiError('303', '余额不足');
                        return;
                    }
                    else
                    {
                        //余额充足
                        $money = Orderpay::balanceComplementedPay($money,$pay);
                        for ($i = 0; $i < $n; $i++)
                        {
                            Db::table('gada_card')
                                ->where('card_id', $card_id[$i])
                                ->update(['is_use' => 1]);
                        }
                        Db::table('gada_balance')
                            ->where('uid', $uid)
                            ->update($money);
                        //记录交易明细
                        //$pay为实际仍要支付的价格
                        $pay = $payment - $sum;
                        Orderpay::transactRecords($money,$pay,$uid,'购买配送服务','-');
                        //更新订单实际总金额和配送距离
                        Db::table('gada_order')
                            ->where('order_num', $order_number)
                            ->update(['order_price' => $order_price, 'change_distance' => $order_distance, 'change_address' => 2,'change_receiver_name'=>$order_cache['cache_receiver'],'change_receiver_phone'=>$order_cache['cache_phone'],'change_receiver_address'=>$order_cache['cache_address']]);
                    }
                }
                else
                {
                    //助力卡金额刚好够支付
                    //更新订单实际总金额和配送距离
                    Db::table('gada_order')
                        ->where('order_num', $order_number)
                        ->update(['order_price' => $order_price, 'change_distance' => $order_distance, 'change_address' => 2,'change_receiver_name'=>$order_cache['cache_receiver'],'change_receiver_phone'=>$order_cache['cache_phone'],'change_receiver_address'=>$order_cache['cache_address']]);
                }
            }
        }
        Test::courierNotice(14,'用户已完成更改地址，请查看新的配送地点',$order_number);
        //记录订单地址已修改的信息（长久保存的消息展示用）
        Db::table('gada_change_address_log')
            ->insert(['order_number' => $order_number, 'change_address' => 2, 'uid' => $uid]);
        Response::returnApiOk('200', '修改地址成功');
    }
    public static function Pay()
    {

    }
    public static function balanceComplementedPay($money,$pay)
    {
        //余额充足
        if ($money['amount'] >= $pay)
        {
            $money['amount'] = $money['amount'] - $pay;
        }
        else
        {
            $pay = $pay - $money['amount'];
            $money['amount'] = 0;
            $money['reserves'] = $money['reserves'] - $pay;
        }
        return $money;
    }
    public static function transactRecords($money,$order_price,$uid,$transact_content,$transact_detail)
    {
        //记录交易明细
        $current_balance = $money['reserves'] + $money['amount']; //当前余额
        //$order_price为实际仍要支付的价格
        //保留两位小数方便观看
        $current_balance = number_format($current_balance, 2);
        Db::table('gada_transact')
            ->insert(['user_id' => $uid, 'transact_content' => $transact_content, 'transact_detail' => $transact_detail . $order_price, 'current_balance' => $current_balance]);
    }
    public static function scoreRecords($uid,$score_content,$score_detail)
    {
        //增加积分
        Db::table('gada_score')
            //->where('user_id',$uid)
            ->insert(['score_content' => $score_content, 'user_id' => $uid, 'score_detail' => $score_detail]);
        $score = Db::table('gada_user')
            ->where('user_id', $uid)
            ->value('score');
        $score = $score + 1;
        Db::table('gada_user')
            ->where('user_id', $uid)
            ->update(['score' => $score]);
    }
}