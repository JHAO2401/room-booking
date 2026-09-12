<?php
/**
 * Publishes booking events to SNS by shelling out to the AWS CLI
 * (proven working via the EC2 instance's attached IAM role).
 */

function sns_publish_event(array $data): bool {
    $topic_arn = 'arn:aws:sns:us-east-1:783053623314:room-booking-raw';

    $message = json_encode($data);
    if ($message === false) {
        error_log('sns_publish_event: json_encode failed');
        return false;
    }

    $cmd = sprintf(
        'HOME=/tmp /usr/bin/aws sns publish --topic-arn %s --message %s --region us-east-1 2>&1',
        escapeshellarg($topic_arn),
        escapeshellarg($message)
    );

    $output = [];
    $exit_code = 0;
    exec($cmd, $output, $exit_code);

    $output_str = implode("\n", $output);
    error_log("sns_publish_event exit=$exit_code output=$output_str");

    return $exit_code === 0;
}