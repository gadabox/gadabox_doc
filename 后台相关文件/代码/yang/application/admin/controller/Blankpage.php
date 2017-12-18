<?php
namespace app\admin\controller;
use think\Controller;
class Blankpage extends Controller
{
    function blankpage()
    {
        return $this->fetch();
    }

}