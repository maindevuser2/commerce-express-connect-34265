<?php
namespace Helpers;

/**
 * Stripe Helper Class
 * Maneja la integración con Stripe usando cURL si la librería oficial no está disponible
 */
class StripeHelper {
    private $secretKey;
    private $apiUrl = 'https://api.stripe.com/v1/';
    
    public function __construct($secretKey) {
        $this->secretKey = $secretKey;
    }
    
    /**
     * Crear un cargo (charge) en Stripe
     */
    public function createCharge($params) {
        $url = $this->apiUrl . 'charges';
        
        $data = [
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'source' => $params['source'],
            'description' => $params['description'] ?? '',
            'receipt_email' => $params['receipt_email'] ?? null,
        ];
        
        // Agregar metadata si existe
        if (isset($params['metadata'])) {
            foreach ($params['metadata'] as $key => $value) {
                $data["metadata[$key]"] = $value;
            }
        }
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response === false) {
            throw new \Exception('Error de conexión con Stripe');
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']['message']);
        }
        
        return $result;
    }
    
    /**
     * Crear un Payment Intent
     */
    public function createPaymentIntent($params) {
        $url = $this->apiUrl . 'payment_intents';
        
        $data = [
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'payment_method' => $params['payment_method'] ?? null,
            'confirmation_method' => $params['confirmation_method'] ?? 'automatic',
            'confirm' => $params['confirm'] ?? false,
            'description' => $params['description'] ?? '',
            'receipt_email' => $params['receipt_email'] ?? null,
            'metadata' => $params['metadata'] ?? []
        ];
        
        return $this->makeRequest('POST', $url, $data);
    }
    
    /**
     * Confirmar un Payment Intent
     */
    public function confirmPaymentIntent($paymentIntentId, $params = []) {
        $url = $this->apiUrl . 'payment_intents/' . $paymentIntentId . '/confirm';
        return $this->makeRequest('POST', $url, $params);
    }
    
    /**
     * Obtener información de un Payment Intent
     */
    public function retrievePaymentIntent($paymentIntentId) {
        $url = $this->apiUrl . 'payment_intents/' . $paymentIntentId;
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Obtener información de un cargo
     */
    public function retrieveCharge($chargeId) {
        $url = $this->apiUrl . 'charges/' . $chargeId;
        $response = $this->makeRequest('GET', $url);
        
        if ($response === false) {
            throw new \Exception('Error de conexión con Stripe');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Crear un reembolso
     */
    public function createRefund($chargeId, $amount = null) {
        $url = $this->apiUrl . 'refunds';
        
        $data = ['charge' => $chargeId];
        if ($amount !== null) {
            $data['amount'] = $amount;
        }
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response === false) {
            throw new \Exception('Error de conexión con Stripe');
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']['message']);
        }
        
        return $result;
    }
    
    /**
     * Realizar solicitud HTTP a Stripe
     */
    private function makeRequest($method, $url, $data = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($httpCode >= 400) {
            error_log("Stripe API error: HTTP $httpCode - $response");
        }
        
        return $response;
    }
    
    /**
     * Validar webhook signature
     */
    public function validateWebhookSignature($payload, $signature, $endpointSecret) {
        $elements = explode(',', $signature);
        $signatureHash = '';
        $timestamp = '';
        
        foreach ($elements as $element) {
            list($key, $value) = explode('=', $element, 2);
            if ($key === 'v1') {
                $signatureHash = $value;
            } elseif ($key === 't') {
                $timestamp = $value;
            }
        }
        
        if (empty($signatureHash) || empty($timestamp)) {
            return false;
        }
        
        $payloadForSignature = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $payloadForSignature, $endpointSecret);
        
        return hash_equals($expectedSignature, $signatureHash);
    }
}
?>
