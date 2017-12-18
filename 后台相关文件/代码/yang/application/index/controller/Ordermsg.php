<?php
/**
 * 订单信息类.
 * User: yang
 * Date: 2017/8/21
 * Time: 11:32
 */
namespace app\index\controller;
require_once('Response.php');
require_once ('Userinfo.php');
require_once ('Constants.php');
use \think\Db;
use \think\Controller;


Class Ordermsg extends controller
{
    public static function createOrder()
    {
        //（时间+10位随机数）
        $time = time();
        $time = substr($time,-6);
        $num = '';
        for ( $i = 0; $i < 3; $i++)
        {
            $n = rand(0,9);
            $num .= $n;
        }
        $order_num = $time . $num;
        return $order_num;
    }
    public function depositOrder()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $box_code = $data['box_code'];
        $bid = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        $is_deposit= Db::table('gada_order')
            ->where(['order_box' => $bid,'order_type' => SPECIAL,'order_status' => CURRENT])
            ->update(['order_type' => UNFINISHED,'order_status' => OCCUPY]);
        //锁上箱子
        Db::table('gada_box')
            ->where('bid',$bid)
            ->update(['is_lock'=>1,'is_close'=>1]);
        if($is_deposit == 1)
        {
            Response::returnApiOk(200,'寄存成功');
        }
        else
        {
            Response::returnApiOk(201,'寄存失败');
        }
    }
    public function showDepositMsg()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        //判定是在占用页面开箱取物（接收box_code）还是在占用订单详情页或者过夜开箱想取物（接收order_number）
        if(empty($data['order_number']))
        {
            //接收到了box_code
            $box_code = $data['box_code'];
            $order_status = '';
        }
        else
        {
            //接收到order_number
            $order_num = $data['order_number'];
            $box_code = Db::table('gada_box')
                ->join('gada_order','gada_box . bid = gada_order . order_box')
                ->where('order_num',$order_num)
                ->value('box_code');
            $order_status = Db::table('gada_order')
                ->where('order_num',$order_num)
                ->value('order_status');
        }
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token', "$token")
            ->value('user_id');
        $card =  Db::table('gada_card')
            ->where(['uid'=>$uid,'is_use'=>0,'expired'=>0])
            ->select();
        $card_num = count($card);
        $bid = Db::table('gada_box')
            ->where('box_code',$box_code)
            ->value('bid');
        if($order_status == TIMEOUT || $order_status == NO_RECEIVE_TIMEOUT)
        {
            //过夜后订单按当日开始工作时间开始计费
            $order_create_time = date("Y-m-d") . '8:00:00';
        }
        else
        {
        $order_create_time= Db::table('gada_order')
            ->where(['order_box' => $bid,'order_type' => UNFINISHED,'order_status' => OCCUPY])
            ->value('order_create_time');
        }
        $order_create_time = strtotime($order_create_time) * 1000;
        $current_time = time() *1000;
        $time = time();
        $time = ceil(($time - $order_create_time/1000)/3600);
        $hourly_rate = 1;
        $price = $time * $hourly_rate;
        $price = (string)$price;
        //根据支付金额推荐使用的卡券
        $recommend_card = Card::recommendCard($card_num,$uid,$price);
        $data = ['current_time'=>$current_time,'order_create_time'=>$order_create_time,'price'=>$price,'card_num'=>$card_num,'card'=>$recommend_card,'box_code'=>$box_code];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function finishLock()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        //$token = $data['token'];
        if(!empty($data['order_number'])) {
            $order_num = $data['order_number'];
            //判定是否有隐藏订单（此时有order_num，但并非隐藏订单订单号）
            $bid = Db::table('gada_order')
                ->where('order_num', $order_num)
                ->value('order_box');
            $is_close = Db::table('gada_box')
                ->where('bid', $bid)
                ->value('is_close');
            $is_special = Db::table('gada_order')
                //->join('gada_order','gada_box . bid = gada_order . order_box')
                ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                ->value('order_num');
            if ($is_close == 1)
            {
                if (!empty($is_special)) {
                    //有隐藏订单
                    $is_invalid = Db::table('gada_order')
                        //->join('gada_order','gada_box . bid = gada_order . order_box')
                        ->where(['order_num' => $is_special])
                        ->update(['order_status' => UN_USE_NOPAID]);
                    Db::table('gada_box')
                        ->where('bid', $bid)
                        ->update(['is_lock' => 1]);
                }
                else
                {
                    //下单完成后取消使用的锁箱

                    $bid = Db::table('gada_order')
                        ->where('order_num', $order_num)
                        ->value('order_box');
                    $is_invalid = Db::table('gada_box')
                        ->where('bid', $bid)
                        ->update(['is_lock' => 1]);
                }
            } //接收箱子端提示判断是否盖盖。
            else
            {
                $is_invalid = '';
            }
        }
        else {
            //未下单取消使用的锁箱
            $box_code = $data['box_code'];
            $bid = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->value('bid');
            $is_close = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->value('is_close');
            if ($is_close == 1) {
                $is_invalid = Db::table('gada_order')
                    //->join('gada_order','gada_box . bid = gada_order . order_box')
                    ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                    ->update(['order_status' => UN_USE_NOPAID]);
                Db::table('gada_box')
                    ->where('bid', $bid)
                    ->update(['is_lock' => 1]);
            }
            else
            {
                $is_invalid = '';
            }
        }
            //变换订单类型使之无效化
        if($is_invalid == 1 && $is_close ==1)
        {
            Db::table('gada_box')
                ->where('bid', $bid)
                ->update(['is_user' => '']);
            Response::returnApiOk(200, '锁箱成功');
        }
        else
        {
            Response::returnApiError(201,'锁箱失败,可能是没盖好');
        }
    }
    public function showDispatchMsg()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $change_deliver = $data['change_deliver'];

            $address_id = $data['address_id'];
            //通过$change_deliver,判定是否是转配送订单。
            if (empty($data['order_number'])) {
                $box_code = $data['box_code'];
            } else {
                $order_num = $data['order_number'];
                $box_code = Db::table('gada_box')
                    ->join('gada_order', 'gada_box . bid = gada_order . order_box')
                    ->where('order_num', $order_num)
                    ->value('box_code');
            }
            $is_close = Db::table('gada_box')
                ->where('box_code', $box_code)
                ->value('is_close');
            if ($is_close == 0) {
                Response::returnApiError(201, '请确认是否盖好箱盖');
            } else {
                //执行锁箱
                Db::table('gada_box')
                    ->where('box_code', $box_code)
                    ->update(['is_lock' => 1]);
                if ($change_deliver == 1) {
                    //转配送订单，前端没box_code
                    $bid = Db::table('gada_box')
                        ->where('box_code', $box_code)
                        ->value('bid');
                    if (empty($order_num)) {
                        //正常转配送没有订单号，过夜超时转配送有订单号
                        $order_num = Db::table('gada_order')
                            ->where(['order_type' => UNFINISHED, 'order_status' => OCCUPY, 'order_box' => $bid])
                            ->value('order_num');
                    }
                    $weight = Db::table('gada_order')
                        ->where(['order_num' => $order_num])
                        ->value('order_weight');
                    //查询订单是否是初始配送地址
                    $change_address = Db::table('gada_order')
                        ->where(['order_num' => $order_num])
                        ->value('change_address');

                } else {
                    //正常配送订单,前端有box_code

                    $bid = Db::table('gada_box')
                        ->where('box_code', $box_code)
                        ->value('bid');
                    $weight = Db::table('gada_order')
                        ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                        ->value('order_weight');
                    $order_num = Db::table('gada_order')
                        ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                        ->value('order_num');
                    //查询订单是否是初始配送地址
                    $change_address = Db::table('gada_order')
                        ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                        ->value('change_address');
                }
                //查询箱子的初始位置
                $origin = Db::table('gada_box')
                    ->where('bid', $bid)
                    ->field('longitude,latitude')
                    ->find();
                $origin = $origin['latitude'] . ',' . $origin['longitude'];
                $uid = Db::table('gada_token')
                    ->where('user_token', $token)
                    ->value('user_id');

                //预计送达时间
                $et = date("H:i ", strtotime("+30 min"));

                $card_id = Db::table('gada_card')
                    ->where(['uid' => $uid, 'is_use' => 0, 'expired' => 0])
                    ->field('card_id')
                    ->select();
                $card_num = count($card_id);

                if (empty($address_id)) {

                    //首次加载支付页面
                    $address = Db::table('gada_address')
                        ->where(['user_id' => $uid])
                        ->order('is_default desc,address_id desc')
                        ->find();
                    if(!empty($address)) {
                        $receiver_address = $address['area'] . $address['address'];
                        $address['receiver_address'] = $receiver_address;

                        //预计算出距离以及金额
                        $destination = $address['address_latitude'] . ',' . $address['address_longitude'];    //配送地址的经纬度
                        $amap = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin=' . $origin . '&destination=' . $destination);
                        $amap = json_decode($amap, true);
                        $distance = $amap['data']['paths'][0]['distance'] / 1000;
                        //保留两位小数方便观看
                        $distance = number_format($distance, 2);

                        //计算价格
                        $price = $this->cacheComputations($weight, $distance);
                        //根据支付金额推荐使用的卡券
                        $recommend_card = Card::recommendCard($card_num, $uid, $price);

                        if ($change_deliver == 0) {
                            Db::table('gada_order')
                                ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                                ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                        } else {
                            if (empty($data['order_number'])) {
                                Db::table('gada_order')
                                    ->where(['order_box' => $bid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                    ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                            } else {
                                Db::table('gada_order')
                                    ->where(['order_num' => $order_num])
                                    ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                            }
                        }
                        $data = ['address' => $address, 'order_weight' => $weight, 'order_distance' => $distance, 'order_arrive_time' => $et, 'price' => $price, 'card_num' => $card_num, 'card' => $recommend_card, 'box_code' => $box_code, 'change_address' => $change_address];
                    }
                    else
                    {
                        $data = ['address' => null, 'order_weight' => $weight, 'order_distance' => 0, 'order_arrive_time' => $et, 'price' => 0, 'card_num' => $card_num, 'card' => null, 'box_code' => $box_code, 'change_address' => $change_address];
                    }
                    Response::returnApiSuccess(200, '反馈成功', $data);

                } else {
                    //二次选择或者编辑收货地址后返回支付页面
                    $address = Db::table('gada_address')
                        ->where(['address_id' => $address_id])
                        ->find();
                    $receiver_address = $address['area'] . $address['address'];
                    $address['receiver_address'] = $receiver_address;

                    //预计算出距离以及金额
                    $destination = $address['address_latitude'] . ',' . $address['address_longitude'];    //配送地址的经纬度
                    $amap = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin=' . $origin . '&destination=' . $destination);
                    $amap = json_decode($amap, true);
                    $distance = $amap['data']['paths'][0]['distance'] / 1000;
                    //保留两位小数方便观看
                    $distance = number_format($distance, 2);
                    //计算价格
                    $price = $this->cacheComputations($weight, $distance);
                    //根据支付金额推荐使用的卡券
                    $recommend_card = Card::recommendCard($card_num, $uid, $price);
                    if ($change_deliver == 0) {
                        Db::table('gada_order')
                            ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => CURRENT])
                            ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                    } else {
                        if (empty($data['order_number'])) {
                            Db::table('gada_order')
                                ->where(['order_box' => $bid, 'order_type' => UNFINISHED, 'order_status' => OCCUPY])
                                ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                        } else {
                            Db::table('gada_order')
                                ->where(['order_num' => $order_num])
                                ->update(['cache_price' => $price, 'order_arrive_time' => $et, 'cache_distance' => $distance]);
                        }
                    }
                    $data = ['address' => $address, 'order_weight' => $weight, 'order_distance' => $distance, 'order_arrive_time' => $et, 'price' => $price, 'card_num' => $card_num, 'card' => $recommend_card, 'box_code' => $box_code, 'change_address' => $change_address];
                    Response::returnApiSuccess(200, '反馈成功', $data);
                }
            }

    }
    public function secondDispatch()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $order_num = $data['order_number'];
        $token = $data['token'];
        $box_code = Db::table('gada_box')
            ->join('gada_order', 'gada_box . bid = gada_order . order_box')
            ->where('order_num', $order_num)
            ->value('box_code');
        $bid = Db::table('gada_box')
            ->where('box_code', $box_code)
            ->value('bid');
        $weight = Db::table('gada_order')
           // ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
               ->where('order_num',$order_num)
                ->value('order_weight');
        //测试文件模拟一个距离
        $distance = Db::table('gada_order')
            //->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
            ->where('order_num',$order_num)
            ->value('order_distance');
        $uid = Db::table('gada_token')
            ->where('user_token', $token)
            ->value('user_id');
        //预计送达时间
        $et = date("H:i ", strtotime("+30 min"));
        $card_id = Db::table('gada_card')
            ->where(['uid'=>$uid,'is_use'=>0,'expired'=>0])
            ->field('card_id')
            ->select();
        $card_num = count($card_id);
        $current_time = date('Y-m-d H:i:s');
            //首次加载支付页面
            $order_msg = Db::table('gada_order')
                ->where(['order_num' => $order_num])
                ->field('receiver_name,receiver_phone,address,area,order_price')
                ->find();
            $receiver_address = $order_msg['area'] . $order_msg['address'];
            $order_msg['receiver_address'] = $receiver_address;
            //二次配送半价
            $second_price = $order_msg['order_price'] * 0.5;
        //根据支付金额推荐使用的卡券
        $recommend_card = Card::recommendCard($card_num,$uid,$second_price);

            Db::table('gada_order')
                ->where(['order_box' => $bid, 'order_type' => SPECIAL, 'order_status' => NO_RECEIVE])
                ->update([ 'second_arrive_time' => $et]);
            $order_msg['address'] = ['receiver_address'=>$order_msg['address'],'receiver_name'=>$order_msg['receiver_name'],'receiver_phone'=>$order_msg['receiver_phone']];
            $data = ['address' => $order_msg['address'], 'order_weight' => $weight, 'order_distance' => $distance, 'order_arrive_time' => $et, 'price' => $second_price, 'card_num' => $card_num, 'box_code' => $box_code,'card'=>$recommend_card];
            Response::returnApiSuccess(200, '反馈成功', $data);
    }
    public function orderDetail()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_num'];

        //查询时间节点
        $status_time = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->field('type0_status0_time,type0_status1_time,type0_status2_time,order_over_time,second_create_time,second_delivered_time')
            ->find();

        //查询是否为待修改地址订单
        $change_address = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->value('change_address');

        //查询预计送达时间以及快递的相关信息
        $msg = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->field('order_dispatch_time,courier,address,area,receiver_name,receiver_phone,change_receiver_name,change_receiver_phone,change_receiver_address')
            ->find();
        //截取用户选择的预计时间的时间部分
        $dispatch_time = trim($msg['order_dispatch_time']);
        $temp=array('1','2','3','4','5','6','7','8','9','0',':');
        $result='';
        for($i=0;$i<strlen($dispatch_time);$i++){
            if(in_array($dispatch_time[$i],$temp)){
                $result.=$dispatch_time[$i];
            }
        }
        //将处理完的result值赋予到预计送达的时间变量中(是配送时间区段时，只取最大时间)
        $order_dispatch_time = substr($result,-5);
        //判断节点数目
        if(empty($status_time['type0_status1_time']))
        {
            $n = PAID;
        }
        elseif(empty($status_time['type0_status2_time']))
        {
            $n = PUSHED;
        }
        elseif(empty($status_time['order_over_time']))
        {
            $n = DISPATCH;
        }
        elseif(empty($status_time['second_create_time']))
        {
            $n = 4;  //$n = RECEIVED;代表常量数冲突
        }
        elseif(empty($status_time['second_delivered_time']))
        {
            $n = SECOND;
        }
        else
        {
            $n = SECONDED;
        }
        //$n = count($status_time);
        switch($n)
        {
            //仅处于刚下单状态
            case PAID:
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];
                //$time = ['order_time'=>$order_time,'status'=>'已支付'];
                $order_status = '已支付';
                //下单的时间
                $order_dispatch_time = $status_time['type0_status0_time'];
                break;
            //服务器已推送给快递
            case PUSHED:
                $order_time[] = ['order_moment' => $status_time['type0_status1_time'],'status'=>'已接单'];
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];

                //$get_order_time = $status_time['type0_status1_time'];
                //$time = ['order_time'=>$order_time,'get_order_time'=>$get_order_time,'status'=>'已接单'];
                $order_status = '已接单';
                //$order_dispatch_time = $order_dispatch_time;
                break;
            //快递员已提箱正在配送中
            case DISPATCH:
                $order_time[] = ['order_moment' => $status_time['type0_status2_time'],'status'=>'配送中'];
                $order_time[] = ['order_moment' => $status_time['type0_status1_time'],'status'=>'已接单'];
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];
                //$order_time = $status_time['type0_status0_time'];
                //$get_order_time = $status_time['type0_status1_time'];
                //$delivered_order_time = $status_time['type0_status2_time'];
                //$time = ['order_time'=>$order_time,'get_order_time'=>$get_order_time,'delivered_order_time'=>$delivered_order_time,'status'=>'配送中'];
                $order_status = '配送中';
                //$order_dispatch_time = $order_dispatch_time;
                break;
            //用户已经签收的状态
            case 4:
                $received_time = substr($status_time['order_over_time'],11,5);
                $order_time[] = ['order_moment' => $received_time,'status'=>'已签收'];
                $order_time[] = ['order_moment' => $status_time['type0_status2_time'],'status'=>'配送中'];
                $order_time[] = ['order_moment' => $status_time['type0_status1_time'],'status'=>'已接单'];
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];
                //$order_time = $status_time['type0_status0_time'];
                //接单的时间
                //$get_order_time = $status_time['type0_status1_time'];
                //开始配送的时间
                //$delivered_order_time = $status_time['type0_status2_time'];
                //签收的时间
                //$order_over_time = $status_time['order_over_time'];
                //$time = ['order_time'=>$order_time,'get_order_time'=>$get_order_time,'delivered_order_time'=>$delivered_order_time,'order_over_time'=>$order_over_time,'status'=>'已签收'];
                $order_status = '已签收';
                $order_dispatch_time = $status_time['order_over_time'];
                break;
            case SECOND:
                $order_time[] = ['order_moment' => $status_time['second_create_time'],'status'=>'配送中'];
                $order_time[] = ['order_moment' => $status_time['type0_status1_time'],'status'=>'已接单'];
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];
                //$order_time = $status_time['type0_status0_time'];
                //$get_order_time = $status_time['type0_status1_time'];
                //$delivered_order_time = $status_time['type0_status2_time'];
                //$time = ['order_time'=>$order_time,'get_order_time'=>$get_order_time,'delivered_order_time'=>$delivered_order_time,'status'=>'配送中'];
                $order_status = '配送中';
                break;
            case SECONDED:
                $order_time[] = ['order_moment' => $status_time['type0_status2_time'],'status'=>'配送中'];
                $order_time[] = ['order_moment' => $status_time['type0_status1_time'],'status'=>'已接单'];
                $order_time[] = ['order_moment' => $status_time['type0_status0_time'],'status'=>'已支付'];
                //$order_time = $status_time['type0_status0_time'];
                //$get_order_time = $status_time['type0_status1_time'];
                //$delivered_order_time = $status_time['type0_status2_time'];
                //$time = ['order_time'=>$order_time,'get_order_time'=>$get_order_time,'delivered_order_time'=>$delivered_order_time,'status'=>'配送中'];
                $order_status = '已签收';
                $order_dispatch_time = $status_time['second_delivered_time'];
                break;
        }
        //查询快递信息
        $courier = Db::table('gada_courier')
            ->where('id_courier',$msg['courier'])
            ->field('cname,cphone')
            ->find();
        //接收人的信息
        /*$add = Db::table('gada_address')
            ->where('address_id',$msg['order_address_id'])
            ->find();*/
        //拼地址给前端用
        if($change_address !=2) {
            $receiver_address = $msg['area'] . $msg['address'];
            $address = ['receiver_name' => $msg['receiver_name'], 'receiver_phone' => $msg['receiver_phone'], 'area' => $msg['area'], 'address' => $msg['address'], 'receiver_address' => $receiver_address];
        }
        else
        {
            $receiver_address = $msg['change_receiver_address'];
            $address = ['receiver_name' => $msg['change_receiver_name'], 'receiver_phone' => $msg['change_receiver_phone'], 'receiver_address' => $receiver_address];

        }
        $data = ['order_dispatch_time'=>$order_dispatch_time,'courier'=>$courier,'address'=>$address,'delivered_time'=>$msg['order_dispatch_time'],'delivery_moment'=>$order_time,'order_status'=>$order_status,'change_address'=>$change_address];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function orderCancel()
    {
        //取消配送类订单
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $order_status = Db::table('gada_order')
            ->where(['order_type'=>UNFINISHED,'order_num'=>$order_num])
            ->value('order_status');
        if($order_status>1)
        {
            //不能够取消当前订单
            Response::returnApiError(201,'该订单当前状态无法取消');
        }
        else
        {
            $now = date("H:i");

            $order_msg = Db::table('gada_order')
                ->where('order_num',$order_num)
                ->find();
            $order_create_time = strtotime($order_msg['order_create_time']);
            $current_time = time();

            //默认送达时间随时取消均不扣费，调整时间的话以预计送达区间为基准，进入后则开始扣费
            $expected_time = substr($order_msg['order_dispatch_time'],0,12);
            if($expected_time == '立即配送')
            {
                //该类型订单不进行任何扣费
                $price=0;
                $card = $this->returnCard($order_msg);
                //刨去助力卡部分，实付金额
                $refund = $order_msg['order_price'] - $card['card_value'];
                $this->refunds($order_msg,$refund,$price);
                //取消成功，移入特殊订单 ，记录取消时间
                $current = date('Y-m-d H:i:s');
                Db::table('gada_order')
                    ->where('order_num',$order_num)
                    ->update(['order_type'=>SPECIAL,'order_status'=>CANCEL_DISPATCH_NOPAID,'order_over_time'=>$current]);
            }
            else
            {
                $expected_time = substr($order_msg['order_dispatch_time'],0,5);
                if(strtotime($now)<$expected_time)
                {
                    //在预计时间开始之前取消也不进行扣费
                    $price = 0;
                    $card = $this->returnCard($order_msg);
                    //刨去助力卡部分，实付金额
                    $refund = $order_msg['order_price'] - $card['card_value'];
                    $this->refunds($order_msg,$refund,$price);
                    //取消成功，移入特殊订单 ，记录取消时间
                    $current = date('Y-m-d H:i:s');
                    Db::table('gada_order')
                        ->where('order_num',$order_num)
                        ->update(['order_type'=>SPECIAL,'order_status'=>CANCEL_DISPATCH_NOPAID,'order_over_time'=>$current]);
                }
                else
                {
                    //成功取消当前订单，并进行了相关的占用扣费

                    //取出的字符串转化成数组格式

                    $order_price = $order_msg['order_price'];


                    $hourly_rate = 1;
                    //订单实际因占用产生的费用
                    $price = $hourly_rate * ceil(($current_time - $order_create_time) / 3600);
                    //$order_time = date("H:i:s",($current_time - $order_create_time));

                    //退还使用的助力卡(被转换的内容无论有无值都会默认有值)
                    $card = $this->returnCard($order_msg);


                    //扣除相关金额
                    //刨去助力卡部分，实付金额
                    $refund = $order_price - $card['card_value'];
                    $this->refunds($order_msg,$refund,$price);



                    //取消成功，移入特殊订单 ，记录取消时间
                    $current = date('Y-m-d H:i:s');
                    Db::table('gada_order')
                        ->where('order_num',$order_num)
                        ->update(['order_type'=>SPECIAL,'order_status'=>CANCEL_DISPATCH,'order_over_time'=>$current]);

                    //若返回金额为负，直接不展示返还金
                    $refund -=$price;
                    if($refund < 0)
                    {
                        $refund = 0;
                    }

                }
            }
            //打开箱子
            $bid = $order_msg['order_box'];
            $this->create_new_order($bid,$uid);
            //配合前端返回时间
            $order_create_time = $order_create_time *1000;
            $current_time = $current_time *1000;
            $data = ['order_create_time'=>$order_create_time,'current_time'=>$current_time,'price'=>$price,'return_card'=>$card['return_card_count'],'refund'=>$refund];
            Response::returnApiSuccess(200,'取消成功',$data);
        }

    }
    public function returnCard($order_msg)
    {
        //退卡方法
        $use_card = explode(',',$order_msg['use_card']);
        if(!empty($order_msg['use_card']))
        {
            $n = count($use_card);
            if($n == 1)
            {
                //展示部分
                $card_value = Db::table('gada_card')
                    ->where('card_id',$use_card[0])
                    ->value('card_value');
                $card = $card_value .'元助力卡一张';
                //实际退还记录部分
                Db::table('gada_card')
                    ->where('card_id',$use_card[0])
                    ->update(['is_use'=>0]);
            }
            else
            {
                //展示部分
                $card = $n . '张助力卡';
                //实际退还记录部分
                $card_value = 0;
                for($i=0;$i<$n;$i++)
                {
                    //记录一下卡的价值
                    $return_card = Db::table('gada_card')
                        ->where('card_id',$use_card[$i])
                        ->value('card_value');
                    $card_value += $return_card;
                    //退还卡
                    Db::table('gada_card')
                        ->where('card_id',$use_card[$i])
                        ->update(['is_use'=>0]);
                }
            }
        }
        else
        {
            $card_value = 0;
            $card = '没有使用助力卡';
        }
        $card = ['card_value'=>$card_value,'return_card_count'=>$card];
        return $card;
    }
    public function create_new_order($bid,$uid)
    {
        //处理订单结束后开锁并再生成隐藏订单，用监督是否会盖箱锁箱
        Db::table('gada_box')
            ->where('bid',$bid)
            ->update(['is_close'=>0,'is_lock'=>0]);
        //生成隐藏新订单
        $order_num = Ordermsg::createorder();
        //生成特殊临时订单
        Db::table('gada_order')
            ->insert(['uid' => $uid, 'order_num' => $order_num, 'order_box' => $bid]);
    }
    public function refunds($order_msg,$refund,$price)
    {
        //$price = 仍要扣除的占用费
        //退款方法
        $balance = Db::table('gada_balance')
            ->where('uid', $order_msg['uid'])
            ->find();
        if($price == 0)
        {
            //不进行扣费只进行退款
            if ($refund > 0)
            {
                //仍有金额可退
                //记录交易明细
                $current_balance = $balance['amount'] + $balance['reserves'] + $refund;
                //保留两位小数方便观看
                $current_balance = number_format($current_balance, 2);
                Db::table('gada_transact')
                    //->where('user_id',$uid)
                    ->insert(['user_id' => $order_msg['uid'], 'transact_content' => '退还金额', 'transact_detail' => '+' . $refund, 'current_balance' => $current_balance]);
                Db::table('gada_order')
                    ->where('order_num', $order_msg['order_num'])
                    ->update(['deposit_price' => $price, 'refund' => $refund]);
                $balance['reserves'] += $refund;
                Db::table('gada_balance')
                    ->where('uid', $order_msg['uid'])
                    ->update($balance);
            }
            else
            {
                //无金额可退

            }
        }
        else {
            //进行扣费处理

            if ($refund > 0) {
                //助力卡不够，仍缴纳了余额.在返还余额中进行占用费的扣除
                $refund = $refund - $price;
                //记录交易明细
                $current_balance = $balance['amount'] + $balance['reserves'] + $refund;
                //保留两位小数方便观看
                $current_balance = number_format($current_balance, 2);
                Db::table('gada_transact')
                    //->where('user_id',$uid)
                    ->insert(['user_id' => $order_msg['uid'], 'transact_content' => '退还金额', 'transact_detail' => '+' . $refund, 'current_balance' => $current_balance]);
                Db::table('gada_order')
                    ->where('order_num', $order_msg['order_num'])
                    ->update(['deposit_price' => $price, 'refund' => $refund]);
            }
            else {
                //助力卡价值刚好够,占用金额另外扣除
                $refund = 0 - $price;
                //记录交易明细
                $current_balance = $balance['amount'] + $balance['reserves'] + $refund;
                //保留两位小数方便观看
                $current_balance = number_format($current_balance, 2);
                Db::table('gada_transact')
                    //->where('user_id',$uid)
                    ->insert(['user_id' => $order_msg['uid'], 'transact_content' => '取消配送扣除金额', 'transact_detail' => $refund, 'current_balance' => $current_balance]);
                Db::table('gada_order')
                    ->where('order_num', $order_msg['order_num'])
                    ->update(['refund' => $refund]);
            }
            //付费情况（两个账户，那个有钱扣哪个）
            if($balance['reserves'] ==0)
            {
                //冻结账户为空，即直接扣除可动账户
                $amount = $balance['amount'] + $refund;
                Db::table('gada_balance')
                    ->where('uid', $order_msg['uid'])
                    ->update(['amount'=>$amount]);

            }
            elseif($balance['reserves'] !=0)
            {
                //冻结账户有余额，直接扣除即可（存在逻辑漏洞）
                $reserves = $balance['reserves'] + $refund;
                if($reserves >= 0) {
                    //必须保证在不确定余额账户是否够支付之前，不能出现冻结账户中余额扣为负数
                    Db::table('gada_balance')
                        ->where('uid', $order_msg['uid'])
                        ->update(['reserves' => $reserves]);
                }
                else //冻结账户余额不够扣除占用费用，就用可动账户扣除，无论是否够用
                {
                    $amount = $balance['amount'] + $refund;
                    Db::table('gada_balance')
                        ->where('uid', $order_msg['uid'])
                        ->update(['amount'=>$amount]);
                }
            }
        }
    }
    public function receive()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        //开箱并释放箱子的使用用户，但添加上快递持有信息
        $order_msg = Db::table('gada_order')
            //->join('gada_order','gada_box . bid = gada_order . order_box')
            ->where('order_num',$order_num)
            ->field('order_box,courier,order_price,change_deliver_time,second_create_time')
            ->find();
        //订单号绑定箱子来确保快递是否有未归还的嘎哒箱
        $is_free = Db::table('gada_box')
            ->where('bid',$order_msg['order_box'])
            ->update(['is_user'=>'','is_lock'=>0,'is_close'=>0,'is_courier'=>$order_msg['courier'],'order_number'=>$order_num]);
        $order_over_time = date("Y-m-d H:i:s");
       // $over_time = date("Y-m-d");
        if(empty($order_msg['change_deliver_time']))
        {
            if (empty($order_msg['second_create_time']))
            {
                $is_receive = Db::table('gada_order')
                    ->where(['order_num' => $order_num, 'uid' => $uid])
                    ->update(['order_type' => COMPLETED, 'order_status' => RECEIVED, 'order_over_time' => $order_over_time, 'order_current_time' => $order_over_time, 'goback_time' => $order_over_time]);
            }
            else
            {
                $is_receive = Db::table('gada_order')
                    ->where(['order_num' => $order_num, 'uid' => $uid])
                    ->update(['order_type' => COMPLETED, 'order_status' => SECOND_RECEIVED, 'order_over_time' => $order_over_time, 'order_current_time' => $order_over_time, 'goback_time' => $order_over_time]);
            }
        }
        else {
            if (empty($order_msg['second_create_time'])) {
                $is_receive = Db::table('gada_order')
                    ->where(['order_num' => $order_num, 'uid' => $uid])
                    ->update(['order_type' => COMPLETED, 'order_status' => CHANGE, 'order_over_time' => $order_over_time, 'order_current_time' => $order_over_time, 'goback_time' => $order_over_time]);
            }
            else
            {
                $is_receive = Db::table('gada_order')
                    ->where(['order_num' => $order_num, 'uid' => $uid])
                    ->update(['order_type' => COMPLETED, 'order_status' => SECOND_RECEIVED, 'order_over_time' => $order_over_time, 'order_current_time' => $order_over_time, 'goback_time' => $order_over_time]);
            }
        }

        if($is_free ==1 && $is_receive ==1)
        {
            //增加积分
            Db::table('gada_score')
                ->where('user_id',$uid)
                ->insert(['score_content'=>'完成配送订单','user_id'=>$uid,'score_detail'=>'+1']);
            $score = Db::table('gada_user')
                ->where('user_id',$uid)
                ->value('score');
            $score = $score + 1;
            Db::table('gada_user')
                ->where('user_id',$uid)
                ->update(['score'=>$score]);
            Test::a();

            Response::returnApiOk(200,'签收成功');
        }
    }
    public function changeAddress()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $receiver_name = $data['new_receiver_name'];
        $receiver_phone = $data['new_receiver_phone'];
        $change_address = $data['new_address'];
        //查询订单的状态
        $order_msg = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->find();
        //收货人信息若是空则直接沿用原先收货人信息
        if(empty($receiver_name))
        {
            $receiver_name = $order_msg['receiver_name'];
        }
        if(empty($receiver_phone))
        {
            $receiver_phone = $order_msg['receiver_phone'];
        }
        $money = Db::table('gada_balance')
            ->where('uid', $order_msg['uid'])
            ->find();
        $order_amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address=' . $order_msg['area'] . $order_msg['address']);
        $order_amap = json_decode($order_amap, true);
        //提取原订单地址的配送坐标
        $origin = $order_amap['geocodes'][0]['location'];   //原订单配送地址经纬度
        //配送地址信息在高德地图API进行查询比对，是否可用
        $amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address=' . $change_address);
        $amap = json_decode($amap, true);
        //判定定位等级是否达标
        $level = $amap['geocodes'][0]['level'];
        //提取新地址的配送坐标
        $str=explode(",",$amap['geocodes'][0]['location']);
        $address_longidute = $str[(count($str)-1)];
        $address_latiude = $str[0];
        if($order_msg['change_address'] ==1) {
            if ($level == '村庄' || $level == '热点商圈' || $level == '兴趣点' || $level == '门牌号' || $level == '单元号' || $level == '道路' || $level == '道路交叉路口' || $level == '公交站台、地铁站') {
                $destination = $amap['geocodes'][0]['location'];    //修改的配送地址的经纬度
                if ($order_msg['order_status'] == PAID || $order_msg['order_status'] == PUSHED)
                {
                    //不在配送途中
                    //预计算出距离以及金额
                    $change_amap = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin=' . $origin . '&destination=' . $destination);
                    $change_amap = json_decode($change_amap, true);
                    $distance = $change_amap['data']['paths'][0]['distance'] / 1000;
                    //保留两位小数方便观看
                    $distance = number_format($distance, 2);
                    //计算价格
                    $price = $this->cacheComputations($order_msg['weight'], $distance);
                    $payment = $price - $order_msg['order_price'];  //大于0少补的金额，小于0是多退的金额

                    Db::table('gada_order')
                        ->where('order_num', $order_num)
                        ->update(['cache_receiver' => $receiver_name, 'cache_phone' => $receiver_phone, 'cache_address' => $change_address, 'cache_distance' => $distance, 'cache_price' => $payment]);

                    //统计可用卡数量
                    $card_num = Db::table('gada_card')
                        ->where(['uid' => $order_msg['uid'], 'is_use' => 0, 'expired' => 0])
                        ->select();
                    $card_num = count($card_num);
                    //推荐用卡
                    $recommend_card = Card::recommendCard($card_num, $order_msg['uid'], $price);


                    if ($payment < 0)
                    {
                        //多退的情况不允许使用卡券（满足前端展示需求）
                        $card_num = 0;
                        //多退的金额
                        $refund = abs($payment);
                        $reserves = $money['reserves'] + $refund;
                        Db::table('gada_balance')
                            ->where('uid', $order_msg['uid'])
                            ->update(['reserves' => $reserves]);
                        $current_balance = $money['amount'] + $reserves;
                        //多退的金额记录
                        Db::table('gada_transact')
                            ->insert(['user_id' => $order_msg['uid'], 'transact_content' => '更改配送服务', 'transact_detail' => '+' . $refund, 'current_balance' => $current_balance]);
                        //更新订单实际总金额和配送距离


                        Db::table('gada_order')
                            ->where('order_num', $order_num)
                            ->update(['order_price' => $price, 'change_distance' => $distance, 'change_address' => 2,'change_receiver_name'=>$receiver_name,'change_receiver_phone'=>$receiver_phone,'change_receiver_address'=>$change_address]);


                        Test::courierNotice(14,'用户已完成更改地址，请查看新的配送地点',$order_num);
                        //记录订单地址已修改的信息（长久保存的消息展示用）
                        Db::table('gada_change_address_log')
                            ->insert(['order_number' => $order_num, 'change_address' => 2, 'uid' => $order_msg['uid']]);
                    }
                    elseif ($payment == 0)
                    {
                        //多退的情况不允许使用卡券（满足前端展示需求）
                        $card_num = 0;
                        //更新订单实际总金额和配送距离
                        Db::table('gada_order')
                            ->where('order_num', $order_num)
                            ->update(['order_price' => $price, 'change_distance' => $distance, 'change_address' => 2,'change_receiver_name'=>$receiver_name,'change_receiver_phone'=>$receiver_phone,'change_receiver_address'=>$change_address]);
                        Test::courierNotice(14,'用户已完成更改地址，请查看新的配送地点',$order_num);
                        //记录订单地址已修改的信息（长久保存的消息展示用）
                        Db::table('gada_change_address_log')
                            ->insert(['order_number' => $order_num, 'change_address' => 2, 'uid' => $order_msg['uid']]);
                    }


                    $data = ['price' => $payment, 'card_num' => $card_num, 'card' => $recommend_card, 'distance' => $distance];
                    Response::returnApiSuccess(200, '更改订单信息成功', $data);
                }
                elseif ($order_msg['order_status'] == DISPATCH || $order_msg['order_status'] == DELIVERED)
                {
                    //正常配送送途中
                    //查询箱子的信息
                    $box_msg = Db::table('gada_box')
                        ->where('bid', $order_msg['order_box'])
                        ->field('longitude,latitude,box_area')
                        ->find();
                    //箱子当前位置坐标
                    $box_coordinate = $box_msg['latitude'] . ',' . $box_msg['longitude'];
                    //箱子的起始坐标
                    $box_amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address=' . $box_msg['box_area']);
                    $box_amap = json_decode($box_amap, true);
                    $box_origin = $box_amap['geocodes'][0]['location'];
                    //配送完成的距离
                    $finished_distance = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin=' . $box_origin . '&destination=' . $box_coordinate);
                    $finished_distance = json_decode($finished_distance, true);
                    $finished_distance = $finished_distance['data']['paths'][0]['distance'] / 1000;
                    //保留两位小数方便观看
                    $finished_distance = number_format($finished_distance, 2);
                    $change_distance = file_get_contents('http://restapi.amap.com/v4/direction/bicycling?key=d3e9608efb6046a177e067ea3fcc9067&origin=' . $box_coordinate . '&destination=' . $destination);
                    $change_distance = json_decode($change_distance, true);
                    $change_distance = $change_distance['data']['paths'][0]['distance'] / 1000;
                    //保留两位小数方便观看
                    $change_distance = number_format($change_distance, 2);
                    $distance = $finished_distance + $change_distance;
                    //计算价格
                    $price = $this->cacheComputations($order_msg['order_weight'], $distance);
                    $payment = $price - $order_msg['order_price'];  //大于0少补的金额，小于0是多退的金额


                    Db::table('gada_order')
                        ->where('order_num', $order_num)
                        ->update(['cache_receiver' => $receiver_name, 'cache_phone' => $receiver_phone, 'cache_address' => $change_address, 'cache_distance' => $distance, 'cache_price' => $payment]);



                    //统计可用卡数量
                    $card_num = Db::table('gada_card')
                        ->where(['uid' => $order_msg['uid'], 'is_use' => 0, 'expired' => 0])
                        ->select();
                    $card_num = count($card_num);
                    //推荐用卡
                    $recommend_card = Card::recommendCard($card_num, $order_msg['uid'], $price);




                    if ($payment < 0)
                    {
                        //多退的情况不允许使用卡券（满足前端展示需求）
                        $card_num = 0;
                        //多退的金额
                        $refund = abs($payment);
                        $reserves = $money['reserves'] + $refund;
                        Db::table('gada_balance')
                            ->where('uid', $order_msg['uid'])
                            ->update(['reserves' => $reserves]);
                        $current_balance = $money['amount'] + $reserves;
                        //多退的金额记录
                        Db::table('gada_transact')
                            ->insert(['user_id' => $order_msg['uid'], 'transact_content' => '更改配送服务', 'transact_detail' => '+' . $refund, 'current_balance' => $current_balance]);
                        //更新订单实际总金额和配送距离
                        Db::table('gada_order')
                            ->where('order_num', $order_num)
                            ->update(['order_price' => $price, 'change_distance' => $distance, 'change_address' => 2,'change_receiver_name'=>$receiver_name,'change_receiver_phone'=>$receiver_phone,'change_receiver_address'=>$change_address]);
                        Test::courierNotice(14,'用户已完成更改地址，请查看新的配送地点',$order_num);
                        //记录订单地址已修改的信息（长久保存的消息展示用）
                        Db::table('gada_change_address_log')
                            ->insert(['order_number' => $order_num, 'change_address' => 2, 'uid' => $order_msg['uid']]);
                    }
                    elseif ($payment == 0)
                    {
                        //多退的情况不允许使用卡券（满足前端展示需求）
                        $card_num = 0;
                        //更新订单实际总金额和配送距离
                        Db::table('gada_order')
                            ->where('order_num', $order_num)
                            ->update(['order_price' => $price, 'change_distance' => $distance, 'change_address' => 2,'change_receiver_name'=>$receiver_name,'change_receiver_phone'=>$receiver_phone,'change_receiver_address'=>$change_address]);
                        Test::courierNotice(14,'用户已完成更改地址，请查看新的配送地点',$order_num);
                        //记录订单地址已修改的信息（长久保存的消息展示用）
                        Db::table('gada_change_address_log')
                            ->insert(['order_number' => $order_num, 'change_address' => 2, 'uid' => $order_msg['uid']]);
                    }



                    $data = ['price' => $payment, 'card_num' => $card_num, 'card' => $recommend_card, 'distance' => $distance];
                    Response::returnApiSuccess(200, '更改订单信息成功', $data);
                } else {
                    //订单状态异常
                    Response::returnApiError(201, '该订单状态异常');
                }
            } else {
                Response::returnApiError(201, '该配送地址无法定位,请换一个试试');
            }
        }
        else
        {
            Response::returnApiError(201, '由于修改配送地址超时，您将无法再修改配送地址');
        }
    }
    public function cacheComputations($weight,$distance)
    {
        //预计算订单的价格
        if($weight <= 10)
        {
            //重量小于10kg的订单，不加收超重费用
            $extra = 0;
        }
        else
        {
            $weight = $weight - 10;
            $extra =(0.5) * ceil($weight/1);
        }
        if($distance<=1)
        {
            $distance_price = 5;
        }
        elseif(1<$distance && $distance<=2)
        {
            $distance_price = 8;
        }
        elseif(2<$distance && $distance<=3)
        {
            $distance_price = 10;
        }
        else
        {
            $distance_price = 50;
        }
        $price = $distance_price + $extra;
        return $price;
    }
    public function midwayDispatch()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        //判定箱子是否已折回
        $goback_return_time = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->value('goback_return_time');
        if(!empty($goback_return_time))
        {
            Response::returnApiError(201,'请刷新订单列表');
        }
        else
        {
            $current_time = date("Y-m-d H:i:s");
            $is_second = Db::table('gada_order')
                ->where('order_num',$order_num)
                ->update(['order_type' => UNFINISHED, 'order_status' => SECOND,'second_create_time'=>$current_time]);
            if($is_second == 1)
            {
                //给快递员通知再配送消息
                Test::courierNotice(7,'您有用户发起再配送的新订单',$order_num);
                Response::returnApiOk(200,'再配送申请成功，请务必保持收货人联系方式畅通');
            }
            else
            {
                Response::returnApiError(201,'再配送申请失败');
            }
        }
    }
    public function takeStock()
    {
        //自取开箱
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $order_num = $data['order_number'];
        $order_msg = Db::table('gada_order')
            ->where('order_num',$order_num)
            ->find();
        $over_time = date("Y-m-d H:i:s");

        //当日无人签收自取（不收费）
        Db::table('gada_order')
            ->where('order_num',$order_num)
            ->update(['order_type'=>COMPLETED,'order_status'=>NO_RECEIVE_TAKESTOCK,'order_over_time'=>$over_time]);
        //清算快递员收入
        $income = $order_msg['order_price'] - 2;
        Db::table('gada_corder')
            ->insert(['order_number'=>$order_num,'income'=>$income,'over_time'=>$over_time,'id_courier'=>$order_msg['courier']]);

        //打开箱子
        Db::table('gada_box')
            ->where('bid', $order_msg['order_box'])
            ->update(['is_close'=>0,'is_lock'=>0]);

        //方便前端发起锁箱操作
        $box_code = Db::table('gada_box')
            ->where('bid', $order_msg['order_box'])
            ->value('box_code');
        $this->create_new_order($order_msg['order_box'],$order_msg['uid']);
        Response::returnApiSuccess(200,'开锁成功，记得取完后锁箱哦！',$box_code);
           /* //收过过夜费后的自取（正常占用的过夜自取和无人签收的过夜自取）
            //当日的计费起始时间
            $start_time = date("Y-m-d") . '8:00:00';
            $current_time = date("Y-m-d H:i:s");
            $hour = ceil($current_time - $start_time);
            //调占用支付接口
            Db::table('gada_order')
                ->where('order_num',$order_num)
                ->update(['order_type'=>COMPLETED,'order_status'=>TIMEOUT_TAKESTOCK,'order_over_time'=>$over_time]);*/

    }
}
























        //已弃用的原订单展示接口，现在改用showorder接口
        /* $token = file_get_contents('php://input');
        $token = json_decode($token,true);
        $token = $token['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $order = Db::table('gada_order')
            ->where([
                'uid'=> $uid,
               // 'order_type'=> 0,
                //  'order_state' => 0
            ])
            ->field('order_num,order_state,courier,order_type,order_status,current_time,order_over_time,order_create_time')
            ->select();
        $num = count($order);
        for($i=0;$i<$num;$i++)
        {
            //true仅表示要展示评论按钮，false仅表示不展示评论按钮
            //填充完成支付字段
            if($order[$i]['order_type'] == 0 && $order[$i]['status'] == 1)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '支付完成';
                $array[$i]['order_time'] = $order[$i]['current_time'];
                $array[$i]['evaluate'] = false;
                $array[$i]['order_content'] = '您的配送订单，编号为"$order[$i][\'order_num\']"';
            }
            //填充被预约字段
            if($order[$i]['order_type'] == 0 && $order[$i]['status'] == 2)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '预约完成';
                $array[$i]['order_time'] = $order[$i]['current_time'];
                $array[$i]['evaluate'] = false;
            }
            //填充配送中字段
            if($order[$i]['order_type'] == 0 && $order[$i]['status'] == 3)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '正在配送中';
                $array[$i]['order_time'] = $order[$i]['current_time'];
                $array[$i]['evaluate'] = false;
            }
            //填充占用中字段
            if($order[$i]['order_type'] == 0 && $order[$i]['state'] == 1)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '正在占用中';
                $array[$i]['order_time'] = $order[$i]['order_create_time'];
                $array[$i]['evaluate'] = false;

            }
            //填充配送完成字段
            if($order[$i]['order_type'] == 1 && $order[$i]['state'] == 0)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '配送完成';
                $array[$i]['order_time'] = $order[$i]['order_over_time'];
                if(empty($order[$i]['evaluate']))
                {
                    $array[$i]['evaluate'] = true;
                }
                else
                {
                    $array[$i]['evaluate'] = false;
                }
            }
            //填充占用完成字段
            if($order[$i]['order_type'] == 1 && $order[$i]['state'] == 1)
            {
                $array[$i]['order_num'] = $order[$i]['order_num'];
                $array[$i]['stataus'] = '占用完成';
                $array[$i]['order_time'] = $order[$i]['order_over_time'];
                if(empty($order[$i]['evaluate']))
                {
                    $array[$i]['evaluate'] = true;
                }
                else
                    {
                        $array[$i]['evaluate'] = false;
                    }
                }
            }*/


