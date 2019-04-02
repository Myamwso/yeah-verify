<?php
  namespace myamwso;
  /**
   *  Verifies email address by attempting to connect and check with the mail server of that account
   *
   *  Author: huangjie 362223016@qq.com
   *          https://github.com/Myamwso/yeah-verify
   *
   *  License: This code is released under the MIT Open Source License. (Feel free to do whatever)
   *
   *  Last update: Oct 11 2016
   *
   * @package VerifyEmail
   * @author  huangjie Battat <362223016@qq.com>
   * This is a test message for packagist
   */

  class VerifyEmail {
    //数据库链接信息
    public $host;
    public $dbName;
    public $userName;
    public $password;

    public $email;
    public $verifier_email;
    public $port;
    private $errors;
    private $recordLog;
    private $resultFile;
    private $_yeah_signup_page_url = 'https://reg.mail.163.com/unireg/call.do?cmd=register.entrance';
    private $_yeah_signup_ajax_url = 'https://reg.mail.163.com/unireg/call.do?cmd=urs.checkName';
    private $_yeah_domains = array('163.com','126.com','yeah.net');
    public $proxyIp;
    public $proxyPort;
    public $cookies;
    public $back_code = [
        'success' => ['status' => 1],
        'error' => ['status' => 2, 'msg' => 'Proxy Connect Failed'],
        'warning' => ['status' => 3, 'msg' => '邮件域名有误'],
        'sysError' => ['status' => 4, 'msg' => 'ACCESS DENY']
    ];
    public $mailTable;
    public $proxy;
    public $proxyKey;
    public $redis;
    public $redisKey = [
        'mailSuccessKey' => 'mail_yeah:checkMail:success', //验证成功的条数
        'mailFailKey' => 'mail_yeah:checkMail:fail', //验证失败的条数
        'readKey' => 'mail_yeah:mail_yeah_cur_fseek', //验证的邮箱当前读到第几行
        'nullKey' => 'mail_yeah:mail_yeah_null_num' //连续100行无数据则自动退出
    ];

    public function __construct($port = 25){
      $this->recordLog = '/4T/www/huangj/mailLog';
      $this->cookies = '/4T/www/huangj/mailLog/cookie.txt';
      $this->resultFile = '/4T/www/huangj/yeah';
      $this->host = '127.0.0.1';
      $this->dbName = 'ym_mail';
      $this->userName = 'ym_mail';
      $this->password = 'sKSdiGjbaR';
      $this->mailTable = 'test';
      $this->proxy = new GetProxyIp();
      $this->proxyKey = 'mail_yeah:proxy_ip_0';
      $this->proxyValue = '*****';
      $this->set_port($port);

      $this->redis = new \Redis();
      $this->redis->connect('127.0.0.1', 6379); //连接Redis
      $this->redis->auth('dw5w@%wZx'); //密码验证
      $this->redis->select(0);//选择数据库2
    }

    public function set_proxy_info($proxyIp, $proxyPort) {
        $this->proxyIp = $proxyIp;
        $this->proxyPort = $proxyPort;
    }

    public function set_verifier_email($email) {
      $this->verifier_email = $email;
    }

    public function get_verifier_email() {
      return $this->verifier_email;
    }


    public function set_email($email) {
      $this->email = $email;
    }

    public function get_email() {
      return $this->email;
    }

    public function set_port($port) {
      $this->port = $port;
    }

    public function get_port() {
      return $this->port;
    }

    public function get_errors(){
      return array('errors' => $this->errors);
    }
    
      /**
       * 验证yeah邮箱注册
       * @return array|mixed
       */
    public function verify() {
      $result = [];
      //check if this is a yeah email
      $domain = $this->get_domain($this->email);

      if(in_array(strtolower($domain), $this->_yeah_domains)) {
        $result = $this->validate_yeah();
      }
      //otherwise check the normal way
      else {
        $result = $this->back_code['warning'];
      }
      return $result;
    }

    private function get_domain($email) {
      $email_arr = explode('@', $email);
      $domain = array_slice($email_arr, -1);
      return $domain[0];
    }

    private function add_error($code, $msg) {
      $this->errors[] = array('code' => $code, 'message' => $msg);
    }

    private function clear_errors() {
      $this->errors = array();
    }

    private function validate_yeah() {
      $pageRes = [];
      $pageRes = $this->fetch_page();
      ///代理失败返回
      if ($pageRes['status'] != 1) {
          return $pageRes;
      }

      $response = $this->request_validation();
      return $response;
    }


    private function fetch_page(){
      $page = [];
      $page = $this->proxy_curl($this->_yeah_signup_page_url, $this->proxyIp, $this->proxyPort);
      return $page;
    }

    private function request_validation(){
        $data = [];
        $data['name'] = explode("@",$this->email)[0];

        $result = $this->proxy_curl($this->_yeah_signup_ajax_url, $this->proxyIp, $this->proxyPort, $data, $this->cookies);
      return $result;
    }

      /**
       * curl获取163注册端口
       * @param $url
       * @param $proxy
       * @param $proxyPort
       * @param string $data
       * @param string $cookie
       * @return mixed
       */
    private function proxy_curl($url, $proxy, $proxyPort, $data='', $cookie = ''){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PROXY, $proxy); //代理ip
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort); //代理端口
//        if ( $cookie ) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1); //管道
//        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies); //cookie
        } else {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $file = $this->recordLog. '/yeah.log';
        $op = fopen($file, "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Uncomment to see the transaction
        curl_setopt($ch, CURLOPT_STDERR, $op);
        fwrite($op,"##{$proxy}###\n");
        $res = curl_exec($ch);
        curl_close($ch);
        fclose($op);

        $file_content_temp = file_get_contents($file);
        $file_content = explode("##{$proxy}###", $file_content_temp);
        $array = explode(PHP_EOL, $file_content[count($file_content)-1]);
        
        foreach ($array as $k => $v) {
            if (preg_match('/Recv failure: Connection reset by peer/', $v)) { ///proxy connect
                return $this->back_code['error'];
            }
            else if (preg_match("/connect to {$proxy} port {$proxyPort} failed/", $v)) {///proxy failed
                return $this->back_code['error'];
            }
            else if (preg_match("/Connection timed out after/", $v)) {///Connection timed out
                return $this->back_code['error'];
            }
        }

        if (isset($res['code'])) {
            ///ip被封
            if ($res['code'] == 400) {
                return $this->back_code['sysError'];
            }
        }
//        else {
//            if ($cookie) {
//                var_dump('无cookie等问题');
//                unlink($this->cookies);
//                $proxyResult = $this->proxy_curl($url, $proxy, $proxyPort, $data);
//                return $proxyResult;
//            }
//        }

        $this->back_code['success']['msg'] = $res;
        return $this->back_code['success'];
    }

    /**
    * 获取yeah邮箱
    * @param $type
    * @param $i
    * @param WhiteList $mWhiteList
    * @return bool
    */
    public function getMail() {
        var_dump("--------------验证开始-----------------");
        $DB = new \PDO("mysql:host={$this->host};dbname={$this->dbName}", $this->userName, $this->password);
        while (1) {
            $readKey = $this->redisKey['readKey'];
            $nullKey = $this->redisKey['nullKey'];
            $file = $this->resultFile . "/originCheck.txt";
            ///连续100行无数据则自动退出
            $nullNum = $this->redis->get($nullKey);
            if ($nullNum > 100) {
                //            $this->_log("{$type} {$i}|=> 已连续100行无数据，自动退出子进程");
                $this->redis->del($nullKey);
                var_dump('------------验证结束------------');
                break;
            }

            //并发锁,防止死锁60s后，未释放，自动释放

            $fseek = $this->redis->get($readKey);
            $line = $this->_getLine($file, $fseek); //去单行文本数据
            $fseek = $line['fseek'];
            $mail = trim($line['str']);
            $this->redis->set($readKey, $fseek);
//                $this->redis->del($lockKey);//并发锁 - 去锁
            if (!empty($mail)) {
                $domain = $this->get_domain($mail);
                if (in_array(strtolower($domain), $this->_yeah_domains)) {
                    ///获取代理ip
                    $proxy = $this->getProxyIp($this->proxyKey);
                    ///163邮件注册验证
                    $this->_verifyEmail($DB, $proxy, $mail);
                } else {
                    var_dump('tuichu');
                    continue;
                }
            } else {
                $this->redis->incr($nullKey);
            }
        }
    }

    private function _verifyEmail ($DB, $proxy, $mail) {
        $this->set_proxy_info($proxy['proxyIp'], $proxy['proxyPort']);
        $this->set_email($mail);
        $result = $this->verify();

        if ($result['status'] == 1) {
            ///注册验证成功
            var_dump("pass: {$mail}");
            var_dump($result);
            $this->redis->incr($this->redisKey['mailSuccessKey']);
            $insertData = [
                'email' => $mail,
                'status' => 1
            ];
            ///处理具体的返回的邮件信息
            $yeahArray = json_decode($result['msg'], true);
            if (isset($yeahArray['result']['126.com']) && $yeahArray['result']['126.com'] == 1) {
                $insertData['one_two_six'] = 1;
            }
            if (isset($yeahArray['result']['163.com']) && $yeahArray['result']['163.com'] == 1) {
                $insertData['one_six_three'] = 1;
            }
            if (isset($yeahArray['result']['yeah.net']) && $yeahArray['result']['yeah.net'] == 1) {
                $insertData['yeah'] = 1;
            }
            $this->insert($DB, $this->mailTable, $insertData);
            return true;
        } elseif($result['status'] == 2 || $result['status'] == 4) {
            var_dump($result);
            sleep(1);
            $this->proxy->setIpRedis($this->proxyKey, $this->proxyValue);
            $proxyAgain = $this->getProxyIp($this->proxyKey);
            $this->set_proxy_info($proxyAgain['proxyIp'], $proxyAgain['proxyPort']);
            var_dump("发送邮件信息->{$mail}");
            $this->set_email($mail);
            $result = $this->_verifyEmail($DB, $proxyAgain, $mail);
            return $result;
        } elseif ($result['status'] == 3) {
            var_dump("fail: {$mail}");
            file_put_contents($this->resultFile . '/fail.log', date('Y-m-d H:i:s') .":". $mail."\n", FILE_APPEND);
            $this->redis->incr($this->redisKey['mailFailKey']);
            return true;
        } else {
            var_dump("fail: {$mail}");
            file_put_contents($this->resultFile . '/fail.log', date('Y-m-d H:i:s') .":". $mail."\n", FILE_APPEND);
            $this->redis->incr($this->redisKey['mailFailKey']);
            return true;
        }
    }

    /**
    * 获取指定文件的指定指针的单行数据
    * @param $file
    * @param int $fseek
    * @return array
    */
    private function _getLine($file, $fseek=0) {
      $handle = fopen($file, 'r');
    
      fseek($handle, $fseek);
      $str = fgets($handle);
    
      return [
          'str' => trim($str),
          'fseek' => ftell($handle)
      ];
    }

      /**
       * 获取代理ip
       * @return mixed
       */
      public function getProxyIp($proxyIpKey) {
          $proxy = $this->redis->hgetAll($proxyIpKey);

          if (isset($proxy['expire_time']) && strtotime($proxy['expire_time']) > time()) {
              return $proxy;
          } else {
              sleep(1);
              $this->proxy->setIpRedis($proxyIpKey, $this->proxyValue);
              $proxy = $this->getProxyIp($proxyIpKey);
              return $proxy;
          }
      }

      //插入数据
      public function insert($pdo, $table, $data=[]){
          //	创建sql预处理语句
          $sql = "INSERT IGNORE {$table} SET ";
          foreach(array_keys($data) as $fileld){
              $sql .= $fileld.'=:'.$fileld.', ';
          }
          //去除sql语句的左右空格 并去除右边的逗号
          $sql = rtrim(trim($sql),',').';';

          //创建pdo预处理对象
          $stmt = $pdo->prepare($sql);
          //绑定参数到预处理对象
          foreach($data as $fileld => $value){
              $stmt->bindValue(":{$fileld}",$value);
          }
          //执行新增操作
          if($stmt->execute()){
              if($stmt->rowCount()>0){
                  return true;
              }
          }else{
              return false;
          }
      }

      /**
           * @param $data
           * @return $this
           *  更新 修改
           */
      public function update($pdo, $table, $data, $where) {
          if(!is_array($data)){
              return false;
          }
          $set = array();
          foreach($data as $key => $val){
              $set[] = $key . "='" . $val  . "'";
          }
          $sql = "UPDATE {$table} SET ";
          $sql .= implode(",", $set);
          $sql .= " WHERE " . $where;
          $res = $pdo->query($sql);
          return $res->rowCount();
      }


      /**
       * 获取pdo的数组结果集
       * @param $DB
       * @param $sql
       * @return mixed
       */
      public function getPdoResult ($DB, $sql) {
          $ob = $DB->prepare($sql);
          $ob->execute();
          $result = $ob->fetch($DB::FETCH_ASSOC);
          return $result;
      }

      /**
       * 获取pdo的所有数组结果集
       * @param $DB
       * @param $sql
       * @return mixed
       */
      public function getPdoAllResult ($DB, $sql) {
          $ob = $DB->prepare($sql);
          $ob->execute();
          $result = $ob->fetchAll($DB::FETCH_ASSOC);
          return $result;
      }

  }

