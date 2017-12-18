<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/28
 * Time: 16:20
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
use think\Db;
use \app\index\model\Photo;

class Invited extends Controller
{
    public function invited()
    {
        $num = $_GET['phone'];
        $phone[0] = ['phone'=>$num];
        $this->assign('phone',$phone);
        return $this->fetch();
    }
    public function validation($number)
    {
        //审核验证码是否正确

    }
    public function register($phone)
    {
        //先审核
        $is_phone = Db::table('gada_user')
            ->where('user_phone',$phone)
            ->value('username');
        if(!empty($is_phone))
        {
            $notic = '对不起您已是老用户，感谢您的参与';
        }
        else
        {
            $num = rand(100000, 999999);
            $username = 'gada' .$num;
            //先申请一个头像库
            $pic = new Photo;
            $pic->create_time = date("Y-m-d H:i:s");
            $pic->save();
            $pic_id = $pic->pic_id;
            $phone = ['username' => $username,'user_phone' => $phone,'pic_id'=>$pic_id];
            Db::table('gada_user')->insert($phone);
            $uid = Db::table('gada_user')
                ->where('user_phone',$phone)
                ->value('user_id');
            //送两张五元助力券
            $card_5 = 2;
            $card5 = ['uid' =>$uid,'card_value' => '5','card_url' =>'http://47.94.157.157:85/public/image/5t.png'];
            for($i=0;$i<$card_5;$i++)
            {
                Db::table('gada_card')
                    ->insert($card5);
            }
            $notic = '恭喜你，领取成功，可在嘎哒箱APP‘我的卡券’中查看';
        }
    }
    public function url()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $phone = Db::table('gada_token')
            ->join('gada_user','gada_token . user_id = gada_user . user_id')
            ->where('user_token',$token)
            ->value('user_phone');
        //生成分享链接
        $url = 'http://47.94.157.157/index/invited/invited?phone=' . $phone;
        $image = 'http://47.94.157.157:85/public/image/2.jpg';
        $title = '你的好友送来两张嘎哒箱助力券，快来领取吧！！！';
        $content = '嘎哒箱扫码即用';
        $data = ['url'=>$url,'image'=>$image,'title'=>$title,'content'=>$content];
        Response::returnApiSuccess(200,'生成成功',$data);

    }
}