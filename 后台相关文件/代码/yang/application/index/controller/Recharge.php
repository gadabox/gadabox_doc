<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/11/8
 * Time: 11:31
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Db;
use \think\Controller;
require_once ('Response.php');
require_once ('Test.php');
require_once ('Payconstants.php');
class Recharge
    {
        public function recharge()
        {
            $data = file_get_contents('php://input');
            $data = json_decode($data, true);
            if($data['pay_type'] =='wechat')
            {
                //跳转微信支付
                $this->wechatPay($data);
            }
            elseif($data['pay_type'] == 'ali')
            {
                //跳转支付宝支付
                $this->alipay($data);
            }
        }
        public function wechatPay($data)
        {
            //接收前端充值请求向微信接口申请预支付订单
            $token = $data['token'];
            $uid = Db::table('gada_token')
                ->where('user_token',$token)
                ->value('user_id');
            $timestamp = time();
            $recharge_money = $data['recharge_money'];
            $recharge_money = $recharge_money * 100;
            //随机字符串
            $nonce_str = Test::nonce_str(32);
            $recharge_number = Test::rechargeNumber();
            //$notify_url = '&' . 'notify_url';
            $stringA="appid=wx92dd9aaefbfa74d8&attach=支付充值&body=gadabox&mch_id=1488123732&nonce_str={$nonce_str}&notify_url=http://47.94.157.157/index/recharge/wechatnotify&out_trade_no={$recharge_number}&spbill_create_ip=14.23.150.211&total_fee={$recharge_money}&trade_type=APP&key=bbBfAcefBdZEvDfHwxddAAefBfaGDbMW";
            $sign = md5($stringA);

            //所有字符大写
            $sign =strtoupper($sign);

            $a = "<xml>
               <appid>".WECHAT_APP_ID ."</appid>
               <attach>支付充值</attach>
               <body>gadabox</body>
               <mch_id>".WECHAT_MCH_ID."</mch_id>
               <nonce_str>{$nonce_str}</nonce_str>
               <notify_url>".WECHAT_NOTIFY."</notify_url>
               <out_trade_no>{$recharge_number}</out_trade_no>
               <spbill_create_ip>".WECHAT_SPBILL_CREATE_ID."</spbill_create_ip>
               <total_fee>{$recharge_money}</total_fee>
               <trade_type>APP</trade_type>
               <sign>{$sign}</sign>
                </xml>";
            $curl = curl_init();

//设置url
            curl_setopt($curl, CURLOPT_URL,"https://api.mch.weixin.qq.com/pay/unifiedorder");

//设置发送方式：post
            curl_setopt($curl, CURLOPT_POST, true);

//设置发送数据
            curl_setopt($curl, CURLOPT_POSTFIELDS, $a);

//TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

//执行cURL会话 ( 返回的数据为xml )
            $return_xml = curl_exec($curl);

//关闭cURL资源，并且释放系统资源
            curl_close($curl);

            $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            $value_array_json = json_encode($value_array,true);
            if($value_array['return_code'] == 'FAIL')
            {

                //预订单发起失败，并记录表中
                Db::table('gada_wpayfail')
                    ->insert(['return_message'=>$value_array['return_msg'],'fail_type'=>'预支付订单发起失败','origin_string'=>$value_array_json]);
            }
            else
            {
                //接收成功
                //录入支付基础表
                $money = $recharge_money/100; //转换成元单位
                Db::table('gada_paybasic')
                    ->insert(['pay_order_number'=>$recharge_number,'total_amount'=>$money,'pay_status'=>'待支付','third_prepaid'=>$value_array['prepay_id'],'third_type'=>'微信','create_uid'=>$uid,'origin_string'=>$value_array_json]);
                //配合前端回调
                $stringB = "appid=wx92dd9aaefbfa74d8&noncestr={$value_array['nonce_str']}&package=Sign=WXPay&partnerid=1488123732&prepayid={$value_array['prepay_id']}&timestamp={$timestamp}&key=bbBfAcefBdZEvDfHwxddAAefBfaGDbMW";
                $app_sign = md5($stringB);
                $app_sign = strtoupper($app_sign);
                $value_array['timestamp'] = $timestamp;
                $value_array['partner_id'] = WECHAT_MCH_ID;
                $value_array['app_sign'] = $app_sign;


                Response::returnApiSuccess(200, '反馈成功', $value_array);
            }
        }
    public function wechatNotify()
    {
        $return_xml = file_get_contents('php://input');
        $value_array = json_decode(json_encode(simplexml_load_string($return_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $value_array_json = json_encode($value_array,true);
        if($value_array['return_code'] == 'SUCCESS')
        {
            //接收到成功反馈
            $paybasic_msg = Db::table('gada_paybasic')
                ->where('pay_order_number',$value_array['out_trade_no'])
                ->field('paybasic_id,total_amount,pay_status,create_uid')
                ->find();
            if(empty($paybasic_msg['paybasic_id']))
            {
                //该反馈为无效反馈（预支付表中并没此信息）
                Db::table('gada_wpayfail')
                    ->insert(['return_message'=>$value_array['return_msg'],'fail_type'=>'无效反馈','origin_string'=>$value_array_json]);
            }
            else
            {
                //在预支付表中有相关信息
                $total_amount = $paybasic_msg['total_amount']*100; //转化微信通用单位 分
                if($paybasic_msg['pay_status'] == '待支付'&&$total_amount==$value_array['total_fee'])
                {
                    $is_success = Db::table('gada_paylog')
                        ->insert(['pay_order_number'=>$value_array['out_trade_no'],'total_amount'=>$paybasic_msg['total_amount'],'origin_string'=>$value_array_json,'third_type'=>'微信','third_order_number'=>$value_array['transaction_id']]);
                    if($is_success == 1)
                    {
                        $time = date("Y-m-d H:i:s");
                        //变更预支付表中信息
                        Db::table('gada_paybasic')
                            ->where('paybasic_id',$paybasic_msg['paybasic_id'])
                            ->update(['pay_status'=>'支付完成','actual_time'=>$time]);
                        //对支付用户账户进行充值
                        $money = Db::table('gada_balance')
                            ->where('uid',$paybasic_msg['create_uid'])
                            ->field('amount,reserves')
                            ->find();
                        $money['amount'] +=$paybasic_msg['total_amount'];
                        Db::table('gada_balance')
                            ->where('uid',$paybasic_msg['create_uid'])
                            ->update($money);
                        //记录交易明细
                        $current_balance = $money['reserves'] + $money['amount']; //当前余额
                        //保留两位小数方便观看
                        $current_balance = number_format($current_balance, 2);
                        Db::table('gada_transact')
                            ->insert(['user_id' => $paybasic_msg['create_uid'], 'transact_content' => '购买充值服务', 'transact_detail' => '+' .$paybasic_msg['total_amount'], 'current_balance' => $current_balance]);
                    }
                }
                else
                {
                    //收到无用重复回执信息或者无效的金额回执信息，不用做任何处理
                }
            }
        }
        elseif($value_array['return_code'] == 'FAIL')
        {
            //支付结果回执失败，并记录表中

            Db::table('gada_wpayfail')
                ->insert(['return_message'=>$value_array['return_msg'],'fail_type'=>'支付回执失败','origin_string'=>$value_array_json]);
        }
       // \u4ed8\u5145\u503c","bank_type":"CFT","cash_fee":"100","fee_type":"CNY","is_subscribe":"N","mch_id":"1488123732","nonce_str":"0wx9a216ycu5btlis87jpm3gfeon4dzv","openid":"oft0G1Bm3j1Ai_EYp8oq5WWvfNEA","out_trade_no":"wbs221086651","result_code":"SUCCESS","return_code":"SUCCESS","sign":"ECBD2FD68B18C121D352CAA29A34378E","time_end":"20171109175440","total_fee":"100","trade_type":"APP","transaction_id":"4200000001201711093496004366"}
    }
    public function alipay($data)
    {
        $token = $data['token'];
        $uid = Db::table('gada_token')
            ->where('user_token',$token)
            ->value('user_id');
        $timestamp = time();
        $recharge_money = $data['recharge_money'];
        $recharge_number = Test::rechargeNumber();
        vendor('AopSdk');
        $ali = new \AopClient();
        $ali->appId =ALI_APP_ID;
        $ali->alipayrsaPublicKey = ALIPAY_RSA_PUBLICKEY;
        $ali->rsaPrivateKey= ALI_RSA_PRIVATEKEY;
        $ali->format='json';
        $ali->postCharset='UTF-8';
        $ali->signType='RSA2';
        $ali->gatewayUrl=ALI_GATEWAYURL;
        $request = new \AlipayTradeAppPayRequest();
        $content = json_encode([
            'body'=>'充值',
            'subject'=>'gadaboxpay',
            'out_trade_no'=>$recharge_number,
            'total_amount'=>$recharge_money,
            'product_code'=>'QUICK_MSECURITY_PAY'
        ]);
        $notify_url = ALI_NOTIFY_URL;
        $request->setNotifyUrl($notify_url);
        $request->setBizContent($content);
        $response = $ali->sdkExecute($request);

        //录入支付基础表
        Db::table('gada_paybasic')
            ->insert(['pay_order_number'=>$recharge_number,'total_amount'=>$recharge_money,'pay_status'=>'待支付','third_prepaid'=>'无','third_type'=>'支付宝','create_uid'=>$uid,'origin_string'=>$response]);

        //配合前端回调
        $data = ['order_info'=>$response];
        Response::returnApiSuccess(200,'反馈成功',$data);
        /*Db::table('gada_wpayfail')
            ->insert(['return_message'=>666,'fail_type'=>'ali','origin_string'=>$response]);*/
    }
    public function aliNotify()
    {
        vendor('AopSdk');
        $response0 = file_get_contents('php://input');
        $alipayrsaPublicKey = ALIPAY_RSA_PUBLICKEY;
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = ALIPAY_RSA_PUBLICKEY;
        $aop->appId =ALI_APP_ID;
        $aop->rsaPrivateKey= ALI_RSA_PRIVATEKEY;
        $aop->format='json';
        //验签通过后再实现业务逻辑，比如修改订单表中的支付状态。
        /**
        ①验签通过后核实如下参数trade_status、out_trade_no、total_amount、seller_id
        ②修改订单表
         **/
        //打印success，应答支付宝。必须保证本界面无错误。只打印了success，否则支付宝将重复请求回调地址。
        //echo 'success';
        //分割成数组键值对
        $response = explode('&',$response0);
        $array = [];
        foreach($response as $v)
        {
            $m = explode('=',$v);
            //务必要进行url解码否则验签会失败
            $array[$m[0]] = urldecode($m[1]);
        }

        //此处验签方式必须与下单时的签名方式一致

        $flag = $aop->rsaCheckV1($array,$alipayrsaPublicKey,"RSA2");
        if($flag ==true)
        {
           //验签通过
            if($array['trade_status'] == 'TRADE_SUCCESS')
            {
                //支付回复成功，核查基础表中数据真实性
                $paybasic_msg = Db::table('gada_paybasic')
                    ->where('pay_order_number',$array['out_trade_no'])
                    ->field('paybasic_id,total_amount,pay_status,create_uid')
                    ->find();

                if(empty($paybasic_msg['paybasic_id']))
                {
                    //该反馈为无效反馈（预支付表中并没此信息）
                    Db::table('gada_wpayfail')
                        ->insert(['return_message'=>'无','fail_type'=>'无效反馈','origin_string'=>$response0]);
                }
                else
                {
                    if($array['buyer_pay_amount']<=0)
                    {
                        //不进行充值处理
                    }
                    elseif($paybasic_msg['pay_status'] == '待支付' &&$array['buyer_pay_amount'] == $paybasic_msg['total_amount'] )
                    {
                        $is_success = Db::table('gada_paylog')
                            ->insert(['pay_order_number'=>$array['out_trade_no'],'total_amount'=>$paybasic_msg['total_amount'],'origin_string'=>$response0,'third_type'=>'支付宝','third_order_number'=>$array['trade_no']]);
                        if($is_success == 1) {
                            //确认无误执行充值操作
                            $time = date("Y-m-d H:i:s");
                            //变更预支付表中信息
                            Db::table('gada_paybasic')
                                ->where('pay_order_number', $array['out_trade_no'])
                                ->update(['pay_status' => '支付完成', 'actual_time' => $time]);
                            //对支付用户账户进行充值
                            $money = Db::table('gada_balance')
                                ->where('uid', $paybasic_msg['create_uid'])
                                ->field('amount,reserves')
                                ->find();
                            //$paybasic_msg['total_amount'] = $paybasic_msg['total_amount'] /100;
                            $money['amount'] += $paybasic_msg['total_amount'];
                            Db::table('gada_balance')
                                ->where('uid', $paybasic_msg['create_uid'])
                                ->update($money);
                            //记录交易明细
                            $current_balance = $money['reserves'] + $money['amount']; //当前余额
                            //保留两位小数方便观看
                            $current_balance = number_format($current_balance, 2);
                            Db::table('gada_transact')
                                ->insert(['user_id' => $paybasic_msg['create_uid'], 'transact_content' => '购买充值服务', 'transact_detail' => '+' . $paybasic_msg['total_amount'], 'current_balance' => $current_balance]);
                        }
                    }
                    else
                    {
                        //该订单已经处理过
                    }
                }
            }
        }
        else
        {
            //验签无效，记录下来
            Db::table('gada_wpayfail')
                ->insert(['return_message'=>'无','fail_type'=>'签名无效','origin_string'=>$response0]);
        }
        echo 'success';
    }
}