<?php

return [

    /*
     * 账号基本信息，请从微信公众平台/开放平台获取
     */
    'app_id'  => env('WECHAT_APPID', ''),         // AppID
    'secret'  => env('WECHAT_SECRET', ''),     // AppSecret
    'token'   => env('WECHAT_TOKEN', 'TOKEN'),          // Token
    'aes_key' => env('WECHAT_AES_KEY', ''),                // EncodingAESKey

    /**
     * 小程序配置信息
     */
    'mini_program' => [
        'app_id'  => env('WECHAT_MINI_PROGRAM_APPID', ''),
        'secret'  => env('WECHAT_MINI_PROGRAM_SECRET', ''),
        'token'   => env('WECHAT_MINI_PROGRAM_TOKEN', ''),
        'aes_key' => env('WECHAT_MINI_PROGRAM_AES_KEY', ''),
    ],

    /*
     * 微信支付
     */
    'payment' => [
        'merchant_id'        => env('WECHAT_PAYMENT_MERCHANT_ID', ''),
        'key'                => env('WECHAT_PAYMENT_KEY', ''),
        'cert_path'          => env('WECHAT_PAYMENT_CERT_PATH', 'path/to/your/cert.pem'), // XXX: 绝对路径！！！！
        'key_path'           => env('WECHAT_PAYMENT_KEY_PATH', 'path/to/your/key'),      // XXX: 绝对路径！！！！
    ]
];
