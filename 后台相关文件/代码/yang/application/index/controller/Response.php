<?php
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
/**
 * 数据返回发送类.
 * User: yang
 * Date: 2017/7/19
 * Time: 15:23
 */
/**
 * 按json方式传输通讯数据
 * @param integer $code  状态码
 * @param string $message  消息提示
 * @param array $data   数据
 * @return  string
 */
Class Response
{
       public static function returnApiSuccess($code,$message = '',$data)
       {
           //正常登陆接口
           $result = array(
               'code' => $code,
               'message' => $message,
               'data' => $data,
           );
           echo json_encode($result,JSON_UNESCAPED_UNICODE);
           return;
        }
        public static function returnApiOk($code,$message = '')
        {
            //失败登陆接口
            $result = array(
                'code' => $code,
                'message' => $message,
            );
            echo json_encode($result,JSON_UNESCAPED_UNICODE);
            return;
        }
       public static function returnApiError($code,$message = '')
       {
           //失败登陆接口
           $result = array(
               'code' => $code,
               'message' => $message,
           );
           echo json_encode($result,JSON_UNESCAPED_UNICODE);
           return;
       }
        public static function returnToken($token)
        {
            //返回新生成的token
            $result = array(
                'token' => $token,
            );
            echo json_encode($result,JSON_UNESCAPED_UNICODE);
            return;
        }
}