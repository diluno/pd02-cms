<?php

use craft\helpers\App;
use Dart\Library\Craft\FlysystemProvider\Adapter\CloudflareR2Adapter;

return [
    '*' => [
        'adapterConfigs' => [
            'cloudflareR2' => new CloudflareR2Adapter(
                // Cast to string so Craft still boots while the R2 vars are unset
                accountId: (string) App::parseEnv('$CLOUDFLARE_R2_ACCOUNT_ID'),
                accessKeyId: (string) App::parseEnv('$CLOUDFLARE_R2_ACCESS_KEY_ID'),
                secretAccessKey: (string) App::parseEnv('$CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
                bucket: (string) App::parseEnv('$CLOUDFLARE_R2_BUCKET'),
                eu: (bool) App::parseBooleanEnv('$CLOUDFLARE_R2_EU_ENABLED'),
            ),
        ],
    ],
];
