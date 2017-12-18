<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/9/30
 * Time: 11:55
 */
namespace app\courier\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
const UNFINISHED = 0;            //未完成订单类 type = 0
const COMPLETED  = 1;            //已完成订单类 type = 1
const SPECIAL    = 2;            //特殊订单类 type = 2
const PAID       = 0;            //已支付订单 type = 0 status = 0
const PUSHED     = 1;            //已推送快递订单 type = 0 status = 1
const DISPATCH   = 2;            //已提箱配送中订单 type = 0 status = 2
const DELIVERED  = 3;            //已送达的订单 type = 0 status = 3
const OCCUPY     = 4;            //占用中的订单 type = 0 status = 4
const SECOND     = 5;            //二次配送订单 type = 0 status = 5
const SECONDED   = 6;            //二次送达订单 type = 0 status = 6
const RECEIVED   = 0;            //已签收订单  type = 1 status = 0
const OCCUPYED   = 1;            //占用完成订单 type = 1 status = 1
const CHANGE     = 2;            //转配送订单 type = 1 status = 2
const TIMEOUT_TAKESTOCK     = 3;    //超时的自取订单  type = 1 status ==3 （包含占用过夜自取和无人签收过夜自取）
const NO_RECEIVE_TAKESTOCK  = 4;    //未签收自取订单  type = 1 status ==4(当日不计费)
const SECOND_RECEIVED       = 5;    //已完成的二次配送订单  type = 1 status ==5
const CURRENT    = 0;               //正在进行中的隐藏订单 type = 2 status = 0
const CANCEL_DISPATCH       = 1;    //取消配送的订单（扣费）
const TIMEOUT    = 2;               //超时的订单(过夜订单)
const NO_RECEIVE = 3;               //未签收的订单
const UN_USE_NOPAID  = 4;           //取消使用（未扣费）的订单
const FORCE_CLOSE    = 5;           //被快递关闭箱子的订单
const IS_READ_PAID   = 6;           //已阅读的快递关闭箱子的订单（扣费）
const UN_USE_PAID    = 7;           //取消使用（扣费）的订单
const IS_READ_NOPAID        = 8;    //已阅读的快递关闭箱子订单（未扣费）
const NO_RECEIVE_TIMEOUT    = 9;    //无人签收的超时订单  type = 2 status =9（过夜）
const CANCEL_DISPATCH_NOPAID = 10;  //取消配送的订单(未扣费)
const LOCK       = 1;               //箱子已锁
const UNLOCK     = 0;               //箱子未锁
const CLOSE      = 1;               //箱盖已盖
const OPEN       = 0;               //箱盖未盖