<?php
/**
 * Publishes booking events to SNS by shelling out to the AWS CLI
 * (proven working via the EC2 instance's attached IAM role).
 *
 * Writes its own dedicated log file at /var/www/html/sns_debug.log,
 * instead of relying on PHP's error_log() — that setting can point
 * to a different file depending on the SAPI/vhost config, which is
 * why nothing was showing up in /var/log/httpd/error_log.
 */

function sns_debug_log(string $line): void {
    $log_file = __DIR__ . '/../sns_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $line\n", FILE_APPEND | LOCK_EX);
}

function sns_publish_event(array $data): bool {
    $topic_arn = 'arn:aws:sns:us-east-1:783053623314:room-booking-notifications';

    sns_debug_log('sns_publish_event CALLED with data: ' . json_encode($data));

    $message = json_encode($data);
    if ($message === false) {
        sns_debug_log('sns_publish_event: json_encode failed');
        return false;
    }

    $cmd = sprintf(
        'HOME=/tmp /usr/bin/aws sns publish --topic-arn %s --message %s --region us-east-1 2>&1',
        escapeshellarg($topic_arn),
        escapeshellarg($message)
    );

    sns_debug_log('Running command: ' . $cmd);

    $output = [];
    $exit_code = 0;
    exec($cmd, $output, $exit_code);

    $output_str = implode("\n", $output);
    sns_debug_log("Result: exit=$exit_code output=$output_str");

    return $exit_code === 0;
}