<?php
/**
 * 官网首页的渲染类.
 * User: yang
 * Date: 2017/8/11
 * Time: 11:57
 */
namespace app\index\controller;
header ( "Content-Type: text/html; charset=utf-8" );
use \think\Db;
class Index
{
    public function index()
    {
        $pub_Key = "-----BEGIN PUBLIC KEY----- MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDC0ZMAcqceVeINH6tpIRapFX6R 7AVJKZtVaXxJYAbccqlhFLlMc8pZJEhlvxFfneX7Y0A5nbUMlREFhrNoe/Y6C2OQ l/qIb5mOX8ufXw+L8R7zPpJFkmuRAdfl2506DQEWZwkwpPImoN8CoXuHpvJy3N9p rBXrEOl2R4fC7NkR8wIDAQAB -----END PUBLIC KEY-----";
        $pub_Key = str_replace("-----BEGIN PUBLIC KEY----- ","",$pub_Key);
        $pub_Key = str_replace("-----END PUBLIC KEY-----","",$pub_Key);
        echo $pub_Key;
    }
}
