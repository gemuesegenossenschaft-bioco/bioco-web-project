#!/bin/bash
# Health check watchdog for bioco.ch Next.js server.
# Called by cron every 5 min. Detects running-but-broken states (HTTP 200 with
# error boundary content) and restarts via start.sh which owns env vars/secrets.
# This file lives in the repo (no secrets) and is rsynced by deploy.sh.

CURL=/usr/bin/curl
PGREP=/usr/bin/pgrep
KILL=/bin/kill
RM=/bin/rm
SLEEP=/bin/sleep
DATE=/bin/date
CAT=/bin/cat
MKDIR=/bin/mkdir
GREP=/bin/grep
AWK=/usr/bin/awk
PS=/bin/ps

PORT=49154
URL="http://127.0.0.1:${PORT}/"
START_SCRIPT="/home/bioco/bioco-frontend/start.sh"
LOGFILE="/home/bioco/logs/healthcheck.log"
COOLDOWN_FILE="/tmp/bioco-healthcheck-last-restart"
LOCK_FILE="/tmp/bioco-next-start.lock"
PID_FILE="/tmp/bioco-next.pid"
COOLDOWN_SECONDS=180
CURL_TIMEOUT=10
RECHECK_DELAY=2

log() {
    $MKDIR -p "$(dirname "$LOGFILE")"
    echo "$($DATE '+%Y-%m-%d %H:%M:%S') [healthcheck] $*" >> "$LOGFILE"
}

check_cooldown() {
    if [ -f "$COOLDOWN_FILE" ]; then
        last_restart=$($CAT "$COOLDOWN_FILE" 2>/dev/null || echo 0)
        now=$($DATE +%s)
        elapsed=$((now - last_restart))
        if [ "$elapsed" -lt "$COOLDOWN_SECONDS" ]; then
            log "COOLDOWN: last restart ${elapsed}s ago (< ${COOLDOWN_SECONDS}s). Skipping."
            exit 0
        fi
    fi
}

record_restart() {
    $DATE +%s > "$COOLDOWN_FILE"
}

kill_and_cleanup() {
    log "Killing next-server processes..."
    for p in $($PGREP -a next-server 2>/dev/null | $AWK '{print $1}'); do
        $KILL "$p" 2>/dev/null
        log "Killed PID $p"
    done
    for p in $($PS -eo pid,comm | $AWK '$2 ~ /^next-server/ {print $1}'); do
        $KILL "$p" 2>/dev/null
    done
    $SLEEP 3
    $RM -f "$LOCK_FILE" "$PID_FILE"
    log "Cleaned up lock and pid files."
}

# Returns 0 if healthy, 1 if error boundary detected
check_body_health() {
    local body
    body=$($CURL -sS --max-time "$CURL_TIMEOUT" "$URL" 2>/dev/null) || return 1
    if echo "$body" | $GREP -q '>Fehler</h' && echo "$body" | $GREP -q 'Etwas ist schiefgelaufen'; then
        return 1
    fi
    return 0
}

# --- STEP 1: Process running? ---
if ! $PGREP -x next-server > /dev/null 2>&1; then
    log "NOT RUNNING: next-server not found. Starting..."
    exec "$START_SCRIPT"
fi

# --- STEP 2: HTTP status check ---
http_status=$($CURL -s -o /dev/null -w '%{http_code}' --max-time "$CURL_TIMEOUT" "$URL" 2>/dev/null)
if [ "$http_status" != "200" ]; then
    log "HTTP $http_status (not 200). Delegating to start.sh..."
    exec "$START_SCRIPT"
fi

# --- STEP 3: Deep body check (double-verify) ---
if ! check_body_health; then
    log "FIRST CHECK FAILED: error boundary detected. Rechecking in ${RECHECK_DELAY}s..."
    $SLEEP "$RECHECK_DELAY"
    if ! check_body_health; then
        log "SECOND CHECK FAILED: confirmed unhealthy."
        check_cooldown
        record_restart
        kill_and_cleanup
        log "Restarting via start.sh..."
        exec "$START_SCRIPT"
    else
        log "Second check passed (transient). No action."
    fi
fi

exit 0
