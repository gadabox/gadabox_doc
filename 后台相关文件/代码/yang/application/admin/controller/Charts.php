<?php
namespace app\admin\controller;
use think\Controller;
class Charts extends Controller
{
    function charts()
    {
        return $this->fetch();
    }

}