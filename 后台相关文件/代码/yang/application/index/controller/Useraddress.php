<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/1
 * Time: 15:15
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
//use think\Response;

//use think\Response;

require_once ('Aes.php');

//require_once ('Token.php');
Class Useraddress extends controller
{

    //变量函数，选择具体接口功能
    public function getAddressList()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
       // $token = $data['token'];
        $token = Aes::deaes($imei,$data['token']);
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $add = Db::table('gada_address')
            ->where('user_id',$uid)
            ->order('is_default desc,address_id desc')
            ->field('receiver_name,receiver_phone,area,address,address_id,is_default')
            ->select();
        $n = count($add);
        if($n == 0)
        {
            $address = ['address'=> $add];
            Response::returnApiSuccess(200,'地址反馈成功',$address);
        }
        else
        {
        for($i = 0;$i<$n;$i++)
        {
        $add[$i]['receiver_address'] =  $add[$i]['area'] . $add[$i]['address'];

        $add[$i]['receiver_address'] = Aes::enaes($imei,$add[$i]['receiver_address']);

        $address[$i] = ['receiver_name'=> Aes::enaes($imei,$add[$i]['receiver_name']),'receiver_phone'=> Aes::enaes($imei,$add[$i]['receiver_phone']),'receiver_address' =>  $add[$i]['receiver_address'],'address_id' => Aes::enaes($imei,$add[$i]['address_id']),'is_default'=>  Aes::enaes($imei,$add[$i]['is_default']),'area'=>  Aes::enaes($imei,$add[$i]['area']),'address' =>  Aes::enaes($imei,$add[$i]['address']) ];
        }
        $address = ['address' => $address];
        Response::returnApiSuccess(200,'地址反馈成功',$address);
        }
    }
    public function addAddress()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        //$token = $data['token'];
        $token = Aes::deaes($imei,$data['token']);
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $receiver_name = Aes::deaes($imei,$data['receiver_name']);
        $receiver_phone = Aes::deaes($imei,$data['receiver_phone']);
        $area = Aes::deaes($imei,$data['area']);
        $address = Aes::deaes($imei,$data['address']);
        $is_default = Aes::deaes($imei,$data['is_default']);

        //配送地址信息在高德地图API进行查询比对，是否可用
        $amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address='.$area . $address);
        $amap = json_decode($amap,true);
        //判定定位等级是否达标
        $level = $amap['geocodes'][0]['level'];
        if($level == '村庄'||$level == '热点商圈'||$level == '兴趣点'|| $level == '门牌号'||$level == '单元号'||$level =='道路'||$level =='道路交叉路口'||$level =='公交站台、地铁站')
        {
        //$receiver_name = Aes::deaes($imei,$data['receiver_Name']);
        //$receiver_phone = Aes::deaes($imei,$data['receiver_Phone']);
        //$area = Aes::deaes($imei,$data['area']);
        //$address = Aes::deaes($imei,$data['address']);
            $str=explode(",",$amap['geocodes'][0]['location']);
            $address_longitude = $str[(count($str)-1)];
            $address_latitude = $str[0];
            if($is_default == 1)
            {
                Db::table('gada_address')
                    ->where('user_id',$uid)
                    ->update(['is_default' => 0]);
            }
            $add = ['receiver_name'=>$receiver_name,'receiver_phone'=>$receiver_phone,'area'=>$area,'address'=>$address,'user_id'=>$uid,'is_default'=> $is_default,'address_longitude'=>$address_longitude,'address_latitude'=>$address_latitude];
            Db::table('gada_address')
                ->insert($add);
            Response::returnApiOk(200,'添加地址成功');
        }
        else
        {
            Response::returnApiError(201,'该配送地址无法定位,请换一个试试');
        }
    }
    public function setDefault()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $add_id = Aes::deaes($imei,$data['address_id']);
        if(empty($add_id))
        {
            //该地址并不在用户地址库中
            Response::returnApiError(201,'无法设置为默认地址');
        }
        else {
            //$add_id = $data['address_id'];
            $uid = Db::table('gada_address')
                ->where('address_id', $add_id)
                ->value('user_id');
            //其余地址先取消默认
            $address = Db::table('gada_address')
                ->field('address_id')
                ->where('user_id', $uid)
                ->select();
            $n = count($address);
            for ($i = 0; $i < $n; $i++) {
                Db::table('gada_address')
                    ->where('address_id', $address[$i]['address_id'])
                    ->update(['is_default' => 0]);
            }
            $is_updata = Db::table('gada_address')
                ->where('address_id', $add_id)
                ->update(['is_default' => 1]);
            if ($is_updata == 1) {
                Response::returnApiOk('200', '设置默认地址成功');
            } else {
                Response::returnApiError(201, '设置默认地址失败');
            }
        }
    }
    public function deleteAddress()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $add_id = Aes::deaes($imei,$data['address_id']);

        Db::table('gada_address')
            ->where('address_id',$add_id)
            ->delete();

        Response::returnApiOk('200','删除配送地址成功');
    }
    public function updateAddress()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $imei = $data['imei'];
        $add_id = Aes::deaes($imei, $data['address_id']);
        $receiver_name = Aes::deaes($imei, $data['receiver_name']);
        $receiver_phone = Aes::deaes($imei, $data['receiver_phone']);
        $area = Aes::deaes($imei, $data['area']);
        $address = Aes::deaes($imei, $data['address']);
        $is_default = Aes::deaes($imei, $data['is_default']);
        //配送地址信息在高德地图API进行查询比对，是否可用
        $amap = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=d3e9608efb6046a177e067ea3fcc9067&address=' . $area . $address);
        $amap = json_decode($amap, true);
        //判定定位等级是否达标
        $level = $amap['geocodes'][0]['level'];
        if ($level == '村庄' || $level == '热点商圈' || $level == '兴趣点' || $level == '门牌号' || $level == '单元号' || $level == '道路' || $level == '道路交叉路口' || $level == '公交站台、地铁站')
        {
            $str=explode(",",$amap['geocodes'][0]['location']);
            $address_longitude = $str[(count($str)-1)];
            $address_latitude = $str[0];
            if ($is_default == 1) {
                //保证该项设置默认后其他地址管理默认值字段清零
                $uid = Db::table('gada_address')
                    ->where('address_id', $add_id)
                    ->value('user_id');
                $address = Db::table('gada_address')
                    ->field('address_id')
                    ->where('user_id', $uid)
                    ->select();
                $n = count($address);
                for ($i = 0; $i < $n; $i++) {
                    Db::table('gada_address')
                        ->where('address_id', $address[$i]['address_id'])
                        ->update(['is_default' => 0]);
                }
            }
            Db::table('gada_address')
                ->where('address_id', $add_id)
                ->update(['receiver_name' => $receiver_name, 'receiver_phone' => $receiver_phone, 'area' => $area, 'address' => $address, 'is_default' => $is_default,'address_longitude'=>$address_longitude,'address_latitude'=>$address_latitude]);
            Response::returnApiOk('200', '更改地址成功');
        }
        else
        {
            Response::returnApiError(201,'该配送地址无法定位,请换一个试试');
        }
    }
}