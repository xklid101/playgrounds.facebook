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

/**
 * @see e.g. https://developers.facebook.com/docs/marketing-api/reference/ad-campaign-group/insights
 */
$insightsParams = [
    'level' => 'campaign',
    'time_increment' => 'all_days',
    'date_preset' => 'maximum',
    'time_range' => [
        'since' => '2022-01-01',
        'until' => '2022-12-01',
    ],
];
$insightsFields = [
    'campaign_id',
    'campaign_name',
    'impressions',
    'clicks',
    'cpc',
    'cpm',
    'reach',
    'frequency',
    'unique_clicks',
    'canvas_avg_view_percent',
    'canvas_avg_view_time',
    'date_start',
    'date_stop',
    'estimated_ad_recall_rate',
    'estimated_ad_recallers',
    'inline_link_clicks',
    'inline_post_engagement',
    'objective',
    'place_page_name',
    'social_spend',
    'spend',
    'unique_actions',
    'unique_inline_link_clicks',
    'video_30_sec_watched_actions',
    'video_avg_time_watched_actions',
    'video_p100_watched_actions',
    'video_p25_watched_actions',
    'video_p50_watched_actions',
    'video_p75_watched_actions'
];


echo '<pre>';
// read some data !!! ASYNC !!!
$list = $fbApi->getDataAllAsync(
    'insights',
    array_merge(
        $insightsParams,
        [
            'fields' => implode(',', $insightsFields)
        ]
    )
);

// output
echo "<b>count: " . count($list) . '</b><br>';

echo json_encode(
    $list,
    JSON_PRETTY_PRINT
);
echo '</pre>';
