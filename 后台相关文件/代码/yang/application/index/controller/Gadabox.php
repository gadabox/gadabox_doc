<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/8
 * Time: 14:09
 */
namespace app\index\controller;
require_once('Response.php');
use \think\Db;
use \think\Controller;
require_once ('Constants.php');
require_once ('Androidpush.php');

Class Gadabox extends controller
{
    public function openBox()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $box_code = $data['box_code'];
        $is_box = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        if(empty($is_box))
        {
            Response::returnApiError(201,'该箱号为无效码');
        }
        else
        {
            $balance = Db::table('gada_balance')
                ->where('uid', $uid)
                ->find();
            $balance = $balance['reserves'] + $balance['amount'];
            //冻结账户不可能为负，即只需判定可动账户是否欠费即可。
            if($balance['amount'] < 0) {
                Response::returnApiError(201, '账号欠费，请先充值');
            }
            $is_user = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->value('is_user');
            $is_courier = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->value('is_courier');
            //判定箱子是否无人占用
            if(empty($is_user) && empty($is_courier))
            {
                //向箱子端发送开锁指令，确认箱子开锁成功之后，再进行开箱人记录和临时订单的生成。
                /**
                 * 该区域为箱子对接通讯代码
                 */
                //以上空白处留着开箱确认部分代码

                $bid = Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->value('bid');
                //先暂定开箱的同时箱子也开盖了
                Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_user' => $uid, 'is_close' => 0,'is_lock' => 0]);
                //生成订单编号（时间+5位随机数+box_code）
                $order_num = Ordermsg::createorder();
                $order_create_time = date('Y-m-d H:i:s');
                //生成特殊临时订单
                Db::table('gada_order')
                    ->insert(['uid' => $uid, 'order_num' => $order_num, 'order_box' => $bid,'order_create_time'=>$order_create_time]);
                /*$order_create_time = Db::table('gada_order')
                    ->where('order_num', $order_num)
                    ->value('order_create_time');*/
                $order_create_time = strtotime($order_create_time) * 1000;
                $current_time = time() * 1000;
                $hourly_rate = 1;
                $msg = ['order_create_time' => $order_create_time, 'current_time' => $current_time, 'hourly_rate' => $hourly_rate];
                Response::returnApiSuccess(200, '开锁成功', $msg);
            }
            else
            {
                Response::returnApiError(201, '该箱目前不可使用，换一个试试');
            }
        }
    }
    //免费试用时间内的取消,取消后特殊订单type=2 status=0 变成 特殊订单type=2 status=4的无效订单。
    public function cancelUse()
    {
        //针对未下单的特殊订单的取消反馈
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        //$token = $data['token'];
        $box_code = $data['box_code'];
        $is_close = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('is_close');
        //接收箱子端提示判断是否盖盖。
        if($is_close==0)
        {
            Response::returnApiError(201 ,'请盖上嘎哒箱');
        }
        else
        {
            //把箱子状态释放掉
            $box_msg = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->field('is_user,bid')
                ->find();
            //查询该订单信息，进行扣费处理
            $order_create_time = Db::table('gada_order')
                ->where(['order_box' => $box_msg['bid'], 'order_type' => 2, 'order_status' => 0])
                ->value('order_create_time');
            $order_create_time = strtotime($order_create_time);
            $current_time = time();
            $over_time = date('Y-m-d H:i:s', $current_time);
            //十分钟之内不收费，十分钟之后开始计时
            $time = $current_time - $order_create_time;
            if($time <= 60)
            {
                $is_canceluse = Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_user' => '','is_lock'=>1]);
                //不收取费用
                $is_payed = 0; //给前端用于识别是否产生费用
                $price = '';
                $data = ['is_payed' => $is_payed];
                $is_invalid = Db::table('gada_order')
                    ->where(['order_box' => $box_msg['bid'], 'order_type' => 2, 'order_status' => 0])
                    ->update(['order_status' => 4, 'order_price' => $price, 'order_over_time' => $over_time]);
                if($is_canceluse == 1 && $is_invalid ==1)
                {
                    Response::returnApiSuccess(200, '取消使用成功', $data);
                }
                else
                {
                    Response::returnApiError(201, '取消使用失败');
                }
            }
            else
            {
                //超过十分钟，收取费用
                $is_payed = 1; //给前端用于识别是否产生费用
                $hourly_rate = 1;
                $price = $hourly_rate * ceil(($time - 60) / 3600);
                //占用时每小时的计费标准（1元）走账
                $balance = Db::table('gada_balance')
                    ->where('uid', $box_msg['is_user'])
                    ->find();
                if ($balance['reserves'] > $price || $balance['reserves'] = $price)
                {
                    //优先扣除冻结账户
                    $balance['reserves'] = $balance['reserves'] - $price;
                    //用于记录是否成功计费
                    $is_pay = Db::table('gada_balance')
                        ->where('uid', $box_msg['is_user'])
                        ->update($balance);
                }
                else
                {
                    //冻结账户金额不足，直接扣除余额（无论是否够用）
                    $balance['amount'] = $balance['amount'] - $price;
                    $is_pay = Db::table('gada_balance')
                        ->where('uid', $box_msg['is_user'])
                        ->update($balance);
                }
                //记录交易明细
                Orderpay::transactRecords($balance,$price,$box_msg['is_user'],'取消使用超时的费用','-');
                //变换订单类型使之无效化
                $is_invalid = Db::table('gada_order')
                    //->join('gada_order','gada_box . bid = gada_order . order_box')
                    ->where(['order_box' => $box_msg['bid'], 'order_type' => 2, 'order_status' => 0])
                    ->update(['order_status' => 7, 'order_price' => $price, 'order_over_time' => $over_time]);
                $data = ['is_payed' => $is_payed];
                $is_canceluse = Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_user' => '','is_lock'=>1]);
                if ($is_canceluse == 1 && $is_invalid == 1 && $is_pay == 1)
                {
                    Response::returnApiSuccess(200, '取消使用成功', $data);
                }
                else
                {
                    Response::returnApiError(201, '取消使用失败');
                }
            }
        }
    }
    //收到箱子微动开关的反馈，然后透传用户，用户锁箱按钮才能高亮点击
    public function is_close()
    {
       $boxcode = 123456;
       $phone = Db::table('gada_box')
            ->join('gada_user','gada_box . is_user = gada_user . user_id')
            ->where('box_code',$boxcode)
            ->value('user_phone');
        Androidpush::pushphone($phone);
    }
    //获取未完成订单的箱子的计时等信息的反馈
    public function getBoxMsg()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $box_code = $data['box_code'];
        $bid = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        $order_create_time = Db::table('gada_order')
            ->where(['order_box'=>$bid,'order_type'=>2,'order_status'=>0])
            ->value('order_create_time');
        $order_create_time = strtotime($order_create_time) * 1000;
        $current_time = time() *1000;
        //占用时每小时的计费标准（1元）
        $hourly_rate = 1;
        $msg = ['order_create_time' => $order_create_time,'current_time' => $current_time,'hourly_rate' => $hourly_rate];
        Response::returnApiSuccess(200,'反馈成功',$msg);
    }
    public function isRead()
    {
        //修改用户已读的强关订单
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $price = Db::table('gada_order')
            ->where(['uid'=>$uid,'order_type'=>SPECIAL,'order_status'=>FORCE_CLOSE])
            ->value('order_price');
        if(empty($price))
        {
            //该订单无费用支出
            $is_payed = 0;
            $is_read = Db::table('gada_order')
                ->where(['uid'=>$uid,'order_type'=>SPECIAL,'order_status'=>FORCE_CLOSE])
                ->update(['order_status'=>IS_READ_NOPAID]);
        }
        else
        {
            //该订单有费用支出
            $is_payed = 1;
            $is_read = Db::table('gada_order')
                ->where(['uid'=>$uid,'order_type'=>SPECIAL,'order_status'=>FORCE_CLOSE])
                ->update(['order_status'=>IS_READ_PAID]);
        }
        $data = ['is_payed'=>$is_payed];
        if($is_read == 1)
        {
            Response::returnApiSuccess(200,'已读',$data);
        }
    }
    public function overNight()
    {
        $night_fee = 20;
        //清算所有当前时间仍处于占用或者未签收的订单
        $deposit_order = Db::table('gada_order')
            ->where(['order_type'=>UNFINISHED,'order_status'=>OCCUPY])
            ->field('order_num,uid')
            ->select();
        $deposit_num = count($deposit_order);
        for($i=0;$i<$deposit_num;$i++)
        {
            Db::table('gada_order')
                ->where('order_num',$deposit_order[$i]['order_num'])
                ->update(['order_type'=>SPECIAL,'order_status'=>TIMEOUT,'order_price'=>'']);
        }
        $no_receive_order = Db::table('gada_order')
            ->where(['order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
            ->field('order_num,uid,order_price,second_price,courier')
            ->select();
        $no_receive_num = count($no_receive_order);
        for($i=0;$i<$no_receive_num;$i++)
        {
            //清算快递员收入
            $over_time = date("Y-m-d H:i:s");
            $income = $no_receive_order[$i]['order_price'] + $no_receive_order[$i]['second_price'] -2;
            Db::table('gada_corder')
                ->insert(['order_number'=>$no_receive_order[$i]['order_num'],'income'=>$income,'over_time'=>$over_time,'id_courier'=>$no_receive_order[$i]['courier']]);
            //转未签收的订单状态为无人签收超时状态，移除快递员的绑定
            Db::table('gada_order')
                ->where('order_num',$no_receive_order[$i]['order_num'])
                ->update(['order_type'=>SPECIAL,'order_status'=>NO_RECEIVE_TIMEOUT]);
        }
        $order_msg = array_merge($deposit_order,$no_receive_order);
        $order_sum = $deposit_num + $no_receive_num;
        for($i=0;$i<$order_sum;$i++)
        {
            //记录过夜费的扣除,清除之前的所有占用费用
            $balance = Db::table('gada_balance')
                ->where('uid', $order_msg[$i]['uid'])
                ->find();
            if ($balance['reserves'] > $night_fee || $balance['reserves'] == $night_fee)
            {
                //优先扣除冻结账户
                $balance['reserves'] = $balance['reserves'] - $night_fee;
                //用于记录是否成功计费
                $is_pay = Db::table('gada_balance')
                    ->where('uid', $order_msg[$i]['uid'])
                    ->update($balance);
            }
            else
            {
                //冻结账户金额不足，直接扣除余额（无论是否够用）
                $balance['amount'] = $balance['amount'] - $night_fee;
                $is_pay = Db::table('gada_balance')
                    ->where('uid', $order_msg[$i]['uid'])
                    ->update($balance);
            }
            if($is_pay == 1)
            {
                //占用订单清算过夜费记录
                Db::table('gada_order')
                    ->where('order_num', $order_msg[$i]['order_num'])
                    ->update(['night_fee' => $night_fee]);
                //记录交易明细
                Orderpay::transactRecords($balance,$night_fee,$order_msg[$i]['uid'],'过夜费','-');
                echo '完成过夜清算';
            }
        }
    }
    public function openBack()
    {
        //配送锁箱后返回开箱页，执行开箱操作
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $box_code = $data['box_code'];
        $token = $data['token'];
        $phone = Db::table('gada_token')
            ->join('gada_user','gada_token . user_id = gada_user . user_id')
            ->where('user_token',$token)
            ->value('user_phone');
        Db::table('gada_box')
            ->where('box_code', $box_code)
            ->update(['is_lock' => 0]);
        Androidpush::pushphone($phone);
        Response::returnApiOk(200,'开锁成功');
    }
}