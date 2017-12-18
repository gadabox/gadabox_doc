<?php
namespace app\admin\controller;
use think\Controller;
class Grids extends Controller
{
    function grids()
    {
        return $this->fetch();
    }

}