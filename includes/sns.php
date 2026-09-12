<?php

require_once __DIR__ . '/s3.php'; 
function sns_publish_event(array $data): bool {
    $topic_arn = getenv('SNS_RAW_TOPIC_ARN');
    if (!$topic_arn) return false; // notifications not configured, no-op

    $region = getenv('AWS_REGION') ?: 'us-east-1';

    $creds = s3_get_credentials(); // same EC2-role / env-var lookup as S3
    if (!$creds) return false;

    $message = json_encode($data);
    if ($message === false) return false;

    $params = [
        'Action'   => 'Publish',
        'Version'  => '2010-03-31',
        'TopicArn' => $topic_arn,
        'Message'  => $message,
    ];
    ksort($params);
    $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $host = "sns.$region.amazonaws.com";
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $payload_hash = hash('sha256', $body);

    $headers = [
        'content-type' => 'application/x-www-form-urlencoded; charset=utf-8',
        'host' => $host,
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $amz_date,
    ];
    if (!empty($creds['token'])) {
        $headers['x-amz-security-token'] = $creds['token'];
    }
    ksort($headers);

    $canonical_headers = '';
    foreach ($headers as $k => $v) {
        $canonical_headers .= "$k:$v\n";
    }
    $signed_headers = implode(';', array_keys($headers));

    $canonical_request = implode("\n", [
        'POST',
        '/',
        '',
        $canonical_headers,
        $signed_headers,
        $payload_hash,
    ]);

    $credential_scope = "$date_stamp/$region/sns/aws4_request";
    $string_to_sign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amz_date,
        $credential_scope,
        hash('sha256', $canonical_request),
    ]);

    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $creds['secret'], true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 'sns', $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $authorization = "AWS4-HMAC-SHA256 Credential={$creds['key']}/$credential_scope, "
        . "SignedHeaders=$signed_headers, Signature=$signature";

    $curl_headers = [
        "Host: $host",
        "X-Amz-Date: $amz_date",
        "X-Amz-Content-Sha256: $payload_hash",
        "Authorization: $authorization",
        "Content-Type: application/x-www-form-urlencoded; charset=utf-8",
    ];
    if (!empty($creds['token'])) {
        $curl_headers[] = "X-Amz-Security-Token: {$creds['token']}";
    }

    $ch = curl_init("https://$host/");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $curl_headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($status !== 200) {
        error_log("sns_publish_event failed (HTTP $status): $err");
        return false;
    }
    return true;
}