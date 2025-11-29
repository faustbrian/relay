# Request Signing

HMAC, AWS Signature V4, and custom signing for secure API authentication.

## HMAC Signing

```php
#[Post, Json, HmacSignature(
    secret: 'webhook_secret',
    algorithm: 'sha256',
    header: 'X-Signature',
)]
class CreateWebhook extends Request
{
    public function endpoint(): string
    {
        return '/webhooks';
    }
}

// Generates: X-Signature: sha256=<hmac_of_body>
```

## HMAC with Timestamp

```php
#[Post, Json, HmacSignature(
    secret: 'api_secret',
    algorithm: 'sha256',
    header: 'X-Signature',
    timestampHeader: 'X-Timestamp',
    includeTimestamp: true,
)]
class SecureRequest extends Request { ... }

// Signs: timestamp + body
// Generates: X-Timestamp: 1234567890
//            X-Signature: sha256=<hmac_of_timestamp.body>
```

## AWS Signature V4

```php
#[Get, Json, AwsSignature(
    accessKey: 'AKIAIOSFODNN7EXAMPLE',
    secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    region: 'us-east-1',
    service: 's3',
)]
class ListBuckets extends Request
{
    public function endpoint(): string
    {
        return '/';
    }
}

// Automatically adds:
// - Authorization header with AWS4-HMAC-SHA256 signature
// - X-Amz-Date header
// - X-Amz-Content-Sha256 header
```

## AWS with Session Token

```php
#[Get, Json, AwsSignature(
    accessKey: 'AKIAIOSFODNN7EXAMPLE',
    secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    sessionToken: 'FwoGZXIvYXdzE...', // For temporary credentials
    region: 'us-west-2',
    service: 'execute-api',
)]
class CallApiGateway extends Request { ... }
```

## Connector-Level Signing

```php
class AwsConnector extends Connector
{
    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region,
    ) {}

    public function signing(): ?SigningConfig
    {
        return new AwsSignatureV4(
            accessKey: $this->accessKey,
            secretKey: $this->secretKey,
            region: $this->region,
            service: 's3',
        );
    }
}
```

## Custom Signing

```php
class CustomSigner implements RequestSigner
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secret,
    ) {}

    public function sign(Request $request): void
    {
        $timestamp = time();
        $body = json_encode($request->body() ?? []);
        $signature = hash_hmac(
            'sha256',
            "{$timestamp}.{$body}",
            $this->secret
        );

        $request->withHeader('X-API-Key', $this->apiKey);
        $request->withHeader('X-Timestamp', (string) $timestamp);
        $request->withHeader('X-Signature', $signature);
    }
}

// Use in connector
class ApiConnector extends Connector
{
    public function signing(): ?RequestSigner
    {
        return new CustomSigner($this->apiKey, $this->secret);
    }
}
```

## Signing Specific Body Parts

```php
class PayloadSigner implements RequestSigner
{
    public function sign(Request $request): void
    {
        $body = $request->body();

        // Sign only specific fields
        $toSign = [
            'amount' => $body['amount'],
            'currency' => $body['currency'],
            'timestamp' => $body['timestamp'],
        ];

        $signature = hash_hmac(
            'sha256',
            json_encode($toSign),
            $this->secret
        );

        // Add signature to body
        $request->withBody([
            ...$body,
            'signature' => $signature,
        ]);
    }
}
```

## Webhook Verification (Incoming)

```php
class WebhookVerifier
{
    public function __construct(
        private readonly string $secret,
    ) {}

    public function verify(
        string $payload,
        string $signature,
        ?int $timestamp = null,
    ): bool {
        // Timestamp tolerance (5 minutes)
        if ($timestamp && abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->secret);

        return hash_equals($expected, $signature);
    }
}

// Usage in Laravel controller
public function handleWebhook(Request $request): Response
{
    $verifier = new WebhookVerifier(config('services.api.webhook_secret'));

    if (!$verifier->verify(
        $request->getContent(),
        $request->header('X-Signature'),
        $request->header('X-Timestamp'),
    )) {
        abort(401, 'Invalid signature');
    }

    // Process webhook...
}
```
