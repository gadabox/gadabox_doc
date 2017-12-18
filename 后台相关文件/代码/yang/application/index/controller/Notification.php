<?php
/**
 * 消息通知类.
 * User: yang
 * Date: 2017/10/12
 * Time: 16:01
 */
namespace app\index\controller;
require_once('Response.php');
require_once ('Constants.php');
use \think\Db;
use \think\Controller;

Class Notification extends controller
{
    public function orderNotification()
    {
        //订单消息的通知查询接口
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $order_messages = $this->orderNotificationMessages($uid);
        if(empty($order_messages['order_messages']))
        {
            Response::returnApiSuccess(200,'返回成功',$order_messages);
        }
        else {
            //对查询结果的二维数组进行时间排序
            $sort = array(
                'direction' => 'SORT_DESC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
                'field' => 'order_time',       //排序字段
            );
            $arrSort = array();
            foreach ($order_messages['order_messages'] AS $uniqid => $row) {
                foreach ($row AS $key => $value) {
                    $arrSort[$key][$uniqid] = $value;
                }
            }
            if ($sort['direction']) {
                array_multisort($arrSort[$sort['field']], constant($sort['direction']), $order_messages['order_messages']);
            }
            Response::returnApiSuccess(200, '返回成功', $order_messages);
        }
    }
    public function orderNotificationMessages($uid)
    {
        $unfinished = Db::table('gada_order')
            ->join('gada_box','gada_order . order_box = gada_box . bid')
            ->where(['uid'=>$uid,'order_type'=>UNFINISHED])
            ->field(['order_status,order_num,order_current_time,change_address,is_notice,is_lock'])
            ->order('order_current_time desc')
            ->select();
        $completed = Db::table('gada_order')
            ->where(['uid'=>$uid,'order_type'=>COMPLETED])
            ->field(['order_status,order_num,order_current_time,evaluate_time'])
            ->order('order_current_time desc')
            ->select();
        $special = Db::table('gada_order')
            ->where(['uid'=>$uid,'order_type'=>SPECIAL])
            ->field(['order_status,order_num,order_current_time'])
            ->order('order_current_time desc')
            ->select();
        $change_address = Db::table('gada_change_address_log')
            ->where('uid',$uid)
            ->order('create_time desc')
            ->find();
        $n = count($unfinished);        //未完成订单数目
        $j = count($completed);         //已完成订单数目
        $k = count($special);           //特殊订单数目
        $m = 0;                         //初始化返回数组的起始地址
        for($i = 0;$i < $k;$i++) {
            if ($special[$i]['order_status'] == 3) {
                $content = '由于我们的快递小哥无法与您取得联系,我们已将您的嘎哒箱遣回,请在订单详情中完成后续操作';
                $message[$m] = ['order_status' => '订单无人签收', 'order_number' => $special[$i]['order_num'], 'order_content' => $content, 'order_time' => $special[$i]['order_current_time']];
                $m++;
            }
        }
        for($i = 0;$i < $n;$i++) {
            if ($unfinished[$i]['change_address'] == 1)
            {
                //更改地址的订单优先级最高，可覆盖当前订单的本来状态
                $content = '您的配送订单，编号为'.$unfinished[$i]['order_num'].'快递员提报了客户改送货地址的申请，十分钟内未更改将按照原地址配送';
                $message[$m] = ['order_status' => '订单待改地址', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                $m++;
            }
            else
            {
                if ($unfinished[$i]['order_status'] == 4 && $unfinished[$i]['is_lock'] == 1) {
                    $content = '您的存放订单,编号为' . $unfinished[$i]['order_num'] . '还没取出,请尽量取出或联系我们,过夜将产生过夜费。';
                    $message[$m] = ['order_status' => '寄存订单未取货', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                    $m++;
                }
                elseif ($unfinished[$i]['order_status'] == 1) {
                    $content = '我们的快递小哥已接收您的嘎哒箱订单';
                    $message[$m] = ['order_status' => '订单已接单', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                    $m++;
                }
                elseif ($unfinished[$i]['order_status'] == 2) {
                    $content = '快递小哥已接收到您的嘎哒箱,正火速朝您赶来';
                    $message[$m] = ['order_status' => '订单配送中', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                    $m++;
                }
                elseif ($unfinished[$i]['order_status'] == 3) {
                    $content = '快递小哥已到达您所指定的区域,请签收';
                    $message[$m] = ['order_status' => '订单已送达', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                    $m++;
                }
                elseif ($unfinished[$i]['order_status'] == 5) {
                    $content = '您的嘎哒箱订单正在由我们的快递小哥进行二次配送,请保持手机联系畅通';
                    $message[$m] = ['order_status' => '订单二次配送中', 'order_number' => $unfinished[$i]['order_num'], 'order_content' => $content, 'order_time' => $unfinished[$i]['order_current_time']];
                    $m++;
                }
            }
        }
        for($i = 0;$i < $j;$i++) {
            if ($completed[$i]['order_status'] == 0 && empty($completed[$i]['evaluate_time'])) {
                $content = '您的嘎哒箱订单已完成签收,感谢您的使用';
                $message[$m] = ['order_status' => '订单已签收', 'order_number' => $completed[$i]['order_num'], 'order_content' => $content, 'order_time' => $completed[$i]['order_current_time']];
                $m++;
            }
            elseif ($completed[$i]['order_status'] == 1 ||$completed[$i]['order_status'] ==2) {
                $content = '您在嘎哒箱中的物品已取出,感谢您的使用';
                $message[$m] = ['order_status' => '物品已取出', 'order_number' => $completed[$i]['order_num'], 'order_content' => $content, 'order_time' => $completed[$i]['order_current_time']];
                $m++;
            }
        }
        if(!empty($change_address))
        {
            if($change_address['change_address'] == 0)
            {
                $content = '您的配送订单，编号为'.$change_address['order_number'].'，修改地址请求由于10分钟内未进行任何操作响应，我们将对原配送地址进行配送';
                $message[$m] = ['order_status' => '订单未改地址', 'order_number' => $change_address['order_number'], 'order_content' => $content, 'order_time' => $change_address['create_time']];
            }
            else //change_address == 2
            {
                $content = '您的配送订单，编号为'.$change_address['order_number'].'已完成地址修改，您可在订单详情中进行查看';
                $message[$m] = ['order_status' => '订单已改地址', 'order_number' => $change_address['order_number'], 'order_content' => $content, 'order_time' => $change_address['create_time']];
            }
        }
            //所有订单通知的详细全展示
            if($m == 0)
            {
                $order_messages = ['order_messages'=>[]];
            }
            else
            {
                $order_messages = ['order_messages' => $message];
            }

        return $order_messages;
    }
    public function message()
    {
        //消息首页，展示两大类消息中（订单消息、消息通知）最新的一条消息
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        //查询订单最新消息
        $order_messages = $this->orderNotificationMessages($uid);
        if(!empty($order_messages['order_messages']))
        {
            //防止空数组 无法取指定位置的数据
            $order_messages['order_message'] = $order_messages['order_messages'][0];
        }
        else
        {
            $order_messages['order_message'] = null;
        }
        //查询最新通知消息
        $notify_message = $this->notificationMsg($uid);
        if(!empty($notify_message))
        {
            //防止空数组 无法取指定位置的数据
            $notify_message =$notify_message[0];
        }
        $data = ['order_message'=>$order_messages['order_message'],'notify_message'=>$notify_message];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function notification()
    {
        //消息通知接口
        $data = file_get_contents('php://input');
        $data = json_decode($data,true);
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        //快过期卡券提醒
        $card = $this->notificationMsg($uid);

        //最近活动提醒信息（*整合排序，不然会乱）$card 以后要替换成所有类型通知的综合

        $data = ['notify_messages'=>$card];
        Response::returnApiSuccess(200,'反馈成功',$data);
    }
    public function notificationMsg($uid)
    {
        //各类型通知消息的综合查询
        //快过期卡券提醒
        $card_msg = $this->cardMsg($uid);
        $card = $this->showExpiringCardMsg($card_msg);

        //活动通知消息

        return $card;
    }
    public function notificationSequencing()
    {
        //各类型通知消息按时间进行排序
    }
    public function showExpiringCardMsg($card_msg)
    {
        $n = count($card_msg);
        $card = [];
        //统一反馈的格式
        for($i=0;$i<$n;$i++)
        {
            $notify_title = '您的助力卡' . $card_msg[$i]['expiring_status'] . '天后过期';
            $notify_content = '您有' . $card_msg[$i]['expiring_count'] . '张嘎哒助力卡再有一周就要过期了,赶快去使用吧！';
            $notify_time = $card_msg[$i]['create_time'] . ' 00:00:00';
            $card[$i] = ['notify_time'=>$notify_time,'notify_title'=>$notify_title,'notify_content'=>$notify_content,'color'=>'red'];
        }
        return $card;
    }
    public function cardMsg($uid)
    {
        //卡券临近到期通知信息
        $card_msg = Db::table('gada_expiringcard')
            ->where('user_id',$uid)
            ->field('expiring_count,create_time,expiring_status')
            ->select();
        return $card_msg;

    }
    public function expiringCard()
    {
        //统计并通知快到期卡券的信息(定时文件)
        $today = date("Y-m-d");
        $expiring_seven_day = date("Y-m-d",strtotime("+6day"));
        //七天通知消息在前一天再通知时失效
        $seven_failure_time = date("Y-m-d",strtotime("+5day"));
        //清理提示七天过期的通知消息，保留1天过期的通知消息
        Db::table('gada_expiringcard')
            ->where('failure_time','<=',$today)
            ->where('expiring_status',7)
            ->delete();
        $seven_day_card = Db::table('gada_card')
            ->where(['is_use'=>0,'expired'=>0,'over_time'=>$expiring_seven_day])
            ->join('gada_user','gada_user . user_id = gada_card . uid')
            ->field('uid,user_phone,count(card_id)')
            ->group('uid')
            ->select();
        $n = count($seven_day_card);
        for($i=0;$i<$n;$i++)
        {
            $msg = '您有'.$seven_day_card[$i]['count(card_id)']. '张助力券快到期了,为避免浪费尽快使用吧！';
            Test::noticeMsg(12,$msg,$seven_day_card[$i]['user_phone']);
            Db::table('gada_expiringcard')
                ->insert(['user_id'=>$seven_day_card[$i]['uid'],'expiring_count'=>$seven_day_card[$i]['count(card_id)'],'expiring_status'=>'7','create_time'=>$today,'failure_time'=>$seven_failure_time]);
        }
        $expiring_tomorrow = date("Y-m-d",strtotime("+1day"));
        $one_day_card= Db::table('gada_card')
            ->where(['is_use'=>0,'expired'=>0,'over_time'=>$expiring_tomorrow])
            ->join('gada_user','gada_user . user_id = gada_card . uid')
            ->field('uid,user_phone,count(card_id)')
            ->group('uid')
            ->select();
        $m = count($one_day_card);
        for($i=0;$i<$m;$i++)
        {
            $msg = '您有'.$one_day_card[$i]['count(card_id)']. '张助力券明天就到期了,为避免浪费尽快使用吧！';
            Test::noticeMsg(12,$msg,$one_day_card[$i]['user_phone']);
            Db::table('gada_expiringcard')
                ->insert(['user_id'=>$one_day_card[$i]['uid'],'expiring_count'=>$one_day_card[$i]['count(card_id)'],'expiring_status'=>'1','create_time'=>$today]);
        }
        echo '通知都已经派发出';
    }
}