<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/29
 * Time: 16:49
 */
namespace app\courier\controller;
use app\index\controller\Response;
require ('Constants.php');
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
use \think\Db;

class Showorder
{
    public function showorder()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $ctoken = $data['ctoken'];
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        if($data['order_status'] == PAID)
        {
            $this->paidOrder($id_courier);
        }
        if($data['order_status'] == PUSHED)
        {
            $this->pushedorder($id_courier);
        }
        if($data['order_status'] == DISPATCH || $data['order_status'] == DELIVERED)
        {
            $this->dispatchOrder($id_courier);
        }
        if($data['order_status'] == 4)
        {
            $this->noReceiveOrder($id_courier);
        }
    }
    public function paidOrder($id_courier)
    {
        $paid = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>PAID])
            ->field('order_num,order_box,order_price,address,area,receiver_name,receiver_phone,order_arrive_time,uid,order_distance,change_distance,change_receiver_name,change_receiver_phone,change_receiver_address,change_address')
            ->select();
        $n = count($paid);
        if($n == 0)
        {
            //无订单时
            $data = array();
        }
        else {
            for ($i = 0; $i < $n; $i++)
            {
                $order_price[$i] = $paid[$i]['order_price'] - 2;
                //配送地址
                /*$address[$i] = Db::table('gada_address')
                    ->where('address_id', $paid[$i]['order_address_id'])
                    ->field('area,address,receiver_name,receiver_phone')
                    ->find();*/
                //箱子所属商家
                $box[$i] = Db::table('gada_box')
                    ->where('bid', $paid[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();

               /* $receiver_phone[$i] = Db::table('gada_user')
                    ->where('user_id', $paid[$i]['uid'])
                    ->value('user_phone');*/
                if($paid[$i]['change_address'] != 2)
                {
                    $data[$i] = ['order_number' => $paid[$i]['order_num'], 'time' => $paid[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $paid[$i]['address'], 'receiver_area' => $paid[$i]['area'], 'receiver' => $paid[$i]['receiver_name'], 'receiver_phone' => $paid[$i]['receiver_phone'], 'distance' => $paid[$i]['order_distance'],'change_address'=>$paid[$i]['change_address']];
                }
                else
                {
                    $data[$i] = ['order_number' => $paid[$i]['order_num'], 'time' => $paid[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $paid[$i]['change_receiver_address'], 'receiver' => $paid[$i]['change_receiver_name'], 'receiver_phone' => $paid[$i]['change_receiver_phone'], 'distance' => $paid[$i]['change_distance'],'change_address'=>$paid[$i]['change_address']];
                }
            }
        }
        //$data = $i;
        $data = ['orders'=>$data];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function pushedorder($id_courier)
    {
        $pushed = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>PUSHED])
            ->field('order_num,order_box,order_price,address,area,receiver_name,receiver_phone,change_receiver_address,change_distance,change_receiver_name,change_receiver_phone,order_arrive_time,uid,order_distance,change_address')
            ->select();
        $n = count($pushed);
        if($n == 0)
        {
            //无订单时
            $data = array();
        }
        else {
            for ($i = 0; $i < $n; $i++) {
                $order_price[$i] = $pushed[$i]['order_price'] - 2;
                //配送地址
                /*$address[$i] = Db::table('gada_address')
                    ->where('address_id', $pushed[$i]['order_address_id'])
                    ->field('area,address,receiver_name,receiver_phone')
                    ->find();*/
                //箱子所属商家
                $box[$i] = Db::table('gada_box')
                    ->where('bid', $pushed[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();
                $receiver_phone[$i] = Db::table('gada_user')
                    ->where('user_id', $pushed[$i]['uid'])
                    ->value('user_phone');
                if ($pushed[$i]['change_address'] != 2)
                {
                    $data[$i] = ['order_number' => $pushed[$i]['order_num'], 'time' => $pushed[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $pushed[$i]['address'], 'receiver_area' => $pushed[$i]['area'], 'receiver' => $pushed[$i]['receiver_name'], 'receiver_phone' => $pushed[$i]['receiver_phone'], 'distance' => $pushed[$i]['order_distance'],'change_address'=>$pushed[$i]['change_address']];
                }
                else
                {
                    $data[$i] = ['order_number' => $pushed[$i]['order_num'], 'time' => $pushed[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $pushed[$i]['change_receiver_address'], 'receiver' => $pushed[$i]['change_receiver_name'], 'receiver_phone' => $pushed[$i]['change_receiver_phone'], 'distance' => $pushed[$i]['change_distance'],'change_address'=>$pushed[$i]['change_address']];
                }
            }
        }
        $data = ['orders'=>$data];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function dispatchOrder($id_courier)
    {
        //查看配送中的订单
        $dispatch = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>DISPATCH])
            ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_receiver_name,change_distance,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address')
            ->select();
        $dispatch_num = count($dispatch);
      //  if($n == 0) {
        //看是否有配送到的订单
        $delivered = Db::table('gada_order')
            ->where(['courier' => $id_courier, 'order_type' => UNFINISHED, 'order_status'=>DELIVERED])
            ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_distance,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address')
            ->select();
        $delivered_num = count($delivered);
        //查看再配送订单
        $second_deliver = Db::table('gada_order')
            ->where(['courier' => $id_courier, 'order_type' => UNFINISHED, 'order_status'=>SECOND])
            ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_distance,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address,goback_return_time,second_delivered_time')
            ->select();
        $second_deliver_num = count($second_deliver);
        $second_delivered = Db::table('gada_order')
            ->where(['courier' => $id_courier, 'order_type' => UNFINISHED, 'order_status'=>SECONDED])
            ->field('order_num,order_box,order_price,second_price,second_create_time,address,area,receiver_name,receiver_phone,change_distance,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,order_delivered_time,change_address,goback_return_time,second_delivered_time')
            ->select();
        $second_delivered_num = count($second_delivered);
         //   }
        if($dispatch_num == 0 && $delivered_num ==0 && $second_deliver_num == 0 && $second_delivered_num ==0)
        {
            //无订单时
            $data = array();
        }
        else {
            //结合四种订单展示
            $order_msg = array_merge($dispatch,$delivered,$second_deliver,$second_delivered);

            $sum = $dispatch_num + $delivered_num + $second_deliver_num + $second_delivered_num;       //总共订单数

            for ($i = 0; $i < $sum; $i++) {
                if(empty($order_msg[$i]['second_create_time']))
                {
                    //首次配送
                        $order_msg[$i]['dispatch_again'] = 0;
                }
                else
                {
                    //免费或者5折费用的二次配送
                    $order_msg[$i]['dispatch_again'] = 1;
                    $order_msg[$i]['order_delivered_time'] = $order_msg[$i]['second_delivered_time'];

                }
                if(empty($order_msg[$i]['second_price'])) {
                    $order_price[$i] = $order_msg[$i]['order_price'] - 2;
                }
                else
                {
                    //区分是未折回的免费再配送还是已折回的5折二次配送
                    if(empty($order_msg[$i]['goback_return_time']))
                    {
                        $order_price[$i] = $order_msg[$i]['order_price'] - 2;
                    }
                    else
                    {
                        $order_price[$i] = $order_msg[$i]['second_price'] + $order_msg[$i]['order_price'] - 2;
                    }
                }
                //配送地址
                /*$address[$i] = Db::table('gada_address')
                    ->where('address_id', $dispatch[$i]['order_address_id'])
                    ->field('area,address,receiver_name,receiver_phone')
                    ->find();*/
                //箱子所属商家
                $box[$i] = Db::table('gada_box')
                    ->where('bid', $order_msg[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();
                /*$receiver_phone[$i] = Db::table('gada_user')
                    ->where('user_id', $dispatch[$i]['uid'])
                    ->value('user_phone');*/
                if ($order_msg[$i]['change_address'] != 2)
                {
                    $data[$i] = ['order_number' => $order_msg[$i]['order_num'], 'time' => $order_msg[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $order_msg[$i]['address'], 'receiver_area' => $order_msg[$i]['area'], 'receiver' => $order_msg[$i]['receiver_name'], 'receiver_phone' => $order_msg[$i]['receiver_phone'], 'distance' => $order_msg[$i]['order_distance'], 'order_delivered_time' => $order_msg[$i]['order_delivered_time'], 'change_address' => $order_msg[$i]['change_address'],'dispatch_again'=>$order_msg[$i]['dispatch_again']];
                }
                else
                {
                    $data[$i] = ['order_number' => $order_msg[$i]['order_num'], 'time' => $order_msg[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $order_msg[$i]['change_receiver_address'], 'receiver' => $order_msg[$i]['change_receiver_name'], 'receiver_phone' => $order_msg[$i]['change_receiver_phone'], 'distance' => $order_msg[$i]['change_distance'],'change_address'=>$order_msg[$i]['change_address'], 'order_delivered_time' => $order_msg[$i]['order_delivered_time'],'dispatch_again'=>$order_msg[$i]['dispatch_again']];
                }
            }
        }
        $data = ['orders'=>$data];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function noReceiveOrder($id_courier)
    {
        //整合了正常接受的折返和未签收的折返。
        //未签收
        $no_receive = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>SPECIAL,'order_status'=>NO_RECEIVE])
            ->field('order_num,order_box,order_price,address,area,receiver_name,receiver_phone,change_distance,change_receiver_name,change_receiver_phone,change_receiver_address,order_arrive_time,uid,order_distance,change_address,goback_return_time')
            ->select();
        $n = count($no_receive);
        //筛出并不展示未签收的但已归还箱子的订单
        $k = 0; //定义归还箱子中的订单个数
        for($i=0;$i<$n;$i++)
        {
            if(empty($no_receive[$i]['goback_return_time']))
            {
                $no_receive_going[$k] =  $no_receive[$i];
                $k++;
            }
        }

        //正常签收
        $return = Db::table('gada_box')
            ->where('is_courier',$id_courier)
            ->field('order_number')
            ->select();
        //print_r($return);
        $m = count($return);
        if($k == 0 && $m == 0)
        {
            //无订单时
            $data = array();
        }
        elseif($k != 0) {
            //仅存在未签收订单
            for ($i = 0; $i < $k; $i++) {
                $order_price[$i] = $no_receive_going[$i]['order_price'] - 2;
                //配送地址

                //箱子所属商家
                $box[$i] = Db::table('gada_box')
                    ->where('bid', $no_receive_going[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();

                if ($no_receive_going[$i]['change_address'] != 2)
                {
                    $data[$i] = ['order_number' => $no_receive_going[$i]['order_num'], 'time' => $no_receive_going[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $no_receive_going[$i]['address'], 'receiver_area' => $no_receive_going[$i]['area'], 'receiver' => $no_receive_going[$i]['receiver_name'], 'receiver_phone' => $no_receive_going[$i]['receiver_phone'], 'distance' => $no_receive_going[$i]['order_distance'], 'no_receiver' => 1,'change_address'=>$no_receive_going[$i]['change_address']];
                }
                else
                {
                    $data[$i] = ['order_number' => $no_receive_going[$i]['order_num'], 'time' => $no_receive_going[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $no_receive_going[$i]['change_receiver_address'], 'receiver' => $no_receive_going[$i]['change_receiver_name'], 'receiver_phone' => $no_receive_going[$i]['change_receiver_phone'], 'distance' => $no_receive_going[$i]['change_distance'],'change_address'=>$no_receive_going[$i]['change_address'],'no_receiver' => 1];
                }
            }
        }
        elseif($m != 0 && $k == 0){
            //仅存在正常签收待放还的订单
            for($i=0;$i<$m;$i++){
                $return_order[$i] = Db::table('gada_order')
                    ->where('order_num',$return[$i]['order_number'])
                    ->find();
            }

            for($i=0;$i<$m;$i++) {
                $order_price[$i] = $return_order[$i]['order_price'] - 2;
                //配送地址

                //箱子所属商家
                $box[$i] = Db::table('gada_box')
                    ->where('bid', $return_order[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();

                if ($return_order[$i]['change_address'] ==0) {
                    $data[$i] = ['order_number' => $return_order[$i]['order_num'], 'time' => $return_order[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $return_order[$i]['address'], 'receiver_area' => $return_order[$i]['area'], 'receiver' => $return_order[$i]['receiver_name'], 'receiver_phone' => $return_order[$i]['receiver_phone'], 'distance' => $return_order[$i]['order_distance'], 'no_receiver' => 0,'change_address'=>$return_order[$i]['change_address']];
                }
                else
                {
                    $data[$i] = ['order_number' => $return_order[$i]['order_num'], 'time' => $return_order[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $return_order[$i]['change_receiver_address'], 'receiver' => $return_order[$i]['change_receiver_name'], 'receiver_phone' => $return_order[$i]['change_receiver_phone'], 'distance' => $return_order[$i]['change_distance'],'change_address'=>$return_order[$i]['change_address'], 'no_receiver' => 0];
                }
            }

        }
        if($m !=0 && $k != 0){
            //两种订单均存在
            for($i=0;$i<$m;$i++){
                $return_order[$i] = Db::table('gada_order')
                    ->where('order_num',$return[$i]['order_number'])
                    ->find();
            }
            //设置数组中正常签收订单待归还嘎哒箱的起始数组位置
            $j = $k;
            for($i=0;$i<$m;$i++){
                $order_price[$j] = $return_order[$i]['order_price'] - 2;
                //配送地址
                $address[$j] = Db::table('gada_address')
                    ->where('address_id', $return_order[$i]['order_address_id'])
                    ->field('area,address,receiver_name,receiver_phone')
                    ->find();
                //箱子所属商家
                $box[$j] = Db::table('gada_box')
                    ->where('bid', $return_order[$i]['order_box'])
                    ->field('box_area,box_store')
                    ->find();

                if($return_order[$i]['change_address'] != 2)
                {
                    $data[$j] = ['order_number' => $return_order[$i]['order_num'], 'time' => $return_order[$i]['order_arrive_time'], 'price' => $order_price[$j], 'sender_address' => $box[$j]['box_area'], 'sender' => $box[$j]['box_store'], 'receiver_address' => $address[$j]['address'], 'receiver_area' => $address[$j]['area'], 'receiver' => $address[$j]['receiver_name'], 'receiver_phone' => $address[$j]['receiver_phone'], 'distance' => $return_order[$i]['order_distance'], 'no_receiver' => 0,'change_address'=>$return_order[$i]['change_address']];
                    $j++;
                }
                else
                {
                    $data[$j] = ['order_number' => $return_order[$i]['order_num'], 'time' => $return_order[$i]['order_arrive_time'], 'price' => $order_price[$j], 'sender_address' => $box[$j]['box_area'], 'sender' => $box[$j]['box_store'], 'receiver_address' => $return_order[$i]['change_receiver_address'], 'receiver' => $return_order[$i]['change_receiver_name'], 'receiver_phone' => $return_order[$i]['change_receiver_phone'], 'distance' => $return_order[$i]['change_distance'],'change_address'=>$return_order[$i]['change_address'], 'no_receiver' => 0];
                }
            }

        }
        $data = ['orders'=>$data];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
}