<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/28
 * Time: 10:03
 */
namespace app\courier\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//include_once(dirname(__FILE__) . '../../index/controller/autoload.php');
use \think\Db;
use \think\Controller;
use app\courier\model\Cphoto;
require ('../application/courier/controller/Constants.php');
Class Test
{
    public function audit()
    {
        //模拟审核通过最后一个快递申请人的信息
        $courier_msg = Db::table('gada_register')
            ->order('register_id desc')
            ->field('cname,cphone,courier_identification,identification_id')
            ->find();
        //申请一个头像库
        $courier_photo = new Cphoto;
        $courier_photo->create_time = date("Y-m-d H:i:s");
        $courier_photo->save();
        $pic_id = $courier_photo->pic_id;
        $courier_msg['pic_id'] = $pic_id;
        $is_audit = Db::table('gada_courier')
            ->insert($courier_msg);
        //转移待申请表中数据

        if($is_audit == 1)
        {
            $register_id = Db::table('gada_register')
                ->order('register_id desc')
                ->where('is_register',2)
                ->value('register_id');

            $is_updata = Db::table('gada_register')
                ->where('register_id',$register_id)
                ->update(['is_register'=>1]);
            if($is_updata == 1){
            echo '审核完成';
            }
        }
    }
    public function test()
    {
        $ctoken = '9107c83a6a741d1e0660dfd2787afe55';
        $id_courier = Db::table('gada_ctoken')
            ->where('courier_token',$ctoken)
            ->value('id_courier');
        print_r($id_courier);
       /* $paid = Db::table('gada_order')
            ->where(['courier'=>$id_courier,'order_type'=>UNFINISHED,'order_status'=>PAID])
            ->field('order_num,order_box,order_price,order_address_id,order_arrive_time,uid,order_distance')
            ->select();
        $n = count($paid);
        for($i=0;$i<$n;$i++) {
            $order_price[$i] = $paid[$i]['order_price'] - 1;
            //配送地址
            $address[$i] = Db::table('gada_address')
                ->where('address_id', $paid[$i]['order_address_id'])
                ->field('area,address')
                ->find();
            //箱子所属商家
            $box[$i] = Db::table('gada_box')
                ->where('bid', $paid[$i]['order_box'])
                ->field('box_area,box_store')
                ->find();
            $receiver_phone[$i] = Db::table('gada_user')
                ->where('user_id', $paid[$i]['uid'])
                ->value('user_phone');

            $data[$i] = ['order_number' => $paid[$i]['order_num'], 'time' => $paid[$i]['order_arrive_time'], 'price' => $order_price[$i], 'sender_address' => $box[$i]['box_area'], 'sender' => $box[$i]['box_store'], 'receiver_address' => $address[$i]['address'], 'receiver_area' => $address[$i]['area'], 'receiver_phone' => $receiver_phone[$i], 'distance' => $paid[$i]['order_distance']];
        }
        print_r($data);*/
    }
}