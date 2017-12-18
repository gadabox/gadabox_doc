<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
require_once ('Index.php');
class Forms extends Controller
{
    function forms()
    {
        session_start();
        if (isset($_SESSION['admin_id'])) {
            $admin_msg = Index::admin($_SESSION['admin_id']);
            $register = Db::table('gada_register')
                ->where('is_register', 0)
                ->select();
            $this->assign('register', $register);
            $this->assign('username', $admin_msg['admin']);
            $this->assign('photo', $admin_msg['photo']);
            echo $this->fetch();
        }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
    function pass()
    {
        //通过审核
        $phone = $_POST['phone'];
        Db::table('gada_register')
            ->where('cphone', $phone)
            ->update(['is_register' => 1]);
        $data = ['status' => 1];
        return $data;
    }
    function reject()
    {
        //拒绝申请
        $phone = $_POST['phone'];
        Db::table('gada_register')
            ->where('cphone', $phone)
            ->update(['is_register' => 2]);
        $data = ['status' => 1];
        return $data;
    }
}