<?php

// require libs
$config = require __DIR__ . '/config.php';

// init Facebook custom api
$fbApi = (
    new App\Services\Facebook\Api(
        new GuzzleHttp\Client,
        new App\Services\Log\Logger
    )
)->init(
    $config
);


echo '<pre>';
// read some data
$list = $fbApi->getDataAll(
    'campaigns',
    [
        /**
         * @see e.g. https://developers.facebook.com/docs/marketing-api/reference/ad-campaign-group/
         */
        'fields' => 'id,name,start_time,stop_time,status,daily_budget'
    ]
);

// output
echo "<b>count: " . count($list) . '</b><br>';

echo json_encode(
    $list,
    JSON_PRETTY_PRINT
);
echo '</pre>';
