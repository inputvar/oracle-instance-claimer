<?php
// Disable output buffering completely
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) { ob_end_flush(); }
@ini_set('implicit_flush', true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$logFile = '/var/log/oci-loop.log';
$offset = 0;

// Send existing log content first
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            echo "data: " . $line . "\n\n";
        }
    }
    $offset = filesize($logFile);
    flush();
}

// Stream new lines
while (true) {
    if (connection_aborted()) break;

    if (!file_exists($logFile)) {
        sleep(2);
        continue;
    }

    clearstatcache(false, $logFile);
    $currentSize = filesize($logFile);

    if ($currentSize > $offset) {
        $fp = fopen($logFile, 'r');
        fseek($fp, $offset);
        $newContent = fread($fp, $currentSize - $offset);
        fclose($fp);
        $offset = $currentSize;

        $lines = explode("\n", $newContent);
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                echo "data: " . $line . "\n\n";
            }
        }
        flush();
    } elseif ($currentSize < $offset) {
        $offset = 0;
    }

    // Heartbeat to keep connection alive
    echo ": heartbeat\n\n";
    flush();

    sleep(2);
}
