<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/29
 * Time: 14:52
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Token.php');
Class Aes extends controller
{

    //变量函数，选择具体接口功能
    public static function enaes($imei,$data)
    {
        //$imei = $data['imei'];
        //$content = $data['content'];
        $key = Db::table('gada_aes')
            ->where('imei', $imei)
            ->find();
        $aes = $key['aes_key'];
        $iv = $key['iv'];
        $encode = base64_encode(openssl_encrypt($data, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv));
        return $encode;

    }
    public static function deaes($imei,$data)
    {
        //$imei = $data['imei'];
        $data = base64_decode($data);
        $key = Db::table('gada_aes')
            ->where('imei', $imei)
            ->find();
        $aes = $key['aes_key'];
        $iv = $key['iv'];
        $decode = openssl_decrypt($data, 'aes-128-cbc', $aes, OPENSSL_RAW_DATA, $iv);
        return $decode;
    }
}