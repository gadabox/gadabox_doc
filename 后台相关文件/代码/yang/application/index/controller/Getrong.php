<?php
/**
 * 辅助前端获取融云相关信息.
 * User: yang
 * Date: 2017/8/10
 * Time: 16:26
 */
namespace app\index\controller;
require_once ('Rongcloud.php');
require_once ('Response.php');
use \think\Db;
use \think\Controller;

Class Getrong extends controller
{
    public static function getRong($data)
    {
        $appKey = 'pvxdm17jpcrmr';
        $appSecret = '8Fxy6a0PZ5f';
       // $jsonPath = "jsonsource/";  目前不用
        $RongCloud = new Rongcloud($appKey,$appSecret);
        $phone = $data['phone'];
        $username = $data['username'];
        $photo= $data['photo'];
        if(empty($photo))
        {
         $photo = 'http://www.rongcloud.cn/images/logo.png';
        }
        //echo ("\n***************** user **************\n");
        // 获取 Token 方法
        $result = $RongCloud->User()->getToken($phone, $username, $photo);
        //echo "getToken    ";
       // print_r($result);
        //echo "\n";
        return $result;
    }
    public function getUserList()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $n = count($data['phones']);
        $data = $data['phones'];
        for($i=0;$i<$n;$i++)
        {
            $udata[$i] = Db::table('gada_user')
                ->join('gada_photo','gada_user . pic_id = gada_photo . pic_id')
                ->where('user_phone',$data[$i])
                ->field('user_phone,username,content')
                ->find();
           /* $udata[$i]['avatar'] = Db::table('gada_photo')
                ->where('pic_id',$udata[$i]['pic_id'])
                ->value('content');*/
            $list[$i] = ['user_phone'=> $udata[$i]['user_phone'],'nickname' =>$udata[$i]['username'],'avatar'=> $udata[$i]['content']];
        }
        if(empty($list))
        {
            $list = [];
        }
        $data = ['users' => $list];
        Response::returnApiSuccess('200','反馈成功',$data);
    }

}