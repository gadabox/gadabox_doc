<?php
namespace app\admin\controller;
use think\Controller;
class Codes extends Controller
{
    function codes()
    {
        return $this->fetch();
    }

}