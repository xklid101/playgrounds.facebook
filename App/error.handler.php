<?php

set_exception_handler(
    function (Throwable $exception)
    {
        $data = getExceptionData($exception);
        if (!headers_sent()) {
            header('Content-Type: text/html');
        }
        echo '<pre style="background: #FFFEDF; color: #2e2e2e; padding: 10px; font-weight: bold; font-size: 1.2em">';
        foreach ($data as $key => $value) {
            echo "$key: ";
            if (is_array($value)) {
                echo getStrWithoutDirPath(
                    json_encode(
                        $value,
                        JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES
                    )
                );
                echo "\n";
                continue;
            }
            echo getStrWithoutDirPath(
                strval($value)
            );
            echo "\n";
        }
        echo '</pre>';
    }
);

set_error_handler(
    function (
        $errno,
        $errstr,
        $errfile = null,
        $errline = null,
        $errcontext = []
    ) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);
