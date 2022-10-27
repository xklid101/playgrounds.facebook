
<div style="margin: 5px; padding: 5px;">
    1) Generate access token:
    <br>
    <a target="_blank" href="https://developers.facebook.com/tools/explorer/">
        https://developers.facebook.com/tools/explorer/
    </a>
    <ol>
         <li>-> Meta App (e.g. Demo3 API H3.0 connector - app_id/app_secret/adaccount_id)</li>
         <li>-> User or Page (User Token)</li>
         <li>-> Permissions (read_insights, ads_read, public_profile)</li>
         <li>-> Generate Access Token -> Copy/Paste</li>
    </ol>
</div>

<div style="margin: 5px; padding: 5px;">
    2) Edit config.php
</div>

<div style="margin: 5px; padding: 5px;">
    3) Create / edit / modify / view files:
    <div style="margin: 5px; padding: 5px;">
        <?php
            /**
             * ^fb\. - starting with "fb."
             * .* - any characters
             * \.php$ - ending with ".php"
             */
            $files = preg_grep('~^fb\..*\.php$~', scandir(__DIR__));
        ?>
        <?php foreach ($files as $file): ?>
            <a href="<?php echo htmlspecialchars($file) ?>">
                <?php echo htmlspecialchars($file) ?>
            </a>
            <br>
        <?php endforeach ?>
    </div>
</div>
