<?php
/**
 * 安装包下载类.
 * User: yang
 * Date: 2017/8/23
 * Time: 17:57
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
Class Down extends controller
{
    public function down()
    {
        //先判断用户是否是微信访问
        if ($this->weixin())
        {
            //是微信访问
            $filename='./image/wechat.jpg';
            $size = getimagesize($filename);
            $fp = fopen($filename, "rb");
            if ($size && $fp)
            {
                header("Content-type: {$size['mime']}");
                fpassthru($fp);
                exit;
            }
            else
            {
                // error
            }
        }
        else
        {
            //非微信访问
//方法一
            //header("Content-type:text/html;charset=utf-8");
            header('Content-Type: application/vnd.android.package-archive');
            $file_name = "Gadabox-base11.apk";
            $file_name = iconv("utf-8", "gb2312", $file_name);
            $file_sub_path = '../runtime/';
            $file_path = $file_sub_path . $file_name;
            if (!file_exists($file_path)) {
                echo "没有该文件文件";
                return;
            }
            $fp = fopen($file_path, "r");
            $file_size = filesize($file_path);
            //下载文件需要用到的头
            Header("Content-type: application/octet-stream");
            Header("Accept-Ranges: bytes");
            Header("Accept-Length:" . $file_size);
            Header("Content-Disposition: attachment; filename=" . $file_name);
            $buffer = 1024;
            $file_count = 0;
            while (!feof($fp) && $file_count < $file_size) {
                $file_con = fread($fp, $buffer);
                $file_count += $buffer;
                echo $file_con;
            }
            ob_end_flush();
            fclose($fp);
//方法二
           /* $file_path = '../runtime/Gadabox-base3.apk';
            $file_name = "Gadabox-base3.apk";
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Type: application/octet-stream');
            header("Content-length: " . filesize($file_path));
            header('Content-Disposition: attachment; filename='.basename($file_name));
            ob_end_flush();
            readfile($file_path);
            return true;*/
        }
    }

    public function weixin()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false)
        {
            return true;
        }
        return false;
    }
}
