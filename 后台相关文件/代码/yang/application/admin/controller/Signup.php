<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;

class Signup extends Controller
{
    function signup()
    {
        return $this->fetch();
    }
    function register()
    {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $password_again = $_POST['password_again'];
        if($password == $password_again)
        {
            $password = md5($password);
            Db::table('gada_admin')
                ->insert(['admin'=>$username,'password'=>$password]);
            echo "<script> alert('申请成功，请耐心等待'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
        else
        {
            echo "<script> alert('输入信息有误，请重新输入'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signup/signup'>";
        }
    }

}