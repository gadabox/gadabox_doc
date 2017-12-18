<?php

	namespace app\admin\controller;
    use app\index\model\Order;
    use think\Controller;
    use think\Db;

    class Index extends Controller
    {
        function index()
        {
            session_start();
            if(isset($_SESSION['admin_id'])){
                $admin_msg = Index::admin($_SESSION['admin_id']);
                //昨日订单数
                $yesterday=date('Y-m-d',strtotime("-1 day"));
                $yesterday_now = date('Y-m-d H:i',strtotime("-1 day"));
                $today = date('Y-m-d 00:00:00');
                $yesterday_order = Db::table('gada_order')
                    ->where('order_over_time','between',[$yesterday,$today])
                    ->count();
                $this -> assign('yesterday',$yesterday_order);
                //本月新注册用户
                $start=date('Y-m-01 00:00:00');
                $end = date('Y-m-d H:i:s');
                $new_user = Db::table('gada_user')
                    ->where('create_time','between',[$start,$end])
                    ->count();
                $this -> assign('new_user',$new_user);
                //本月活跃用户
                $active_users = Db::table('gada_user')
                    ->where('last_login_time','between',[$start,$end])
                    ->count();
                $this -> assign('active_users',$active_users);
                //本月充值收入
                $month_income = Db::table('gada_paylog')
                    ->where('create_time','between',[$start,$end])
                    //->whereTime('create_time','last month')
                    ->sum('total_amount');
                $this -> assign('month_income',$month_income);

                //今日统计
                $today_dispatch_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$today,$end])
                    ->sum('order_price');
                $today_second_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$today,$end])
                    ->sum('second_price');
                $today_order_income = $today_dispatch_income + $today_second_income;
                $this -> assign('today_order_income',$today_order_income);
                $today_order_count =  Db::table('gada_order')
                    ->where('order_over_time','between',[$today,$end])
                    ->count();
                $this -> assign('today_order_count',$today_order_count);
                $yesterday_dispatch_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$yesterday,$yesterday_now])
                    ->sum('order_price');
                $yesterday_second_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$yesterday,$yesterday_now])
                    ->sum('order_price');
                $yesterday_order_income = $yesterday_dispatch_income + $yesterday_second_income;
                $result = $today_order_income - $yesterday_order_income;
                $symbol = '+';
                if($result<0)
                {
                    $symbol = '-';
                }
                if($yesterday_order_income == 0)
                {
                    $today_percent = '此时还没收入';
                }
                else
                {
                    $today_percent = $symbol . (abs($result)/$yesterday_order_income*100) . '%';
                }
                $this -> assign('today_percent',$today_percent);

                //本月统计
                $month_dispatch_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$start,$end])
                    ->sum('order_price');
                $month_second_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$start,$end])
                    ->sum('second_price');
                $month_order_income = $month_dispatch_income + $month_second_income;
                $this -> assign('month_order_income',$month_order_income);
                $month_order_count =  Db::table('gada_order')
                    ->where('order_over_time','between',[$start,$end])
                    ->count();
                $this -> assign('month_order_count',$month_order_count);
                $last_month_dispatch_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$start,$end])
                    ->sum('order_price');
                $last_month_second_income = Db::table('gada_order')
                    ->where('order_over_time','between',[$start,$end])
                    ->sum('order_price');
                $last_month_order_income = $last_month_dispatch_income + $last_month_second_income;
                $month_result = $month_order_income - $last_month_order_income;
                $symbol = '+';
                if($month_result<0)
                {
                    $symbol = '-';
                }
                if($last_month_dispatch_income == 0)
                {
                    $month_percent = '此时还没收入';
                }
                else
                {
                    $month_percent = $symbol . (abs($month_result)/$last_month_order_income*100) . '%';
                }
                $this -> assign('month_percent',$month_percent);

                //总记录
                $total_dispatch_income = Db::table('gada_order')
                    ->sum('order_price');
                $total_second_income = Db::table('gada_order')
                    ->sum('second_price');
                $total_income = $total_dispatch_income + $total_second_income;
                $this -> assign('total_income',$total_income);
                $total_order_count = Db::table('gada_order')
                    ->where('order_type',1)
                    ->count();
                $this -> assign('total_order_count',$total_order_count);
                $max_order = Db::table('gada_order')
                    ->max('order_price');
                $this -> assign('max_order',$max_order);
                $this->assign('username', $admin_msg['admin']);
                $this->assign('photo', $admin_msg['photo']);
                return $this->fetch();
            }
            else
            {
                echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
            }
        }
        function forms()
        {
            return $this->fetch();
        }
        static function  admin($admin_id)
        {
            $admin = Db::table('gada_admin')
                ->where('aid',$admin_id)
                ->field('admin,pic_id')
                ->find();
            $photo = Db::table('gada_photo')
                ->where('pic_id',$admin['pic_id'])
                ->value('content');
            $admin_msg = ['admin'=>$admin['admin'],'photo'=>$photo];
            return $admin_msg;
        }
}