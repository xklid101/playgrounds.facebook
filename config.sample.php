<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ALL ^ E_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/App/error.handler.php';
require __DIR__ . '/App/functions.php';

return [
    'app_id' => isset($_GET['appId']) ? $_GET['appId'] :  '123465789132456789',
    'app_secret' => isset($_GET['appSecret']) ? $_GET['appSecret'] :  '987654321987654312987654312',
    'adaccount_id' => isset($_GET['adaccountId']) ? $_GET['adaccountId'] :  '456789123456789123',
    'marketingapi_version' => isset($_GET['apiVersion']) ? $_GET['apiVersion'] : 'v15.0',

    /**
     * @see https://developers.facebook.com/tools/explorer/
     *
     *      -> Meta App (e.g. Demo3 API H3.0 connector - app_id/app_secret/adaccount_id)
     *      -> User or Page (User Token)
     *      -> Permissions (read_insights, ads_read, public_profile)
     *      -> Generate Access Token -> Copy/Paste
     */
    'access_token' => isset($_GET['accessToken']) ? $_GET['accessToken'] :  'EAAN5ACCESSTOKENLONGSTRINGoJa',
];
