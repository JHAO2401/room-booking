<?php
/**
 * Minimal, dependency-free S3 client for uploading room photos.
 *
 * Why hand-written instead of the official AWS SDK: this project is
 * plain PHP with no Composer/vendor directory, and the SDK is a heavy
 * dependency to add just for "upload one file to S3". This implements
 * just enough of AWS Signature Version 4 to do a signed PUT request.
 *
 * Credentials are resolved in this order:
 *   1. AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / (AWS_SESSION_TOKEN)
 *      environment variables, if set — useful for local testing.
 *   2. The IAM role attached to the EC2 instance (via the instance
 *      metadata service, IMDSv2) — this is what you'll use in
 *      production, matching the EC2 -> IAM arrow in your diagram.
 *      No access keys are ever stored in code or in the database.
 *
 * Enable S3 storage by setting these environment variables on the
 * server (e.g. in your Apache/EC2 environment config):
 *   S3_BUCKET   = your-bucket-name
 *   AWS_REGION  = us-east-1        (defaults to us-east-1 if unset)
 * If S3_BUCKET is not set, uploads fall back to local disk storage
 * (images/rooms/) automatically — no code changes needed either way.
 */

function s3_is_enabled(): bool {
    return (bool) getenv('S3_BUCKET');
}

/**
 * Fetch temporary credentials from the EC2 instance metadata service
 * (IMDSv2 — requires a session token first, which is the current AWS
 * best practice / more secure than IMDSv1).
 * Returns null if not running on EC2 or no IAM role is attached.
 */
function s3_get_instance_role_credentials(): ?array {
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: null;
    }

    $ctx_token = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $ctx_token);
    if (!$token) {
        $cached = false;
        return null;
    }

    $ctx_role = stream_context_create(['http' => [
        'header' => "X-aws-ec2-metadata-token: $token\r\n",
        'timeout' => 1,
        'ignore_errors' => true,
    ]]);
    $role = @file_get_contents('http://169.254.169.254/latest/meta-data/iam/security-credentials/', false, $ctx_role);
    if (!$role) {
        $cached = false;
        return null;
    }
    $role = trim($role);

    $creds_json = @file_get_contents(
        "http://169.254.169.254/latest/meta-data/iam/security-credentials/$role",
        false,
        $ctx_role
    );
    if (!$creds_json) {
        $cached = false;
        return null;
    }
    $creds = json_decode($creds_json, true);
    if (empty($creds['AccessKeyId'])) {
        $cached = false;
        return null;
    }

    $cached = [
        'key'    => $creds['AccessKeyId'],
        'secret' => $creds['SecretAccessKey'],
        'token'  => $creds['Token'] ?? null,
    ];
    return $cached;
}

function s3_get_credentials(): ?array {
    $env_key = getenv('AWS_ACCESS_KEY_ID');
    $env_secret = getenv('AWS_SECRET_ACCESS_KEY');
    if ($env_key && $env_secret) {
        return [
            'key'    => $env_key,
            'secret' => $env_secret,
            'token'  => getenv('AWS_SESSION_TOKEN') ?: null,
        ];
    }
    return s3_get_instance_role_credentials();
}

/**
 * Upload a local file to S3 using a SigV4-signed PUT request.
 * Returns the public HTTPS URL of the uploaded object, or null on failure.
 */
function s3_upload_file(string $local_path, string $s3_key, string $content_type): ?string {
    $bucket = getenv('S3_BUCKET');
    $region = getenv('AWS_REGION') ?: 'us-east-1';
    if (!$bucket) return null;

    $creds = s3_get_credentials();
    if (!$creds) return null;

    $body = file_get_contents($local_path);
    if ($body === false) return null;

    $host = "$bucket.s3.$region.amazonaws.com";
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $payload_hash = hash('sha256', $body);

    $headers = [
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
        'PUT',
        '/' . $s3_key,
        '',
        $canonical_headers,
        $signed_headers,
        $payload_hash,
    ]);

    $credential_scope = "$date_stamp/$region/s3/aws4_request";
    $string_to_sign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amz_date,
        $credential_scope,
        hash('sha256', $canonical_request),
    ]);

    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $creds['secret'], true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $authorization = "AWS4-HMAC-SHA256 Credential={$creds['key']}/$credential_scope, "
        . "SignedHeaders=$signed_headers, Signature=$signature";

    $curl_headers = [
        "Host: $host",
        "X-Amz-Date: $amz_date",
        "X-Amz-Content-Sha256: $payload_hash",
        "Authorization: $authorization",
        "Content-Type: $content_type",
    ];
    if (!empty($creds['token'])) {
        $curl_headers[] = "X-Amz-Security-Token: {$creds['token']}";
    }

    $ch = curl_init("https://$host/$s3_key");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $curl_headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return null;

    return "https://$host/$s3_key";
}
