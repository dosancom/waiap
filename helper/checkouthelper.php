<?php

class WaiapCheckoutHelper {

    const EXCLUDED_SUSPECTED_FRAUD_METHODS = ["azon", "altp_bizum", "altp_bankia_transfer", "altp_bankia"];

    public function placeOrderFromResponse($requestJSON, $response)
    {
        return $this->convertQuoteToOrder($requestJSON, $response);
    }

    public function convertQuoteToOrder($request, $response)
    {
        $cart = Context::getContext()->cart;
        PrestaShopLogger::addLog("[EZENIT WAIAP] CONVERT TO QUOTE CUSTOMER " . $cart->id);
        $order_total = $cart->getOrderTotal(true, Cart::BOTH);
        $order_status = $response->isCreatePendingOrder() ? (int) Configuration::get('WAIAP_PENDING_PAYMENT') : (int) Configuration::get('PS_OS_PAYMENT');
        $payment_module = Module::getInstanceByName('waiap');
        if($payment_module->validateOrder($cart->id, $order_status, $order_total, $payment_module->displayName, null, array(), null, false, $cart->secure_key)){
            return $cart->id;
        }else{
            return false;
        }
    }

    private function detectSuspectedFraud($responseJSON, $real_order_amount)
    {
        // $responseAmount = 0;
        // PrestaShopLogger::addLog("[EZENIT WAIAP] " . json_encode($responseJSON));
        // $flattenResponse = $this->flatten($responseJSON);
        // PrestaShopLogger::addLog("[EZENIT WAIAP] " . json_encode($flattenResponse));
        // foreach ($flattenResponse as $key => $value) {
        //     if (strpos($key, 'amount') !== false) {
        //         $responseAmount = $value;
        //         break;
        //     }
        // }
        // PrestaShopLogger::addLog("[EZENIT WAIAP] " . $responseAmount);
        // if ((floatval($responseAmount) / 100) != $real_order_amount) {
        //     PrestaShopLogger::addLog("[EZENIT WAIAP] SUSPECTED FROUD DETECTED");
        //     return true;
        // } else {
        //     return false;
        // }
        return false;
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

    public function getCartTags($quote){
        $has_virtual   = false;
        $has_novirtual = false;

        if ($quote->getProducts()) {
            foreach ($quote->getProducts() as $item) {
                if ($item["is_virtual"] == "1") {
                    $has_virtual = true;
                } else {
                    $has_novirtual = true;
                }
            }
        }

        if ($has_virtual && $has_novirtual) {
            return "mixto";
        } else if ($has_virtual) {
            return "digital";
        } else {
            return "fisico";
        }
    }


}
