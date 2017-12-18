<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/30
 * Time: 15:29
 */
namespace app\box\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
class Sendbox
{
    //该类接口方法负责主动访问箱子的功能
    public function send_Msg($data)
    {
        //$data = {'box_code'=箱号,'msg'=内容}
        // 建立socket连接到内部推送端口
        $client = stream_socket_client('tcp://47.94.157.157:60969', $errno, $errmsg, 1);
        // 推送的数据，包含uid字段，表示是给这个uid推送
        //$data = 6;
        // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client,$data);
        // 读取推送结果
        echo fread($client, 8192);
    }
    public function read_GPS($box_code)
    {
        //主动请求读取箱子坐标
        $data = '#' . $box_code . ',' . 'READ_GPS';
        $this->send_Msg($data);
    }
    public function read_lock_status($box_code)
    {
        //主动请求读取箱子当前锁状态
        $data = '#' . $box_code . ',' . 'READ_LOCK_STATUS';
        $this->send_Msg($data);
    }
    public function set_lock($box_code,$lock_status)
    {
        //主动请求设置箱子当前锁状态 0 / 1
        $data = '#' . $box_code . ',' . 'SET_LOCK_0/1' . ',' . $lock_status;
        $this->send_Msg($data);
    }
    public function read_cover_status($box_code)
    {
        //主动请求读取箱子当前微动状态（盖盖情况）
        $data = '#' . $box_code . ',' . 'READ_COVER_STATUS';
        $this->send_Msg($data);
    }
    public function read_quality($box_code)
    {
        //主动请求读取箱子当前重量
        $data = '#' . $box_code . ',' . 'READ_QUALITY';
        $this->send_Msg($data);
    }
    public function read_gravity($box_code)
    {
        //主动请求读取箱子当前震动情况
        $data = '#' . $box_code . ',' . 'READ_GRAVITY';
        $this->send_Msg($data);
    }
    public function read_lead_angle($box_code)
    {
        //主动请求读取箱子当前倾斜情况
        $data = '#' . $box_code . ',' . 'READ_LEAD_ANGLE';
        $this->send_Msg($data);
    }
    public function read_charge($box_code)
    {
        //主动请求读取箱子当前电量信息
        $data = '#' . $box_code . ',' . 'READ_CHARGE';
        $this->send_Msg($data);
    }
    public function read_chargeing($box_code)
    {
        //主动请求读取箱子当前充电信息
        $data = '#' . $box_code . ',' . 'READ_CHARGEING';
        $this->send_Msg($data);
    }
    public function read_charged($box_code)
    {
        //主动请求读取箱子充电是否完成
        $data = '#' . $box_code . ',' . 'READ_CHARGED';
        $this->send_Msg($data);
    }
    public function play_sound($box_code,$sound)
    {
        //调起箱子放第几段音乐
        $data = '#' . $box_code . ',' . 'PLAY_SOUND' . ',' . $sound;
        $this->send_Msg($data);
    }
    public function blink_led($box_code,$led)
    {
        //主动操作箱子进行特定的指示灯闪烁
        $data = '#' . $box_code . ',' . 'BLINK_LED' . ',' . $led;
        $this->send_Msg($data);
    }
    public function get_firmvare_version($box_code)
    {
        //主动访问查看箱子当前版本
        $data = '#' . $box_code . ',' . 'GET_FIRMVARE_VERSION';
        $this->send_Msg($data);
    }
    public function update_firmvare($box_code)
    {

    }
    public function get_mode($box_code)
    {
        //主动访问箱子读取其当前模式状态
        $data = '#' . $box_code . ',' . 'GET_MODE';
        $this->send_Msg($data);
    }
    public function set_mode($box_code,$status)
    {
        //主动设置箱子当前模式状态
        $data = '#' . $box_code . ',' . 'SET_MODE' . ',' . $status;
        $this->send_Msg($data);
    }
    public function restart_device($box_code)
    {
        //初始化箱子
        $data = '#' . $box_code . ',' . 'RESTART_DEVICE';
        $this->send_Msg($data);
    }
    public function enable_module_0($box_code,$status)
    {
        //设置0异常警报的开关
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_0_ON' . ',' . $status;
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_0_OFF' . ',' . $status;
        $this->send_Msg($data);
    }
    public function enable_module_1($box_code,$status)
    {
        //设置1异常警报的开关
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_1_ON' . ',' . $status;
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_1_OFF' . ',' . $status;
        $this->send_Msg($data);
    }
    public function enable_module_2($box_code,$status)
    {
        //设置2异常警报的开关
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_2_ON' . ',' . $status;
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_2_OFF' . ',' . $status;
        $this->send_Msg($data);
    }
    public function enable_module_3($box_code,$status)
    {
        //设置3异常警报的开关
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_3_ON' . ',' . $status;
        $data = '#' . $box_code . ',' . 'ENABLE_MODULE_3_OFF' . ',' . $status;
        $this->send_Msg($data);
    }
}