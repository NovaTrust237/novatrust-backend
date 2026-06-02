<?php


namespace App\Services;


use Illuminate\Support\Facades\Http;

class PaymentService
{
    protected $endpoint;
    protected $apiKey;
    protected $merchantId;


    public function __construct()
    {
        $this->endpoint = config('services.mytouchpoint.endpoint', env('MYTOUCHPOINT_ENDPOINT'));
        $this->apiKey = config('services.mytouchpoint.key', env('MYTOUCHPOINT_API_KEY'));
        $this->merchantId = config('services.mytouchpoint.merchant_id', env('MYTOUCHPOINT_MERCHANT_ID'));
    }

    /**
    * Create a payment link on the provider and return the url + provider reference
    * This is a generic example - adapt body to MyTouchPoint API.
    */

    public function createPaymentLink(array $data): array
    {
        // expected $data: amount, currency, reference, callback_url, return_url, customer_phone
        $resp = Http::withHeaders([
        'Authorization' => "Bearer {$this->apiKey}",
        'Accept' => 'application/json'
        ])->post("{$this->endpoint}/payments/create", [
        'merchant_id' => $this->merchantId,
        'amount' => $data['amount'],
        'currency' => $data['currency'] ?? 'XAF',
        'reference' => $data['reference'],
        'callback_url' => $data['callback_url'],
        'return_url' => $data['return_url'],
        'customer_phone' => $data['customer_phone'] ?? null,
        ]);


        if (! $resp->successful()) {
        return ['success' => false, 'message' => $resp->body()];
        }


        $json = $resp->json();
        // adjust to provider's response structure
        return [
        'success' => true,
        'payment_url' => $json['payment_url'] ?? $json['data']['payment_url'] ?? null,
        'provider_reference' => $json['reference'] ?? $json['data']['reference'] ?? null,
        ];
    }

    /**
* Simple signature verification stub - adapt to provider's webhook spec
*/
    public function verifyWebhook($request): bool
    {
        // example: HMAC with shared secret
        $secret = env('MYTOUCHPOINT_WEBHOOK_SECRET');
        $signatureHeader = $request->header('X-Signature') ?? $request->header('X-Sign');
        if (! $signatureHeader || ! $secret) return false;
        $payload = $request->getContent();
        $calc = hash_hmac('sha256', $payload, $secret);
        return hash_equals($calc, $signatureHeader);
    }

}
