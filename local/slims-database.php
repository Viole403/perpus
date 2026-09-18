<?php

return [
    'default_profile' => 'SLiMS',
    'proxy' => false,
    'nodes' => [
        'SLiMS' => [
            'host' => '127.0.0.1',
            'database' => 'senayan',
            'port' => '3306',
            'username' => 'slims',
            'password' => 'slims123',
            'options' => [
                'storage_engine' => 'InnoDB'
            ]
        ],
    ]
];
