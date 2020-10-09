# think-addon

thinkphp6 addon support

半成品请勿使用

## 安装

```shell
composer require isszz/think-addon -vvv
```

> 接下来将字体放入tp根目录下的config/font目录

## 配置

```php
<?php

// addon配置
return [
    // 不允许作为扩展的关键词
    'sysList' => ['install', 'admin', 'adm', 'index', 'common', 'store', 'user', 'api', 'article', 'pay', 'public', 'app'],
];
```

## 使用方法

在系统根目录创建addon目录

## 快速生成扩展

```shell
php think addon demo
```

返回`Successed`则说明成功

生成后我们就可以访问demo扩展

```
├─addon 扩展目录
│  ├─index              主模型相关
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  ├─view            视图目录
│  │  └─ ...            更多类库目录
│  │ 
│  ├─admin              后台模型相关
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  ├─view            视图目录
│  │  └─ ...            更多类库目录
```

## 访问扩展说明

默认访问扩展时优先查找: 模型 -> 控制器 -> 方法