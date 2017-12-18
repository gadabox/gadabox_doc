<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/12/4
 * Time: 17:42
 */
namespace app\admin\controller;
use think\Controller;
use think\Db;

class Change extends Controller
{
    function change()
    {
        session_start();
        if (isset($_SESSION['admin_id']))
        {
            return $this->fetch();
        }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
    function changePassword()
    {
        //修改登录密码,先读取session，验明身份
        session_start();
        $password = $_POST['password'];
        $password_again = $_POST['password_again'];
        if( $password == $password_again)
        {
            Db::table('gada_admin')
                ->where('aid',$_SESSION['admin_id'])
                ->update(['password'=>md5($password)]);
            echo "<script> alert('修改成功'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/index/index'>";
        }
        else
        {
            //修改失败
            echo "<script> alert('两次密码不一致'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/change/change'>";
        }
    }
}