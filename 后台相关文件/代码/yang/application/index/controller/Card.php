<?php
/**
 * 助力卡券类.
 * User: yang
 * Date: 2017/8/29
 * Time: 14:49
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
require_once ('Response.php');
use \think\Controller;
use \think\Db;
require_once ('Token.php');
const NO_EXPIRED = 0;
const EXPIRED = 1;
const NO_USE = 0;
Class Card extends controller
{

    //变量函数，选择具体接口功能
    public function getcardlist()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token', "$token")
            ->value('user_id');
        $phone = Db::table('gada_user')
            ->where('user_id',$uid)
            ->value('user_phone');
        $card = Db::table('gada_card')
            ->where(['uid'=>$uid,'is_use'=>NO_USE,'expired'=>NO_EXPIRED])
            ->field('card_id,card_value,card_url')
            ->order('card_value desc,over_time asc')
            ->select();
        $num = count($card);
        for($i=0;$i<$num;$i++)
        {
            //生成分享链接
            $card[$i]['url'] = 'http://47.94.157.157/index/invited/invited?phone=' . $phone;
            $card[$i]['image'] = 'http://47.94.157.157:85/public/image/2.jpg';
            $card[$i]['title'] = '你的好友送来一张'.$card[$i]['card_value'] .'元的嘎哒箱助力券，快来领取吧！！！';
            $card[$i]['content'] = '嘎哒箱扫码即用';
        }
        $card = ['cards'=>$card];
        Response::returnApiSuccess('200','反馈成功',$card);
    }
    public function buycards()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        //$imei = $data['imei'];
        $card_5 = $data['card_5'];
        $card_10 = $data['card_10'];
        $sum = 5 * $card_5 + 10 * $card_10;
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $money =  Db::table('gada_balance')
            ->where('uid', "$uid")
            ->find();
        $balance = $money['reserves'] + $money['amount'];
        if($balance < $sum)
        {
            Response::returnApiError('201','余额不足');
        }
        else
        {
            //余额充足
            $money = Orderpay::balanceComplementedPay($money,$sum);
            Db::table('gada_balance')
                ->where('uid',$uid)
                ->update($money);
            //交易明细记录
            Orderpay::transactRecords($money,$sum,$uid,'购买卡券','-');
            //记录购买日期和到期日期
            $time = date("Y-m-d");
            $over_time = date("Y-m-d",strtotime($time.'+1 year'));
            $card5 = ['uid' =>$uid,'card_value' => '5','card_url' =>'http://47.94.157.157:85/public/image/5t.png','start_time'=>$time,'over_time'=>$over_time];
            $card10 = ['uid' =>$uid,'card_value' => '10','card_url' =>'http://47.94.157.157:85/public/image/10t.png','start_time'=>$time,'over_time'=>$over_time];
            for($i=0;$i<$card_5;$i++)
            {
                Db::table('gada_card')
                    ->insert($card5);
            }
            for($i=0;$i<$card_10;$i++)
            {
                Db::table('gada_card')
                    ->insert($card10);
            }
            $balance = $money['reserves'] + $money['amount'];
            Response::returnApiSuccess('200','购买成功',$balance);
        }
    }
    public function giveCard()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $receiver_phone = $data['phone'];
        $card_id = $data['card_id'];
        $card_msg = Db::table('gada_card')
            ->where('card_id',$card_id)
            ->field('uid,is_use,expired')
            ->find();
        $receiver_id = Db::table('gada_user')
            ->where('user_phone',$receiver_phone)
            ->value('user_id');
        if($card_msg['uid'] != $uid || $card_msg['is_use'] !=0 || $card_msg['expired'] !=0)
        {
            Response::returnApiError(201,'卡券信息异常');
        }
        else
        {
            if(empty($receiver_id))
            {
                Response::returnApiError(201,'该用户未注册');
            }
            else
            {
                //卡券的转赠
                Db::table('gada_card')
                    ->where('card_id',$card_id)
                    ->update(['uid'=>$receiver_id]);
                Response::returnApiOk(200,'转赠成功');
            }
        }
    }
    public function setExpiredCard()
    {
        //设置过期卡券并删除将过期消息推送
        $time = date("Y-m-d H:i:s");
        Db::table('gada_card')
            ->where(['is_use'=>NO_USE,'expired '=>NO_EXPIRED])
            ->where('over_time' < $time)
            ->update(['expired'=>EXPIRED]);
    }
    public static function recommendCard($card_num,$uid,$price)
    {
        if($card_num == 0)
        {
            //无卡券推荐
            $recommend_card = null;
        }
        else
        {
            //推荐用卡(在价格一样的情况下优先推荐快过期的卡券)

            $available_card = Db::table('gada_card')
                ->where(['uid' => $uid,'is_use'=>NO_USE,'expired'=>NO_EXPIRED])
                ->where("card_value<=$price")
                ->order('card_value asc,over_time asc')
                ->field('card_id,card_value')
                ->select();
            $n = count($available_card);
            $cache_sum = 0;
            $j = 0;
            for($i=0;$i<$n;$i++)
            {
                $sum = $cache_sum+$available_card[$i]['card_value'];
                if($sum<$price)
                {
                    //当前卡券总值还小于应支付总价格
                    $cache_sum = $sum;
                    $recommend_card[$j] = $available_card[$i];
                    $j++;
                }
                elseif($sum == $price)
                {
                    $recommend_card[$j] = $available_card[$i];
                    break;
                }
            }
            if(empty($recommend_card))
            {
                //没合适卡券推荐
                $recommend_card = null;
            }
        }
        return $recommend_card;
    }
}