# 验证163邮箱注册情况

## 163注册验证
### 数据库信息
```
    $this->host = '127.0.0.1'; //主机
    $this->dbName = '*****'; //库名
    $this->userName = '*****'; //用户名
    $this->password = '*****'; //密码
    $this->mailTable = 'test'; //表名
```

### redis连接以及信息
```
    $this->redis = new \Redis(); 
    $this->redis->connect('127.0.0.1', 6379); //连接Redis主机以及端口
    $this->redis->auth('*****'); //密码验证
    $this->redis->select(0);//选择数据库0
```

### 163注册信息
   地址 https://reg.mail.163.com/unireg/call.do?cmd=register.entrance
   163验证接口地址 https://reg.mail.163.com/unireg/call.do?cmd=urs.checkName

## 获取代理ip

### 代理相关信息
```
    $this->proxyKey = 'mail_yeah:proxy_ip_0'; //设置代理的redis缓存
    $this->proxyValue = '获取代理地址';
```