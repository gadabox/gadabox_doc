<?php
/**
 * Created by PhpStorm.
 * User: yang
 * Date: 2017/10/26
 * Time: 17:18
 */
namespace app\box\controller;
header ( "Content-Type: application/json; charset=utf-8" );
//use app\index\controller\Response;
use \think\Controller;
use \think\Db;
use Workerman\Worker;
require_once __DIR__ . '/Workerman/Autoloader.php';
class Test
{
    public function test($data)
    {

    }
}
        // 创建一个Worker监听2346端口，使用websocket协议通讯
        $worker = new Worker("tcp://0.0.0.0:60000");

        // 启动1个进程对外提供服务
        $worker->count = 1;

        $worker->onWorkerStart = function($worker)
        {
            // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
            $inner_text_worker = new Worker('tcp://0.0.0.0:60969');
            $inner_text_worker->onMessage = function($connection, $data)
            {
                global $worker;
                // $data数组格式，里面有uid，表示向那个uid的页面推送数据
                //$data = json_decode($data, true);
                //$data = $buffer;
                //提取发送信息的前十位，为box_id
                $box_id = substr($data,1,10);
               // $uid = $data['box_code'];
               // $buffer = $data['msg'];
                // 通过workerman，向uid的页面推送数据
                $ret = sendMessageByUid($box_id, $data);
                // 返回推送结果
                $connection->send($ret ? 'ok' : 'fail');
            };
            $inner_text_worker->listen();
        };
        // 新增加一个属性，用来保存uid到connection的映射
        $worker->uidConnections = array();
        // 当有客户端发来消息时执行的回调函数
        $worker->onMessage = function($connection, $data)use($worker)
        {
            // 判断当前客户端是否已经验证,既是否设置了uid
            if(!isset($connection->uid))
            {
                // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
                $connection->uid = substr($data,1,10);;
                /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
                 * 实现针对特定uid推送数据
                 */
                $worker->uidConnections[$connection->uid] = $connection;
                return;
            }
            /*else
            {
                //箱子反馈非首次连接时的业务函数信息
                //移去逗号前箱子编号，截取实际返回函数的名称
                $function = substr($data,12);//中间10位是box_code
                //记录方法函数名的位置
                $n = strpos($data,',');
                $function = substr($function,$n-1);

            }*/
        };

        // 当有客户端连接断开时
        $worker->onClose = function($connection)use($worker)
        {
            global $worker;
            if(isset($connection->uid))
            {
                // 连接断开时删除映射
                unset($worker->uidConnections[$connection->uid]);
            }
        };

        // 向所有验证的用户推送数据
        function broadcast($message)
        {
            global $worker;
            foreach($worker->uidConnections as $connection)
            {
                $connection->send($message);
            }
        }

        // 针对uid推送数据
        function sendMessageByUid($uid, $message)
        {
            global $worker;
            if(isset($worker->uidConnections[$uid]))
            {
                $connection = $worker->uidConnections[$uid];
                $connection->send($message);
                return true;
            }
            return false;
        }

//119.80.123.153:60969

        // 运行worker
        Worker::runAll();
