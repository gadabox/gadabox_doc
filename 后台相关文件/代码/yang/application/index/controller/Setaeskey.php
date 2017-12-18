<?php
namespace app\index\controller;
header("Content-Type: application/json; charset=utf-8");
require_once('Response.php');
use \think\Controller;
use \think\Db;
//use think\Session;
//use think\Request;

Class SetAesKey extends controller
{
    public static function aes($imei)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
        $aeskey = '';
        $iv = '';
        for ( $i = 0; $i < 16; $i++ )
        {
            // 这里提供两种字符获取方式
            // 第一种是使用 substr 截取$chars中的任意一位字符；
            // 第二种是取字符数组 $chars 的任意元素
            // $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1);
                $aeskey .= $chars[ mt_rand(0, strlen($chars) - 1) ];
                $iv .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

                // $data = "Test String";
                $aes = ['aes_key' => $aeskey, 'iv' => $iv];
                Db::table('gada_aes')
                    ->where('imei', $imei)
                    ->update($aes);
                return $aes;
            }
            /* public function en_aes()
         {
         //加密
             $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $privateKey, $data, MCRYPT_MODE_CBC, $iv);
             echo(base64_encode($encrypted));
             echo '<br/>';

         //解密
             $encryptedData = base64_decode("2fbwW9+8vPId2/foafZq6Q==");
             $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $privateKey, $encryptedData, MCRYPT_MODE_CBC, $iv);
             echo($decrypted);
         }*/
}