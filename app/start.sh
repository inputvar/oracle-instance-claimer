#!/bin/bash
# DO NOT use set -e — the loop must never die

LOG_FILE="/var/log/oci-loop.log"
touch "$LOG_FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting OCI ARM Instance Claimer..." >> "$LOG_FILE"

# Start PHP built-in web server in background
php -S 0.0.0.0:8080 -t /app/web /app/web/router.php &
WEB_PID=$!
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Web server started on port 8080 (PID: $WEB_PID)" >> "$LOG_FILE"

# Run the loop
php /app/loop.php
LOOP_EXIT=$?

if [ $LOOP_EXIT -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Loop exited successfully (instance created or already exists)" >> "$LOG_FILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Loop exited unexpectedly with code $LOOP_EXIT" >> "$LOG_FILE"
    # Send crash notification email (env vars inherited from process)
    php -r "
        require '/app/vendor/autoload.php';
        \$n = new \Hitrov\Notification\Email();
        if (\$n->isSupported()) {
            \$n->notify('OCI ARM Claimer loop has stopped unexpectedly (exit code $LOOP_EXIT). Check logs at your Fly.io app URL.');
        }
    "
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Keeping web server alive so you can view final logs..." >> "$LOG_FILE"

# Keep web server alive so user can see the logs
wait $WEB_PID
