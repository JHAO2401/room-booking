#!/bin/bash
#
# stress_test.sh — Orchestrates a full multi-phase load test against the
# Room Booking ALB using Apache Bench (ab), and logs ASG instance count
# in the background so you can see if auto-scaling actually kicked in.
#
# Usage:
#   chmod +x stress_test.sh
#   ./stress_test.sh http://your-alb-dns-name room-booking-asg
#
# Requires: httpd-tools (ab), aws cli (already available in CloudShell)
#   sudo yum install -y httpd-tools

set -e

ALB_URL="${1:?Usage: ./stress_test.sh <ALB_URL> <ASG_NAME>}"
ASG_NAME="${2:?Usage: ./stress_test.sh <ALB_URL> <ASG_NAME>}"

RESULTS_DIR="stress_test_results_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$RESULTS_DIR"

echo "======================================================"
echo " Room Booking — Full Stress Test"
echo " Target: $ALB_URL"
echo " ASG:    $ASG_NAME"
echo " Results will be saved to: $RESULTS_DIR/"
echo "======================================================"

# ------------------------------------------------------------
# Background watcher: logs ASG instance count every 5 seconds
# for the entire duration of this script.
# ------------------------------------------------------------
watch_asg() {
    while true; do
        TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
        COUNT=$(aws autoscaling describe-auto-scaling-groups \
            --auto-scaling-group-names "$ASG_NAME" \
            --query 'AutoScalingGroups[0].Instances | length(@)' \
            --output text 2>/dev/null || echo "ERROR")
        echo "$TIMESTAMP,instance_count=$COUNT" >> "$RESULTS_DIR/asg_scaling_log.csv"
        sleep 5
    done
}
watch_asg &
WATCHER_PID=$!
echo "Started ASG watcher (PID $WATCHER_PID), logging to asg_scaling_log.csv"

# Make sure the watcher gets killed even if the script exits early
trap "kill $WATCHER_PID 2>/dev/null" EXIT

run_ab() {
    local label="$1"
    local requests="$2"
    local concurrency="$3"
    local path="$4"
    local outfile="$RESULTS_DIR/${label}.txt"

    echo ""
    echo "--- $label ---"
    echo "requests=$requests concurrency=$concurrency path=$path"
    ab -n "$requests" -c "$concurrency" "$ALB_URL$path" | tee "$outfile"
    echo "Saved to $outfile"
}

# ------------------------------------------------------------
# Phase 1: Warm-up
# ------------------------------------------------------------
run_ab "phase1_warmup" 100 10 "/index.php"

# ------------------------------------------------------------
# Phase 2: Medium load
# ------------------------------------------------------------
run_ab "phase2_medium_load" 1000 50 "/index.php"

# ------------------------------------------------------------
# Phase 3: High load — designed to push CPU high enough to
# trigger the ASG target-tracking scaling policy (if configured)
# ------------------------------------------------------------
run_ab "phase3_high_load" 5000 200 "/index.php"

# ------------------------------------------------------------
# Phase 4: Multi-page mixed test — simulates traffic spread
# across different parts of the site, not just the homepage
# ------------------------------------------------------------
echo ""
echo "--- Phase 4: Multi-page mixed test ---"
run_ab "phase4a_homepage"  500 50 "/index.php"
run_ab "phase4b_login"     500 50 "/login_register/login.php"
run_ab "phase4c_room_page" 500 50 "/booking/room.php?id=1"
run_ab "phase4d_register"  500 50 "/login_register/register.php"

# ------------------------------------------------------------
# Phase 5: Sustained load (soak test) — 30 concurrent users for
# up to 5 minutes, to check stability over time rather than a
# short burst
# ------------------------------------------------------------
echo ""
echo "--- Phase 5: Sustained load (soak test, max 300s) ---"
ab -n 10000 -c 30 -t 300 "$ALB_URL/index.php" | tee "$RESULTS_DIR/phase5_soak_test.txt"

# ------------------------------------------------------------
# Phase 6: WAF sanity check — confirm a SQL-injection-shaped
# request gets blocked (expect HTTP 403)
# ------------------------------------------------------------
echo ""
echo "--- Phase 6: WAF check (expect HTTP 403) ---"
curl -s -o /dev/null -w "HTTP status: %{http_code}\n" "$ALB_URL/index.php?q=' OR '1'='1" | tee "$RESULTS_DIR/phase6_waf_check.txt"

# Stop the background watcher
kill "$WATCHER_PID" 2>/dev/null
trap - EXIT

# ------------------------------------------------------------
# Summary
# ------------------------------------------------------------
echo ""
echo "======================================================"
echo " All phases complete."
echo " Results directory: $RESULTS_DIR/"
echo ""
echo " Quick summary (Requests per second per phase):"
grep -H "Requests per second" "$RESULTS_DIR"/*.txt 2>/dev/null || true
echo ""
echo " ASG instance count over time (asg_scaling_log.csv):"
cat "$RESULTS_DIR/asg_scaling_log.csv" 2>/dev/null || echo "  (no data captured)"
echo "======================================================"