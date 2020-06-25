<?php

include(_PS_MODULE_DIR_ . 'waiap' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . "proxyhelper.php");
include(_PS_MODULE_DIR_ . 'waiap' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . "checkouthelper.php");

class WaiapBackendModuleFrontController extends ModuleFrontController
{
    protected $helper;

    public function initContent()
    {
        $this->ajax             = true;
        parent::initContent();
    }

    private function actionUnkown($jsonRequest)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: actionUnkown");
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        $this->helper->addOrderInfo($jsonRequest);
        return $this->helper->proxyRequest($jsonRequest);
    }

    private function actionListMethods(&$jsonRequest)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: listMethods");
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        return $this->helper->proxyRequest($jsonRequest);
    }

    private function actionGetConfiguration(&$jsonRequest)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: getConfiguration");
        $this->helper->addOrderInfo($jsonRequest);
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        return $this->helper->proxyRequest($jsonRequest);
    }

    private function actionDeleteToken(&$jsonRequest)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: deleteToken");
        $this->helper->addOrderInfo($jsonRequest);
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        return $this->helper->proxyRequest($jsonRequest);
    }

    private function actionSale(&$jsonRequest)
    {
        $customer = Context::getContext()->customer;
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: sale" . json_encode($jsonRequest));
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: customer email: " . $customer->email);
        $this->helper->addOrderInfo($jsonRequest);
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        $response = $this->helper->proxyRequest($jsonRequest);
        $array_response = json_decode(json_encode($response), true);
        if (
            is_array($array_response)
            &&  array_key_exists("result",  $array_response)
            &&  array_key_exists("code",  $array_response["result"])
            &&  intval($array_response["result"]["code"]) === 0
        ) {
            //CHECK IF result.payload.code exists and result.payload.code !== 198
            if (
                is_array($array_response)
                &&  array_key_exists("result",  $array_response)
                &&  array_key_exists("payload",  $array_response["result"])
                &&  array_key_exists("code", $array_response["result"]["payload"])
                &&  intval($array_response["result"]["payload"]["code"]) !== 0
            ) {
                return $response;
            }
            //CHECK IF IS REDIRECT
            if (
                is_array($array_response)
                &&  array_key_exists("result",  $array_response)
                &&  array_key_exists("payload",  $array_response["result"])
                &&  array_key_exists("url", $array_response["result"]["payload"])
            ) {
                return $response;
            }
            $orderId = $this->checkout_helper->placeOrderFromResponse($jsonRequest, $response, $jsonRequest["params"]["method"]);
            $cart = Context::getContext()->cart;
            $customer = Context::getContext()->customer;
            setcookie("success_redirect", Context::getContext()->link->getPageLink('order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), time() + 10, "/");
        }
        return $response;
    }

    private function actionPlaceOrderOnResponse(&$jsonRequest)
    {
        PrestaShopLogger::addLog("[EZENIT WAIAP] ACTION: actionPlaceOrderOnResponse");
        $this->helper->addOrderInfo($jsonRequest);
        $jsonRequest['params']['notify']   = ['result' => $_SERVER['HTTP_REFERER']];
        $response = $this->helper->proxyRequest($jsonRequest);
        $orderId = $this->checkout_helper->placeOrderFromResponse($jsonRequest, $response, $jsonRequest["params"]["method"]);
        $cart = Context::getContext()->cart;
        $customer = Context::getContext()->customer;
        setcookie("success_redirect", Context::getContext()->link->getPageLink('order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), time() + 10, "/");
        return $response;
    }

    public function postProcess(){
        $this->helper           = new WaiapProxyHelper();
        $this->checkout_helper  = new WaiapCheckoutHelper();
        $jsonRequest = Tools::jsonDecode(Tools::file_get_contents('php://input'), true);
        PrestaShopLogger::addLog("[EZENIT WAIAP] ON BACKEND EXECUTE: " . json_encode($jsonRequest));

        switch ($jsonRequest["action"]) {
            case 'pwall.sale':
                if (
                    array_key_exists("params", $jsonRequest)
                    && array_key_exists("method", $jsonRequest["params"])
                    && $jsonRequest["params"]["method"] == "azon"
                ) {
                    exit(Tools::jsonEncode($this->actionPlaceOrderOnResponse($jsonRequest)));
                    break;
                } else if (
                    array_key_exists("params", $jsonRequest)
                    && array_key_exists("method", $jsonRequest["params"])
                    && $jsonRequest["params"]["method"] == "gpay"
                ) {
                    exit(Tools::jsonEncode($this->actionPlaceOrderOnResponse($jsonRequest)));
                    break;
                } else if(
                    array_key_exists("params", $jsonRequest)
                    && array_key_exists("method", $jsonRequest["params"])
                    && $jsonRequest["params"]["method"] == "apay"
                ){
                    exit(Tools::jsonEncode($this->actionPlaceOrderOnResponse($jsonRequest)));
                    break;
                }else {
                    exit(Tools::jsonEncode($this->actionSale($jsonRequest)));
                    break;
                }
            case 'pwall.getConfiguration':
                exit(Tools::jsonEncode($this->actionGetConfiguration($jsonRequest)));
            case 'pwall.deleteToken':
                exit(Tools::jsonEncode($this->actionDeleteToken($jsonRequest)));
            case 'rpc.listMethods':
                exit(Tools::jsonEncode($this->actionListMethods($jsonRequest)));
            default:
                exit(Tools::jsonEncode($this->actionUnkown($jsonRequest)));
        }
        return false;
    }
}