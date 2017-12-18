<?php
namespace app\admin\controller;
use think\Controller;
class Inbox extends Controller
{
    function inbox()
    {
        return $this->fetch();
    }

}