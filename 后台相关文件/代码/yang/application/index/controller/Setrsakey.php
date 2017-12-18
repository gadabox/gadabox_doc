<?php

namespace app\index\controller;
header("Content-Type: application/json; charset=utf-8");
require_once('Response.php');
use \think\Controller;
use \think\Db;
use think\Session;
use think\Request;

Class SetRsaKey extends controller
{
    public function rsa()
    {
        $ipmsg = file_get_contents('php://input');
        if (empty($ipmsg))
        {
            Response::returnApiError(203, '接口调用失败');
            return;
        }

        $config = array
        (
            "private_key_bits" => 1024,//位数
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        $key = openssl_pkey_new($config);
        openssl_pkey_export($key, $priv_Key);//私钥
        $pub_Key = openssl_pkey_get_details($key);

        $pub_Key = $pub_Key["key"];
        $pub_Key = str_replace("-----BEGIN PUBLIC KEY-----","",$pub_Key);
        $pub_Key = str_replace("-----END PUBLIC KEY-----","",$pub_Key);

         //$pub_Key = base64_encode($pub_Key);
        //注册私钥保留
        $ipmsg = json_decode($ipmsg, true);
        $imei = $ipmsg['imei'];
        Db::table('gada_aes')
            ->where('imei',$imei)
            ->delete();
        $ipmsg = ['imei' =>$imei,'priv_key' =>$priv_Key];
        Db::table('gada_aes') -> insert($ipmsg);

        $pub_Key = ['pub_key'=>$pub_Key];
        //print_r( $pub_Key);
        //发送公钥
        Response::returnApiSuccess(200, '接收公钥成功', $pub_Key);
    }
    public function en_aes()
    {
        $ipmsg = file_get_contents('php://input');
        if (empty($ipmsg))
        {
            Response::returnApiError(203, '接口调用失败');
            return;
        }
        $ipmsg = json_decode($ipmsg, true);
        $imei = $ipmsg['imei'];
        $priv_Key = Db::table('gada_aes')
            ->where('imei',$imei)
            ->value('priv_key');
        //申请AES密钥
        $aes =Setaeskey::aes($imei);
        $aes_key = $aes['aes_key'];
        $iv = $aes['iv'];
        //加密AES密钥和iv
        $en_aes = ""; //密文
        $en_iv = "";
        // $pi_key = openssl_pkey_get_private($priv_Key);
        //RSA加密不能加密数组
        openssl_private_encrypt($aes_key,$en_aes,$priv_Key);
        openssl_private_encrypt($iv,$en_iv,$priv_Key);
        $en_aes = base64_encode($en_aes);
        $en_iv = base64_encode($en_iv);
        $encrypted = ['en_aes' => $en_aes,'iv' => $en_iv];
        // print_r($encrypted);
        //json化时不能转化经RSA加密后的AES密钥
        Response::returnApiSuccess(200, '接收AES成功',$encrypted);
    }

}
