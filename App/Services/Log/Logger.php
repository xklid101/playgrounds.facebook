<?php

namespace App\Services\Log;

// use EdgeLog;
use Throwable;

class Logger
{
    // private $edgeLog;

    // public function __construct(EdgeLog $edgeLog)
    // {
    //     $this->edgeLog = $edgeLog;
    // }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $logtype = 'system';
        $msg = strtoupper($level) . ': ' . $message;
        $msgContext = [];
        $contextCount = count($context);
        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), ['log_type', 'logtype'])) {
                $logtype = $value;
            }
            $strKey = "$key: ";
            if ($contextCount === 1 && !$key) {
                $strKey = "";
            }
            $msgContext[] = "$strKey" . $this->getMessageFromVar($value);
        }

        echo '<div style="margin: 5px; padding: 5px; border: 1px solid grey">';
        // echo '  <div><small>' . $logtype . '</small></div>';
        echo '<div><small>' . htmlspecialchars(preg_replace('#^[A-Za-z0-9-_]+:#', '', $msg)) . '</small></div>';
        foreach ($msgContext as $value) {
            echo '<div><small><pre style="white-space: pre-wrap; overflow-wrap: break-word;">' . htmlspecialchars($value) . '</pre></small></div>';
        }
        echo '</div>';

        // $this->edgeLog->log(
        //     $msg,
        //     implode("\n", $msgContext),
        //     $logtype
        // );
    }

    private function getMessageFromVar($var)
    {
        if ($var instanceof Throwable) {
            return $var->__toString();
        }
        if (is_scalar($var)) {
            return (string) $var;
        }
        if (is_array($var)) {
            return json_encode($var, JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES);
        }
        if (is_object($var)) {
            if (method_exists($var, '__toString')) {
                return $var->__toString();
            }
            return 'Unable to log extended data (' . get_class($var) . ' does not implemet __toString method)';
        }
        return 'Unable to log extended data (unsupported context type)';
    }
}

