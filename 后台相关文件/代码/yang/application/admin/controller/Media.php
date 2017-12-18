<?php
namespace app\admin\controller;
use think\Controller;
class Media extends Controller
{
    function media()
    {
        return $this->fetch();
    }

}