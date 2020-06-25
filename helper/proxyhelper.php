<?php

class WaiapProxyHelper {
    protected $key;
    protected $resource;
    protected $secret;
    protected $environment;
    protected $proxy_helper;

    public function __construct()
    {
        $this->key = Configuration::get('waiap_key');
        $this->resource = Configuration::get('waiap_resource');
        $this->secret = Configuration::get('waiap_secret');
        $this->environment = Configuration::get('waiap_environment');   
    }

    public function addOrderInfo(&$jsonRequest)
    {
        $customer = new Customer((int) (Context::getContext()->customer->id));

        $jsonRequest['params']['order']    = Context::getContext()->cart == null ? "000000" : str_pad(strval(Context::getContext()->cart->id), 12, 0, STR_PAD_LEFT); //Make sure we only send 12 characters, Bizum doesn't admit more than that
        $jsonRequest['params']['amount']   = Context::getContext()->cart == null ? 0 : intval(floatval(Context::getContext()->cart->getOrderTotal(true)) * 100);
        $jsonRequest['params']['currency'] = Context::getContext()->currency->iso_code;
        $jsonRequest['params']['group_id'] = strval($customer->is_guest ? "0" : $customer->id);
    }

    public function proxyRequest($payload)
    {
        $domain_url  = $this->environment . '/pwall/api/v1/actions';
        $url_api     = $domain_url;
        $secret      = $this->secret;
        $key         = $this->key;
        $resource    = $this->resource;
        $nonce       = str_pad(substr($this->generateNonce(), 0, 10), 10, 0, STR_PAD_LEFT); //Make sure we only send 10 characters, Amazon seems to not like more than 10
        $body        = [
            "key"        => $key,
            "resource"   => $resource,
            "nonce"      => $nonce,
            "mode"       => 'sha256',
            "payload"    => $payload
        ];

        $json_body   = json_encode($body);
        $signature   = hash_hmac('sha256', $json_body, $secret);
        $ch          = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $url_api); // change this to use curl_apionfig api url
        curl_setopt($ch, CURLOPT_POST, 1); // set post data to true
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);   // post data
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-signature: ' . $signature,
            'Content-type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($ch));
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        PrestaShopLogger::addLog("[EZENIT WAIAP] PROXY REQUEST: " . $json_body);
        PrestaShopLogger::addLog("[EZENIT WAIAP] PROXY RESPONSE CODE: " . $httpcode);
        PrestaShopLogger::addLog("[EZENIT WAIAP] " . json_encode($response));

        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            PrestaShopLogger::addLog("[EZENIT WAIAP] cURL error ({$errno}): {$error_message}");
        }
        return $response;
    }

    public static function generateNonce()
    {
        return sprintf(
            '%d%d',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}