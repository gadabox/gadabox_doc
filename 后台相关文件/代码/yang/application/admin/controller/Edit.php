<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/12/13
 * Time: 15:29
 */
namespace app\admin\controller;
use think\Controller;
use think\Db;
require_once ('Index.php');
class Edit extends Controller
{
    function edit()
    {
        session_start();
        if (isset($_SESSION['admin_id']))
        {
            $admin_msg = Index::admin($_SESSION['admin_id']);
            $this->assign('username', $admin_msg['admin']);
            $this->assign('photo', $admin_msg['photo']);
            $box_num = $_GET['box_num'];
            $edit = Db::table('gada_box')
                ->where('box_num',$box_num)
                ->find();
            $this->assign('box_num', $edit['box_num']);
            $this->assign('box_code', $edit['box_code']);
            echo $this->fetch();
        }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
    function editbox()
    {
        $box_num = $_POST['box_num'];
        $box_area = $_POST['box_area'];
        $model_num = $_POST['model_num'];
        $box_code = $_POST['box_code'];
        $box_msg = Db::table('gada_box')
            ->where('box_num',$box_num)
            ->field('box_code,model_num')
            ->find();
        if($box_msg['box_code'] == $box_code && empty($box_msg['model_num']))
        {
            $is_success = Db::table('gada_box')
                ->where(['box_num'=>$box_num,'box_code'=>$box_code])
                ->update(['box_area'=>$box_area,'model_num'=>$model_num]);
            if($is_success == 1)
            {
                echo "<script> alert('完善信息成功，可在停用列表中查询'); </script>";
                echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/tables/tables'>";
            }
            else
            {
                echo "<script> alert('完善信息失败，请勿修改初始编号和标识码'); </script>";
                echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/tables/tables'>";
            }
        }
        else
        {
            echo "<script> alert('完善信息失败，请勿修改初始编号和标识码'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/tables/tables'>";
        }
    }
}