<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
require_once ('Index.php');
class Tables extends Controller
{
    function tables()
    {
        session_start();
        if (isset($_SESSION['admin_id'])) {
            $admin_msg = Index::admin($_SESSION['admin_id']);
            $box = Db::table('gada_box')
                ->where([
                    'box_error' => 0,
                    'add_error' => 0,
                    'shake_error' => 0,
                    'open_error' => 0,
                    /*'longitude' => !null,
                    'box_area' => !null*/
                ])

                ->select();
            $error_all = Db::table('gada_box')
                ->where('add_error', 1)
                ->whereor('shake_error', 1)
                ->whereor('open_error', 1)
                ->where('box_error', 0)
                ->select();
            $n = count($error_all);
            for ($i = 0; $i < $n; $i++) {
                if ($error_all[$i]['add_error'] == 1) {
                    $error_all[$i]['error'] = '坐标报警';
                }
                if ($error_all[$i]['shake_error'] == 1) {
                    $error_all[$i]['error'] = '微动报警';
                }
                if ($error_all[$i]['open_error'] == 1) {
                    $error_all[$i]['error'] = '防撬报警';
                }
            }
            $stop = Db::table('gada_box')
                ->where([
                    'box_error' => 1,
                    /* 'longitude' => !null,
                    'box_area' => !null*/
                ])
                ->where('longitude','not null')
                ->where('box_area','not null')
                ->select();
            //建立连接之前主动添加箱子信息
            $new = Db::table('gada_box')
                ->where('longitude',null)
                ->select();
            //待完善列表
            $to_add = Db::table('gada_box')
                ->where('box_area',null)
                ->select();
            $i = 1;     //正常箱的展示序号
            $j = 1;     //异常箱的展示序号
            $k = 1;     //暂停箱的展示序号
            $l = 1;     //新增箱的展示序号
            $m = 1;     //待完善信息箱的展示序号
            $this->assign('i', $i);
            $this->assign('j', $j);
            $this->assign('k', $k);
            $this->assign('l', $l);
            $this->assign('m', $m);
            $this->assign('box', $box);
            $this->assign('error', $error_all);
            $this->assign('stop', $stop);
            $this->assign('new', $new);
            $this->assign('to_add',$to_add);
            $this->assign('username', $admin_msg['admin']);
            $this->assign('photo', $admin_msg['photo']);
            echo $this->fetch();
        }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
        function unlock()
        {
            //开锁
            $box_num = $_POST['box_num'];
            Db::table('gada_box')
                ->where('box_num', $box_num)
                ->update(['is_lock' => 0]);
            $data = ['status' => 1];
            return $data;
        }

        function offline()
        {
            //箱子停用
            $box_num = $_POST['box_num'];
            Db::table('gada_box')
                ->where('box_num', $box_num)
                ->update(['box_error' => 1]);
            $data = ['status' => 1];
            return $data;
        }

        function error_offline()
        {
            //箱子停用
            $box_num = $_POST['box_num'];
            Db::table('gada_box')
                ->where('box_num', $box_num)
                ->update(['box_error' => 1]);
            $data = ['status' => 1];
            return $data;
        }

        function restart()
        {
            //箱子重启
        }

        function error_restart()
        {
            //箱子重启
        }

        function clear()
        {
            //清除警报
            $box_num = $_POST['box_num'];
            Db::table('gada_box')
                ->where('box_num', $box_num)
                ->update(['add_error' => 0, 'shake_error' => 0, 'open_error' => 0]);
            $data = ['status' => 1];
            return $data;
        }

        function work()
        {
            //箱子上线
            $box_num = $_POST['box_num'];
            Db::table('gada_box')
                ->where('box_num', $box_num)
                ->update(['box_error' => 0]);
            $data = ['status' => 1];
            return $data;
        }

}