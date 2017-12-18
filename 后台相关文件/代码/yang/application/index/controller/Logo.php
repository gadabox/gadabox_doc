<?php
/**
 * 闪屏页渲染.
 * User: yang
 * Date: 2017/8/28
 * Time: 9:36
 */
namespace app\index\controller;
header ( "Content-Type: application/json; charset=utf-8" );
use \think\Controller;
class Logo extends Controller
    {
        function logo()
        {
            return $this->fetch();
        }

    }