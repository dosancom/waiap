<?php

include(_PS_MODULE_DIR_ . 'waiap' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . "checkouthelper.php");
include(_PS_MODULE_DIR_ . 'waiap/sdk/autoload.php');

class WaiapBackendModuleFrontController extends ModuleFrontController
{
    protected $helper;

    public function initContent()
    {
        $this->ajax             = true;
        parent::initContent();
    }

    public function addOrderInfo(&$pwall_request, $quote)
    {
        $customer = new Customer((int) (Context::getContext()->customer->id));

        $pwall_request->setOrderId($quote->id == null ? "000000" : strval($quote->id));
        $pwall_request->setAmount($quote->id == null ? 0 : floatval($quote->getOrderTotal(true)));
        $pwall_request->setCurrency(Context::getContext()->currency->iso_code);
        $pwall_request->setOriginalUrl(_PS_BASE_URL_);
        $pwall_request->setGroupId(strval($customer->is_guest ? "0" : $customer->id));
    }

    public function postProcess(){
        $this->client           = new \PWall\Client();
        $this->checkout_helper  = new WaiapCheckoutHelper();
        $jsonRequest = Tools::jsonDecode(Tools::file_get_contents('php://input'), -true);
        PrestaShopLogger::addLog("[EZENIT WAIAP] ON BACKEND EXECUTE: " . json_encode($jsonRequest));

        $this->client->setEnvironment(Configuration::get('waiap_environment'));
        $this->client->setKey(Configuration::get('waiap_key'));
        $this->client->setResource(Configuration::get('waiap_resource'));
        $this->client->setSecret(Configuration::get('waiap_secret'));
        $this->client->setBackendUrl(Context::getContext()->link->getModuleLink('waiap', 'review', [], Configuration::get('PS_SSL_ENABLED')));
        $debug_path = Configuration::get('waiap_debug_path');
        if ($debug_path && $debug_path != '') {
            $this->client->setDebugFile($debug_path);
        }
        $quote = $this->getQuoteFromCheckoutorSession();
        if($quote == null){
            $request = new \PWall\Request(json_encode($jsonRequest), true);
        }else{
            $request = new \PWall\Request(json_encode($jsonRequest), false);
        }
        $this->addOrderInfo($request, $quote);

        if ($request->isEcCreateOrder()||$request->hasUpdateAmount()) {
            //add product info
            //       is_digital: true <- only if all element are digital
            $cart_info = $this->checkout_helper->getPaypalItemsInfo($quote);
            $request->setEcCartInfo($cart_info["items"], $cart_info["is_digital"], $cart_info["breakdown"]);
            $request->setAmount($cart_info["total"]);
            // $cart_info = $this->checkout_helper->getPaypalItemsInfo($quote);
            // $request->setPaypalEcCartInfo($cart_info["items"], $cart_info["is_digital"]);
            // $request->setAmount(floatval($quote->getOrderTotal(false, Cart::ONLY_PRODUCTS)));
        }

        $this->checkout_helper->setPSD2Params($request, Context::getContext()->customer, $quote);

        $response = $this->client->proxy($request);

        if ($response->hasAddress() && !$response->hasUpdateAmount()) {
            //Set address to quote, set shipping method, collect rates
            try {
                $quote = Context::getContext()->cart;
                $error = $this->checkout_helper->setAddressAndCollectRates($response, $quote);
                if ($error) {
                    $response->setError(json_encode($error[0]));
                } else {
                    $response->setUpdatedAmount(floatval(Context::getContext()->cart->getOrderTotal(true)));
                }
            } catch (\Exception $e) {
                $response->setError($e->getMessage());
            }
        }

        if ($response->isCreatePendingOrder() && !Context::getContext()->cookie->__get('waiap_pending_payment')) {
            if($this->checkout_helper->placeOrderFromResponse($jsonRequest, $response)){
                Context::getContext()->cookie->__set('waiap_pending_payment', true);
                Context::getContext()->cookie->__set('waiap_order_id', $this->module->currentOrder);
                Context::getContext()->cookie->__set('waiap_quote_id', $quote->id);
            }
        }else{
            if ($response->canPlaceOrder()) {
                $order_id = null;
                if (Context::getContext()->cookie->__get('waiap_pending_payment')) {
                    $order_id = Context::getContext()->cookie->__get('waiap_order_id');
                    $cart = new Cart(Context::getContext()->cookie->__get('waiap_quote_id'));
                    $order_total = $cart->getOrderTotal(true, Cart::BOTH);

                    $history = new OrderHistory();
                    $history->id_order = (int) $order_id;
                    
                    $new_order_status_id = $this->checkout_helper->detectSuspectedFraud($response, $order_total) ?
                        (int) Configuration::get('WAIAP_SUSPECTED_FRAUD')
                        : (int) Configuration::get('PS_OS_PAYMENT');
                    $history->changeIdOrderState($new_order_status_id, (int)$order_id);
                    $history->save();
                    $customer = Context::getContext()->customer;
                    setcookie("success_redirect", Context::getContext()->link->getPageLink('order-confirmation&id_cart=' . (int) Context::getContext()->cookie->__get('waiap_quote_id') . '&id_module=' . (int) $this->module->id . '&id_order=' . $order_id . '&key=' . $customer->secure_key), time() + 10, "/");
                    Context::getContext()->cookie->__unset('waiap_pending_payment');
                    Context::getContext()->cookie->__unset('waiap_order_id');
                    Context::getContext()->cookie->__unset('waiap_quote_id');
                }else{
                    $this->checkout_helper->placeOrderFromResponse($jsonRequest, $response, $jsonRequest["params"]["method"]);
                    $cart = Context::getContext()->cart;
                    $customer = Context::getContext()->customer;
                    $order_id = $this->module->currentOrder;
                    setcookie("success_redirect", Context::getContext()->link->getPageLink('order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), time() + 10, "/");
                }
                Db::getInstance()->insert('waiap_order_extradata', array(
                    'id_order' => (int) $order_id,
                    'data'      => pSQL(json_encode($response->getPaymentInfo())),
                ));
            }
        }
        

        $this->ajaxDie($response->toJSON());
    }

    private function getQuoteFromCheckoutorSession()
    {
        if (Context::getContext()->cart->id) {
            Context::getContext()->cookie->__unset('waiap_pending_payment');
            Context::getContext()->cookie->__unset('waiap_order_id');
            Context::getContext()->cookie->__unset('waiap_quote_id');
            return Context::getContext()->cart;
        }
        if (Context::getContext()->cookie->__get('waiap_pending_payment') && Context::getContext()->cookie->__get('waiap_quote_id')) {
            return new Cart(Context::getContext()->cookie->__get('waiap_quote_id'));
        }
        return Context::getContext()->cart;
    }
}