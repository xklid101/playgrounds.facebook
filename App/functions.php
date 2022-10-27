<?php

function isEdgeDebugOn()
{
    return true;
}

function getExceptionData(Throwable $exception, $previousCount = 2)
{
    $msg = [];
    $msg['exception'] = get_class($exception);
    $msg['code'] = $exception->getCode();
    $msg['message'] = $exception->getMessage();
    $msg['file'] = $exception->getFile();
    $msg['line'] = $exception->getLine();
    $msg['previous'] = [];
    if ($exception->getPrevious()) {
        $msg['previous'] = "...ABORTED...previousCount exceeded...";
        if ($previousCount > 0) {
            $msg['previous'] = getExceptionData($exception, $previousCount - 1);
        }
    }
    $msg['trace'] = $exception->getTrace();
    return $msg;
}
