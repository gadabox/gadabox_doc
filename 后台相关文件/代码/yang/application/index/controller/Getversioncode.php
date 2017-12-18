<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/8/24
 * Time: 9:31
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
use think\Db;
//use think\Response;


require_once ('Response.php');
require_once ("Down.php");
Class GetVersionCode extends controller
    {
        public function getversioncode()
        {
            //$app_version = file_get_contents('php://input');
            //$version_time = base64_decode($version_time['version_time']);
            //$app_version_code = $app_version['version_code'];
            $server_version = Db::table('gada_version')
                                ->order('version_create_time desc')
                                ->limit(1)
                                ->field('version_code,version_url,version_name,splash_code,splash_url,splash_image')
                                ->find();
            //if($server_version['version_code'] > $app_version_code or $server_version['version_code'] < $app_version_code)
            //{
            //  $url = ['version_url' => $server_version['version_url']];
           // print_r($server_version);
                Response::returnApiSuccess(200,'反馈成功',$server_version);
        }
    }