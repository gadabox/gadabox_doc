<?php
namespace app\admin\controller;
use think\Controller;
class Composemail extends Controller
{
    function composemail()
    {
        return $this->fetch();
    }

}