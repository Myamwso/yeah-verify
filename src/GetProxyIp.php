<?php
  namespace hbattat;
/**
 * Execute the console command.
 *
 * @return mixed
 *
 * 使用方法：获取redis"mailsys:proxy_ip"哈希 下的proxyIp ，判断 expire_time 是否过期， 进程执行遇到proxyIp被封，判断proxyIp是否已经变更或过期，如果变更或过期了，无需操作，否则更新相应域名的值为0
 * 使用命令 nohup php artisan GetProxyIp > ~/GetProxyIp.log 2>&1 &     注意：<~/GetProxyIp.log>替换成日志输出日志文件，默认储存在家目录下的GetProxyIp.log
 */

class GetProxyIp
{
    public $hostList;
    public $proxyLog;
    public $redis;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        //设置中国时区
        date_default_timezone_set('PRC');
        $this->hostList = ['163.com'];
        $this->proxyLog = '/4T/www/huangj/proxyLog';

        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379); //连接Redis
        $this->redis->auth('*****'); //密码验证
        $this->redis->select(0);//选择数据库2
    }

    /**
     * 更新ip
     * @param $ipKey
     * @param $ipUrl
     * @param $hostList
     */
//    public function getProxyIp($ipKey, $ipUrl, $hostList) {
//        ///轮询是否需要更新ip
//        $isChange = 0;
//        $redisIpInfo = $this->redis->HgetAll($ipKey);
//        if ($redisIpInfo) {
//            foreach ($redisIpInfo as $key => $val) {
//                if ($key != 'proxyIp' && $key != 'proxyPort' && $key != 'expire_time' && $key != 'sendEmail' && $key != 'ipNum') {
//                    if ($val) {
//                        $isChange = 0;
//                        break;
//                    } else {
//                        $isChange = 1;
//                    }
//                }
//            }
//            if (isset($redisIpInfo['expire_time'])) {
//
//                ///转换ip过期时间
//                $expire_time = strtotime($redisIpInfo['expire_time']);
//            } else {
//
//                ///初始获取ip失败，执行重新获取ip
//                $isChange = 1;
//            }
//
//            /// ip过期或ip已经被各个端口封闭，重新获取ip
//            if ($isChange || $expire_time <= time()) {
//                $this->SetIpRedis($ipKey, $hostList, $ipUrl);
//            }
//        } else {
//            $this->SetIpRedis($ipKey, $hostList, $ipUrl);
//        }
//
//        sleep(1);
//    }

    /*
     *
     * 获取的Ip信息写入Redis
     *
     */
    public function setIpRedis($ipKey, $ipUrl){
        ///获取ip信息
        $ipInfo = $this->getProxyIp($ipUrl);
        if($ipInfo['error']==0){
            ///redis设置ip信息
            $setIpInfo = $ipInfo;
            unset($setIpInfo['error']);
            foreach($setIpInfo as $key => $val){
                $this->redis->Hset($ipKey, $key, $val);
            }
        }else{
            /// 三次请求ip失败发送邮件通知站长
            sleep(2);
            $result = $this->setIpRedis($ipKey, $ipUrl);
            return $result;
        }
        return true;
    }


    /*
     *
     * 获取的Ip信息
     * 第一次获取ip失败验证三次，三次都错误返回失败
     */
    private function getProxyIp($ipUrl){
        ///接口获取json格式ip地址
        $json_ip = $this->simple_get($ipUrl);

        $value = json_decode($json_ip, true);

        if($value['code']==0 && $value['success']){
            ///ip获取成功时，写入ip获取信息
            $dataDay = date("Y-m-d");
            // 文件夹不存在创建
            $path = $this->proxyLog."/{$dataDay }getIp.log";
            if (!is_dir($path)) {
                touch($path, 0777, true);
                chmod($path, 0777);
            }
            file_put_contents($path, date("Y-m-d H:i:d").":".$json_ip."\n", FILE_APPEND);

            ///代理ip新获取成功
            $res['error'] = 0;
            $res['proxyIp'] = $value['data'][0]['ip'];
            $res['proxyPort'] = $value['data'][0]['port'];
            $res['expire_time'] = $value['data'][0]['expire_time'];
        }else{
            ///代理ip信息获取失败
            $message = "code-" . $value['code'] . ",info-" . $value['msg'];
            ///判断是否重复验证接口
            file_put_contents($this->proxyLog . '/failer.log', date('Y-m-d H:i:s') .":". $message."\n", FILE_APPEND);
            $res = ['error'=>1, 'message' => $message, 'json_ip'=>$json_ip];
        }
        return $res;
    }

    public function simple_get($url)
    {
        $headers = ["content-type:text/html;charset=utf-8"];
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT,30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // curl接收返回数据
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $res = curl_exec($curl);
        //$httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $res;
    }

}
