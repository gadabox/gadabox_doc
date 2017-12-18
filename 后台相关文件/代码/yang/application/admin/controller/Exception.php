<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/12/4
 * Time: 18:30
 */
namespace app\admin\controller;
use think\Controller;
use think\Db;
require_once ('Index.php');
class Exception extends Controller
{
    function exception()
    {
        session_start();
        if (isset($_SESSION['admin_id'])) {
        $admin_msg = Index::admin($_SESSION['admin_id']);
        $exception = Db::table('gada_exception')
            ->join('gada_courier', 'gada_exception . courier_id = gada_courier . id_courier')
            ->field('cname,exception_type,content,order_number')
            ->select();
        $n = count($exception);
        for ($i = 0; $i < $n; $i++) {
            if ($exception[$i]['exception_type'] == 'change_address') {
                $exception[$i]['exception_type'] = '修改配送地址';
            }
            if ($exception[$i]['exception_type'] == 'no_receiver') {
                $exception[$i]['exception_type'] = '无人签收';
            }
            if (empty($exception[$i]['content'])) {
                $exception[$i]['content'] = '无特殊备注';
            }
        }
        $this->assign('username', $admin_msg['admin']);
        $this->assign('photo', $admin_msg['photo']);
        $this->assign('exception', $exception);
        $i = 1;
        $this->assign('i', $i);
        echo $this->fetch();
    }
        else
        {
            echo "<meta http-equiv='Refresh' content='0;URL=http://47.94.157.157/admin/signin/signin'>";
        }
    }
}
