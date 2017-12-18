<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/12/13
 * Time: 13:57
 */
namespace app\admin\controller;
use think\Controller;
use think\Db;
require_once ('Index.php');
class Addbox extends Controller
{
    function addbox()
    {
        session_start();
        if (isset($_SESSION['admin_id'])) {
            $admin_msg = Index::admin($_SESSION['admin_id']);
            $this->assign('username', $admin_msg['admin']);
            $this->assign('photo', $admin_msg['photo']);
            echo $this->fetch();
        }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
    function add()
    {
        $box_num = $_POST['box_num'];
        $box_area = $_POST['box_area'];
        $model_num = $_POST['model_num'];
        $box_code = $_POST['box_code'];
        if(!empty($box_num)&&!empty($box_area)&&!empty($model_num)&&!empty($box_code))
        {
            $is_success = Db::table('gada_box')
                ->insert(['box_num'=>$box_num,'box_area'=>$box_area,'model_num'=>$model_num,'box_code'=>$box_code]);
            if($is_success ==1) {
                echo "<script> alert('添加成功'); </script>";
                echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/tables/tables'>";
            }
            else{
                echo "<script> alert('输入信息有误，请重新输入'); </script>";
                echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/addbox/addbox'>";
            }
        }
        else
        {
            echo "<script> alert('输入信息不得为空，请重新输入'); </script>";
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/addbox/addbox'>";
        }
    }
}