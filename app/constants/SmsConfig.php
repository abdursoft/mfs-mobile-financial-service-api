<?php
namespace App\constants;

class SmsConfig
{

    public static $smsMethods = [
        [
            "name"       => "Email",
            "code"       => "email",
            "attributes" => [
                "host"     => "Email Host",
                "username" => "Email Username",
                "password" => "Email password",
                "port"     => "Server PORT",
                "from"     => "From Address",
                "app"      => "App Name",
            ],
        ],
        [
            "name"       => "Nexmo",
            "code"       => "nexMo",
            "attributes" => [
                "api_key"    => 'API KEY',
                "api_secret" => 'API SECRET',
                "from"       => "FROM",
            ],
        ],
        [
            "name"       => "Twilio",
            "code"       => "twilio",
            "attributes" => [
                'sid'   => 'SID',
                'token' => 'TOKEN',
            ],
        ],
        [
            "name"       => "SSL Wireless",
            "code"       => "ssl_wireless",
            "attributes" => [
                'api_token' => 'API TOKEN',
                'sid'       => 'SID',
            ],
        ],
        [
            "name"       => "Mim SMS",
            "code"       => "mim_sms",
            "attributes" => [
                'username'         => 'USERNAME',
                'api_key'          => 'API KEY',
                'campaign_id'      => 'CAMPAIGN ID',
                'transaction_type' => 'TRANSACTION TYPE',
                'sender_name'      => 'SENDER NAME',
            ],
        ],
        [
            "name"       => "MSEGAT",
            "code"       => "mse_gat",
            "attributes" => [
                'api_key'     => 'API KEY',
                'username'    => 'USERNAME',
                'user_sender' => 'USER SENDER',
            ],
        ],
        [
            "name"       => "Sparrow",
            "code"       => "sparrow",
            "attributes" => [
                'from'  => "FROM",
                'token' => 'TOKEN',
            ],
        ],
        [
            "name"       => "BulksmsBD",
            "code"       => "bulksmsbd",
            "attributes" => [
                "api_key"  => "API KEY",
                "senderid" => "Sender ID",
            ],
        ],
    ];
}
