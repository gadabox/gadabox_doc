<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/29
 * Time: 16:58
 */
namespace app\courier\controller;
use app\index\controller\Response;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
use \think\Db;
require ('Constants.php');
class Showdata
{
    public function showdata()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $ctoken = $data['ctoken'];
        $courier_msg = Db::table('gada_ctoken')
            ->join('gada_courier','gada_ctoken . id_courier = gada_courier . id_courier')
            ->where('courier_token',$ctoken)
            ->field('cname,cphone,courier_type,pic_id,bank_card')
            ->find();
        $photo = Db::table('gada_cphoto')
            ->where('pic_id',$courier_msg['pic_id'])
            ->value('content');
        $data = ['userName'=>$courier_msg['cname'],'phone'=>$courier_msg['cphone'],'courier_type'=>$courier_msg['courier_type'],'avatar'=>$photo,'bank_card'=>$courier_msg['bank_card']];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function extradata()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $ctoken = $data['ctoken'];
        $courier_msg = Db::table('gada_ctoken')
            ->join('gada_courier','gada_ctoken . id_courier = gada_courier . id_courier')
            ->where('courier_token',$ctoken)
            ->field('is_working,gada_courier .id_courier')
            ->find();
        $today = date("Y-m-d") . '%';
        $order = Db::table('gada_corder')
            ->where('over_time', 'like', "{$today}")
            ->where(['id_courier' => $courier_msg['id_courier']])
            ->select();
        $num = count($order);
        $paid = Db::table('gada_order')
            ->where(['courier'=>$courier_msg['id_courier'],'order_type'=>UNFINISHED,'order_status'=>PAID])
            ->select();
        $paid_count = count($paid);
        $pushed = Db::table('gada_order')
            ->where(['courier'=>$courier_msg['id_courier'],'order_type'=>UNFINISHED,'order_status'=>PUSHED])
            ->select();
        $pushed_count = count($pushed);
        $dispatch = Db::table('gada_order')
            ->where(['courier'=>$courier_msg['id_courier'],'order_type'=>UNFINISHED,'order_status'=>DISPATCH])
            ->select();
        $delivered = Db::table('gada_order')
            ->where(['courier'=>$courier_msg['id_courier'],'order_type'=>UNFINISHED,'order_status'=>DELIVERED])
            ->select();
        //查看再配送订单
        $second_deliver = Db::table('gada_order')
            ->where(['courier' => $courier_msg['id_courier'], 'order_type' => UNFINISHED, 'order_status'=>SECOND])
           // ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address,goback_return_time')
            ->select();
        $second_deliver_num = count($second_deliver);
        $second_delivered = Db::table('gada_order')
            ->where(['courier' => $courier_msg['id_courier'], 'order_type' => UNFINISHED, 'order_status'=>SECONDED])
           // ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address,goback_return_time')
            ->select();
        $second_delivered_num = count($second_delivered);
        $dispatch_count = count($dispatch);
        $delivered_count = count($delivered);
        //整合配送中和配送到的订单数
        $dispatch_count = $dispatch_count + $delivered_count + $second_deliver_num + $second_delivered_num;
        $no_receive = Db::table('gada_order')
            ->where(['courier'=>$courier_msg['id_courier'],'order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
            ->select();
        $return = Db::table('gada_box')
            ->where('is_courier',$courier_msg['id_courier'])
            ->field('order_number')
            ->select();
        $return_count = count($return);
        $no_receive_count = count($no_receive);
        $no_receive_count = $no_receive_count + $return_count;
        $data = ['order_count'=>$num,'is_working'=>$courier_msg['is_working'],'taking_order_count'=>$paid_count,'taking_box_count'=>$pushed_count,'dispatching_order_count'=>$dispatch_count,'backing_order_count'=>$no_receive_count];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function workSummary()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $duration = $data['duration'].'%';
        $ctoken = $data['ctoken'];
        $type = $data['type'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $order = [];
        //已完成订单查看
        if($type == 0){
            $work_summary = Db::table('gada_corder')
                ->where('over_time', 'like', "{$duration}")
                ->where(['id_courier' => $id_courier])
                ->field('order_number,income,over_time')
                ->select();
            $n = count($work_summary);
            if ($n == 0) {
                $data = ['finished_orders' => []];
            } else {
                for ($i = 0; $i < $n; $i++) {
                    $income = $work_summary[$i]['income'];
                    $order[$i] = ['order_number' => $work_summary[$i]['order_number'], 'income' => $income, 'time' => $work_summary[$i]['over_time']];
                }
                $data = ['finished_orders' => $order];
            }

        }
        elseif($type == 1)
        {
            $no_receive_goback = Db::table('gada_order')
                ->where(['courier'=>$id_courier,'order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
                ->field('goback_return_time,order_num,order_price')
                ->select();
            //无人签收的所有订单数
            $m = count($no_receive_goback);
            //初始化无人签收已还箱的订单数
            $j = 0;
            for($i=0;$i<$m;$i++)
            {
                if(!empty($no_receive_goback[$i]['goback_return_time']))
                {
                    $no_receive_goback[$i]['order_price'] = $no_receive_goback[$i]['order_price'] -2;
                    $order[$j] = ['order_number'=>$no_receive_goback[$i]['order_num'],'content'=>'待收入金额','income'=>(string)$no_receive_goback[$i]['order_price'],'time'=>$no_receive_goback[$i]['goback_return_time']];
                    $j++;
                }
            }
            $data = ['exception_orders' => $order];
        }
        Response::returnApiSuccess(200, '返回成功', $data);
    }
    public function summary()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $duration = $data['duration'].'%';
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        $work_summary = Db::table('gada_corder')
            ->where('over_time','like',"{$duration}")
            ->where(['id_courier'=>$id_courier])
            ->field('order_number,income,over_time')
            ->select();
        //完成订单的个数
        $n = count($work_summary);
        //初始化总收入
        $sum = 0;
        //完成订单的总收入
        for($i=0;$i<$n;$i++)
        {
            $sum += $work_summary[$i]['income'];
        }
        $no_receive_goback = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
            ->field('goback_return_time')
            ->select();
        //无人签收的所有订单数
        $m = count($no_receive_goback);
        //初始化无人签收已还箱的订单数
        $j = 0;
        for($i=0;$i<$m;$i++)
        {
            if(!empty($no_receive_goback[$i]['goback_return_time']))
            {
                $j++;
            }
        }
        $data = ['finished_number'=>$n,'exception_number'=>$j,'total_income'=>$sum];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function bindingBankCard()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $ctoken = $data['ctoken'];
        $identification_number = Db::table('gada_ctoken')
            ->join('gada_courier', 'gada_ctoken . id_courier = gada_courier . id_courier')
            ->where('courier_token',$ctoken)
            ->value('courier_identification');
        $courier_name = $data['courier_name'];
        $identification = $data['courier_identification'];
        $bank_card = $data['bank_card'];

        if($identification_number == $identification)
        {
            //绑定银行卡
            Db::table('gada_courier')
                ->where('courier_identification',$identification)
                ->update(['bank_card'=>$bank_card]);
            Response::returnApiOk(200,'绑定成功');
        }
        else
        {
            Response::returnApiError(201,'请绑定本人银行卡');
        }
    }
}