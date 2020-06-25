<?php

class WaiapCheckoutHelper {

    const EXCLUDED_SUSPECTED_FRAUD_METHODS = ["azon", "altp_bizum", "altp_bankia_transfer", "altp_bankia"];

    public function placeOrderFromResponse($requestJSON, $response, $method)
    {
        $responseJSON = json_decode(json_encode($response), true);
        if ($this->checkPayment($method, $responseJSON) === true) {
            // add payment method and convert quote to order
            return $this->convertQuoteToOrder($requestJSON, $responseJSON, $method);
        } else {
            return false;
        }
    }

    public function checkPayment($method, $responseJSON)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] PAYMENT METHOD!!!: " . $method);
        return array_key_exists("result",  $responseJSON)
            &&  array_key_exists("code",  $responseJSON["result"])
            &&  intval($responseJSON["result"]["code"]) === 0;
    }

    public function convertQuoteToOrder($request, $response, $method)
    {
        $cart = Context::getContext()->cart;
        PrestaShopLogger::addLog("[EZENIT WAIAP] CONVERT TO QUOTE CUSTOMER " . $cart->id);
        $cart = new Cart((int) $cart->id);
        $order_total = $cart->getOrderTotal(true, Cart::BOTH);
        $order_status = $this->detectSuspectedFraud($response, $order_total) && !in_array($method, self::EXCLUDED_SUSPECTED_FRAUD_METHODS) && array_key_exists("amount", $response) ? (int) Configuration::get('PS_OS_ERROR') : (int) Configuration::get('PS_OS_PAYMENT');
        $payment_module = Module::getInstanceByName('waiap');
        if($payment_module->validateOrder($cart->id, $order_status, $order_total, $payment_module->displayName, null, array(), null, false, $cart->secure_key)){
            return $cart->id;
        }else{
            return false;
        }
    }

    private function detectSuspectedFraud($responseJSON, $real_order_amount)
    {
        $responseAmount = 0;
        PrestaShopLogger::addLog("[EZENIT WAIAP] " . json_encode($responseJSON));
        $flattenResponse = $this->flatten($responseJSON);
        PrestaShopLogger::addLog("[EZENIT WAIAP] " . json_encode($flattenResponse));
        foreach ($flattenResponse as $key => $value) {
            if (strpos($key, 'amount') !== false) {
                $responseAmount = $value;
                break;
            }
        }
        PrestaShopLogger::addLog("[EZENIT WAIAP] " . $responseAmount);
        if ((floatval($responseAmount) / 100) != $real_order_amount) {
            PrestaShopLogger::addLog("[EZENIT WAIAP] SUSPECTED FROUD DETECTED");
            return true;
        } else {
            return false;
        }
    }

    private function flatten($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }


}
