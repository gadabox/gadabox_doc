<?php
namespace app\admin\controller;
use think\Controller;
class Widgets extends Controller
{
    function widgets()
    {
        return $this->fetch();
    }

}