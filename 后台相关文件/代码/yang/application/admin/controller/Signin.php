<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;

class Signin extends Controller
{
    function signin()
    {
        //先清理session
        session_start();
        unset($_SESSION['admin_id']);
        return $this->fetch();
    }
    function alignment()
    {
        //比对账号密码
        $username = $_POST['username'];
        $password = $_POST['password'];
        $msg = Db::table('gada_admin')
            ->where('admin',$username)
            ->find();
        if(md5($password) == $msg['password'])
        {
            //成功登录
            session_start();
            $_SESSION['admin_id']=$msg['aid'];
            echo "<script> alert('欢迎回来'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/index/index'>";
        }
        else
        {
            //账号或密码错误
            echo "<script> alert('账户信息有误'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }

    }
}