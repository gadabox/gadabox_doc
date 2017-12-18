<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/11
 * Time: 13:38
 */
namespace app\index\controller;
require_once('Response.php');
use \think\Db;
use \think\Controller;
require_once ('Constants.php');

Class Showorder extends controller
    {
        public function showorder()
        {
            $data = file_get_contents('php://input');
            $data = json_decode($data,true);

            if($data['order_type'] == UNFINISHED)
            {
                $unfinish = $this->unFinishOrder($data['token']);
                $data = ['orders' => $unfinish];
                Response::returnApiSuccess(200,'订单信息接收成功',$data);
            }
            elseif($data['order_type'] == COMPLETED)
            {
                $finished = $this->completedOrder($data['token']);
                $data = ['orders' => $finished];
                Response::returnApiSuccess(200, '订单信息接收成功', $data);
            }
            elseif($data['order_type'] == SPECIAL)
            {
                $special = $this->specialOrder($data['token']);
                $data = ['orders' => $special];
                Response::returnApiSuccess(200, '订单信息接收成功', $data);
            }
        }
        public static function unFinishOrder($token)
            {
                $uid = Db::table('gada_token')
                    ->where('user_token',$token)
                    ->value('user_id');
                //未完成的订单
                //待收货
                $unfinish = Db::table('gada_order')
                    ->where([
                            'uid'=> $uid,
                            'order_type'=> UNFINISHED,
                           // 'order_status' <= 3
                            ])
                    ->order('order_status')
                    ->field('order_num,order_weight,order_distance,change_distance,order_create_time,first_start_time,order_arrive_time,order_status,courier,night_fee,first_arrive_time,goback_time,change_deliver_time,order_delivered_time')
                    ->select();
                //专门查询二次配送的下单时间和预计配送时间以及送达时间做替换。
                $second = Db::table('gada_order')
                    ->where([
                        'uid'=> $uid,
                        'order_type'=> UNFINISHED,
                        // 'order_status' <= 3
                    ])
                    ->order('order_status')
                    ->field('second_create_time,second_arrive_time,second_delivered_time,second_price')
                    ->select();
                $num = count($unfinish);

                for($i=0;$i<$num;$i++)
                {
                    if($unfinish[$i]['order_status'] <= 4)  //配送订单需要配送员的信息
                    {
                        $cid = $unfinish[$i]['courier'];
                        $wcourier = Db::table('gada_courier')
                            ->where('id_courier',$cid)
                            ->find();
                        $courier = ['cname' => $wcourier['cname'],'cphone' => $wcourier['cphone']];
                        $unfinish[$i]['courier'] = $courier;
                    }
                    else if($unfinish[$i]['order_status'] == 4)
                    {
                        $time = time();
                        $ctime = strtotime($unfinish[$i]['order_create_time']);
                        $duration = $time - $ctime;
                        $unfinish[$i]['order_duration_time'] = $duration;
                    }
                    else if($unfinish[$i]['order_status'] > 4)
                    {
                        //方便前端接收
                        $unfinish[$i]['first_start_time'] = $second[$i]['second_create_time'];
                        $unfinish[$i]['order_arrive_time'] = $second[$i]['second_arrive_time'];
                        $unfinish[$i]['first_arrive_time'] = $unfinish[$i]['order_delivered_time'];
                        $unfinish[$i]['order_price'] = $second[$i]['second_price'];
                        //$unfinish[$i]['order_delivered_time'] = $second[$i]['second_delivered_time'];
                        $cid = $unfinish[$i]['courier'];
                        $wcourier = Db::table('gada_courier')
                            ->where('id_courier',$cid)
                            ->find();
                        $courier = ['cname' => $wcourier['cname'],'cphone' => $wcourier['cphone']];
                        $unfinish[$i]['courier'] = $courier;
                    }
                    if(!empty($unfinish[$i]['change_distance']))
                    {
                        $unfinish[$i]['order_distance'] = $unfinish[$i]['change_distance'];
                    }
                }

                return $unfinish;
               /* //占用中
                $occupy = Db::table('gada_order')
                    ->where([
                            'uid'=>$uid,
                            'order_type'=>1,
                            'order_state'=>0
                            ])
                    ->find();*/
                //快递员信息
                /*$wcourier = Db::table('gada_courier')
                    ->where('id_courier',$wait['courier'] )
                    ->find();

            if(!empty($wait) && !empty($occupy))
            {
                $order_wait = ['order_num' => $wait['order_num'], 'weight' => $wait['order_weight'], 'distance' => $wait['order_distance'], 'price' => $wait['order_price'], 'ctime' => $wait['order_ctime'], 'extime' => $wait['order_extime'], 'cname' => $wcourier['cname'], 'cphone' => $wcourier['cphone'],'type' => $wait['order_type']];
                $order_occupy = ['order_num' => $occupy['order_num'], 'weight' => $occupy['order_weight'], 'ctime' => $occupy['order_ctime'],'type' => $occupy['order_type']];
                $data = ['wait' => $order_wait,'occupy' => $order_occupy];
                Response::returnApiSuccess(200,'订单信息接收成功',$data);
            }
            if(!empty($occupy) && empty($wait))
            {
                $order_occupy = ['order_num' => $occupy['order_num'], 'weight' => $occupy['order_weight'], 'ctime' => $occupy['order_ctime']];
                $data = ['occupy' => $order_occupy];
                Response::returnApiSuccess(200,'订单信息接收成功',$data);
            }
             if(empty($occupy) && !empty($wait))
             {
                 $order_wait = ['order_num' => $wait['order_num'], 'weight' => $wait['order_weight'], 'distance' => $wait['order_distance'], 'price' => $wait['order_price'], 'ctime' => $wait['order_ctime'], 'extime' => $wait['order_extime'], 'cname' => $wcourier['cname'], 'cphone' => $wcourier['cphone']];
                 $data = ['wait' => $order_wait];
                 Response::returnApiSuccess(200,'订单信息接收成功',$data);
             }*/
            }
            public static function completedOrder($token)
            {
                $uid = Db::table('gada_token')
                    ->where('user_token', $token)
                    ->value('user_id');
                //配送完成的
                $finished = Db::table('gada_order')
                    ->where([
                        'uid' => $uid,
                        'order_type' => COMPLETED,
                        //  'order_state'=>1
                    ])
                    ->order('order_over_time desc')
                    ->field('order_num,order_weight,order_distance,order_price,order_create_time,order_over_time,night_fee,order_status,courier,change_deliver_time,evaluate_time,goback_time,first_arrive_time,first_start_time,order_status')
                    ->select();
                $second = Db::table('gada_order')
                    ->where([
                        'uid' => $uid,
                        'order_type' => COMPLETED,
                        //  'order_state'=>1
                    ])
                    ->order('order_over_time desc')
                    ->field('second_price,second_create_time,order_delivered_time')
                    ->select();
                $num = count($finished);

                for ($i = 0; $i < $num; $i++) {

                    if(!empty($second[$i]['second_create_time']))
                    {
                        $finished[$i]['first_arrive_time'] = $second[$i]['order_delivered_time'];
                        $finished[$i]['first_start_time'] = $second[$i]['second_create_time'];
                    }
                    if (!empty($second[$i]['second_price'])) {


                        $finished[$i]['order_price'] = $second[$i]['second_price'];
                    }
                    if (!empty($finished[$i]['courier'])) {
                        $cid = $finished[$i]['courier'];
                        $dcourier = Db::table('gada_courier')
                            ->where('id_courier', $cid)
                            ->find();
                        $courier = ['cname' => $dcourier['cname'], 'cphone' => $dcourier['cphone']];
                        $finished[$i]['courier'] = $courier;
                    }
                    if(!empty($finished[$i]['evaluate_time']))
                    {
                        //已评论
                        $finished[$i]['is_evaluate'] = 1;
                    }
                    else
                    {
                        //未评论
                        $finished[$i]['is_evaluate'] = 0;
                    }
                    if($finished[$i]['order_status'] == TIMEOUT_TAKESTOCK)
                    {
                        //过夜订单展示总价
                        $finished[$i]['order_price'] = $finished[$i]['night_fee'] + $finished[$i]['order_price'] +$second[$i]['second_price'] ;
                        $finished[$i]['order_create_time'] = date("Y-m-d",strtotime($finished[$i]['order_over_time'])) . ' 08:00:00';
                    }
                    /* else
                     {
                         $ovtime = $finished[$i]['order_over_time'];
                         $ovtime = strtotime($ovtime);
                         $ctime = $finished[$i]['order_create_time'];
                         $ctime = strtotime($ctime);
                         $time = $ovtime - $ctime;
                         $finished[$i]['order_over_time'] = $time;
                     }*/
                }

                return $finished;
            }
        public static function specialOrder($token)
        {
            $uid = Db::table('gada_token')
                ->where('user_token', $token)
                ->value('user_id');
            $special = Db::table('gada_order')
                ->where('uid' , $uid)
                ->where('order_type', SPECIAL)
                ->where('order_status' ,'not in',[UN_USE_NOPAID,CANCEL_DISPATCH_NOPAID])
                ->order('order_create_time desc')
                ->field('order_num,order_weight,order_distance,order_price,order_create_time,order_over_time,order_delivered_time,goback_time,night_fee,order_status,courier,change_deliver_time,deposit_price,return_card,refund,goback_return_time')
                ->select();
            $num = count($special);
            for ($i = 0; $i < $num; $i++)
            {
                if($special[$i]['order_status'] == TIMEOUT ||$special[$i]['order_status'] == NO_RECEIVE_TIMEOUT)
                {
                    $time = date('Y-m-d');
                    $over_night_start_time = $time . ' 8:00:00';
                    $str_over_night_start_time = strtotime($over_night_start_time);
                    if(time() < $str_over_night_start_time)
                    {
                        $special[$i]['order_over_night_start_time'] = null;
                    }
                    else
                    {
                        $special[$i]['order_over_night_start_time'] = $over_night_start_time;
                    }
                }
                if (!empty($special[$i]['courier'])) {
                    $cid = $special[$i]['courier'];
                    $dcourier = Db::table('gada_courier')
                        ->where('id_courier', $cid)
                        ->find();
                    $courier = ['cname' => $dcourier['cname'], 'cphone' => $dcourier['cphone']];
                    $special[$i]['courier'] = $courier;
                }
                if(empty($special[$i]['order_price']))
                {
                    $special[$i]['order_price'] = 0;
                }
                //返还卡券的信息
                if(empty($special[$i]['return_card']))
                {
                    $special[$i]['return_card'] = '没使用卡券';
                }

            }

            return $special;
        }

        public function quickOrder()
        {
            $data = file_get_contents('php://input');
            $data = json_decode($data,true);
            $unfinish = $this->unFinishOrder($data['token']);
            $uid = Db::table('gada_token')
                ->where('user_token', $data['token'])
                ->value('user_id');
            $special =  $special = Db::table('gada_order')
                ->where('uid' , $uid)
                ->where('order_type', SPECIAL)
                ->where('order_status' ,'not in',[CURRENT,CANCEL_DISPATCH,UN_USE_NOPAID,CANCEL_DISPATCH_NOPAID,IS_READ_PAID,UN_USE_PAID])
                ->order('order_create_time desc')
                ->field('order_num,order_weight,order_distance,order_price,order_create_time,order_over_time,order_delivered_time,goback_time,night_fee,order_status,courier,change_deliver_time,deposit_price,return_card,refund,goback_return_time')
                ->select();
            $num = count($special);
            for ($i = 0; $i < $num; $i++)
            {
                if (!empty($special[$i]['courier'])) {
                    $cid = $special[$i]['courier'];
                    $dcourier = Db::table('gada_courier')
                        ->where('id_courier', $cid)
                        ->find();
                    $courier = ['cname' => $dcourier['cname'], 'cphone' => $dcourier['cphone']];
                    $special[$i]['courier'] = $courier;
                }
                if(empty($special[$i]['order_price']))
                {
                    $special[$i]['order_price'] = 0;
                }
                //返还卡券的信息
                if(empty($special[$i]['return_card']))
                {
                    $special[$i]['return_card'] = '没使用卡券';
                }
                //替换订单标识满足前端单字段识别，7：超时的订单(过夜订单) 8：未签收的订单 9：无人签收的超时订单
                if($special[$i]['order_status'] == TIMEOUT)
                {
                    $special[$i]['order_status'] = 7;
                    $time = date('Y-m-d');
                    $over_night_start_time = $time . ' 8:00:00';
                    $str_over_night_start_time = strtotime($over_night_start_time);
                    if(time() < $str_over_night_start_time)
                    {
                        $special[$i]['order_over_night_start_time'] = null;
                    }
                    else
                    {
                        $special[$i]['order_over_night_start_time'] = $over_night_start_time;
                    }
                }
                elseif($special[$i]['order_status'] == NO_RECEIVE)
                {
                    $special[$i]['order_status'] = 8;
                }
                else
                {
                    $special[$i]['order_status'] = 9;
                    $time = date('Y-m-d');
                    $over_night_start_time = $time . ' 8:00:00';
                    $str_over_night_start_time = strtotime($over_night_start_time);
                    if(time() < $str_over_night_start_time)
                    {
                        $special[$i]['order_over_night_start_time'] = null;
                    }
                    else
                    {
                        $special[$i]['order_over_night_start_time'] = $over_night_start_time;
                    }
                }
            }
            $orders = array_merge($unfinish,$special);
            $data = ['orders'=>$orders];
            Response::returnApiSuccess(200, '订单信息接收成功', $data);
        }




            //占用完成，已取出的
           /* $picked = Db::table('gada_order')
                ->where([
                    'uid'=>$uid,
                    'order_type'=>1,
                    'order_state'=>1
                ])
                ->find();
            //快递员信息
            $dcourier = Db::table('gada_courier')
                ->where('id_courier',$deliverde['courier'] )
                ->find();
            $order_deliverde = ['order_num' => $deliverde['order_num'],'weight' => $deliverde['order_weight'],'distance' => $deliverde['order_distance'],'price' => $deliverde['order_price'],'ctime' => $deliverde['order_ctime'],'cname'=>$dcourier['cname'] ,'cphone' => $dcourier['cphone']];
            $order_picked = ['order_num' => $picked['order_num'],'weight' => $picked['order_weight'],'ctime' => $picked['order_ctime'],'ovtime' => $picked['order_ovtime'],'price' => $picked['order_price']];
            $data = ['deliverde' =>$order_deliverde,'picked' => $order_picked];
            Response::returnApiSuccess(200,'订单信息接收成功',$data);*/

    }