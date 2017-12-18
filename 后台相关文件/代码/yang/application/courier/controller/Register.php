<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/25
 * Time: 14:24
 */
namespace app\courier\controller;
use app\index\controller\Response;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
use \think\Db;
use app\courier\model\Identification;
Class Register
{
    public function register()
    {

        $courier_phone = $_POST['courier_phone'];
        $courier_name = $_POST['courier_name'];
        $courier_identification = $_POST['courier_ID'];
        //检验手机号是否重复
        $identification = new Identification;
        $is_phone = Db::table('gada_courier')
            ->where('cphone', $courier_phone)
            ->find();
        if (!empty($is_phone)) {
            Response::returnApiError(200, '该手机号已被注册');
        } else //手机号可注册
        {
            //先进行人脸比对创建身份信息字段id

            $i  = 1;
            $dirPath = './images/';
            foreach ($_FILES as $key => $value) {
                //循环遍历数据
                //$token = $value['token'];
                $tmp = $value['name'];//获取上传文件名
                $tmpName = $value['tmp_name'];//临时文件路径
                $create_time = date('YmdHis');
                //上传的文件会被保存到php临时目录，调用函数将文件复制到指定目录
                move_uploaded_file($tmpName, $dirPath . $create_time . '_' . $tmp);
                $content = '/public/images/' . $create_time . '_' . $tmp;

                if($i == 1) {
                    $identification->identification_front = $content;
                    $identification->create_time = date("Y-m-d H:i:s");
                    $identification->save();
                    $identification_id = $identification->identification_id;
                    $i++;

                }
                elseif($i ==2)
                {
                    Db::table('gada_identification')
                        ->where('identification_id',$identification_id)
                        ->update(['identification_back' => $content]);
                }
            }
                //录入信息到注册申请表
            $is_register = Db::table('gada_register')
                ->insert(['cname'=>$courier_name,'cphone'=>$courier_phone,'courier_identification'=>$courier_identification,'identification_id'=>$identification_id]);
            if($is_register == 1)
            {
                Response::returnApiOk(200,'提交成功，请等待审核通知');
            }
        }

    }
}