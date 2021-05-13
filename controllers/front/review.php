<?php

include(_PS_MODULE_DIR_ . 'waiap' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . "checkouthelper.php");
include(_PS_MODULE_DIR_ . 'waiap/sdk/autoload.php');

class WaiapReviewModuleFrontController extends ModuleFrontController
{

  const JS_SDK_BUNDLE             = "/pwall_sdk/pwall_sdk.bundle.js";
  const CSS_PWALL                 = "/pwall_app/css/app.css";
  const JS_APP                    = "/pwall_app/js/app.js";
  const SIPAY_JS_SDK              = "https://cdn.jsdelivr.net/gh/waiap/javascript-sdk@2.0.2/build/pwall-sdk.min.js";

  protected $waiap_checkout_helper = null;
  protected $name = "paymentwall_review";

  public function init()
  {
    $this->page_name = 'waiap-review'; // page_name and body id
    $this->display_column_left = false;
    $this->display_column_right = false;
    $this->waiap_checkout_helper = new WaiapCheckoutHelper();
    parent::init();
  }

  public function initContent()
  {
    parent::initContent();
    $this->context->smarty->assign([
      'page_title' => Configuration::get('waiap_review_page_title') ? Configuration::get('waiap_review_page_title') : $this->module->l('Waiap payment review')
    ]);
    if (_PS_VERSION_ >= 1.7) {
      $this->setTemplate('module:waiap/views/templates/front/paymentwall_review17.tpl');
    } else {
      $this->setTemplate('paymentwall_review.tpl');
    }
  }

  public function setMedia()
  {
    Context::getContext()->cookie->__get('waiap_pending_payment');
    $cart = new Cart(Context::getContext()->cookie->__get('waiap_quote_id'));
    parent::setMedia();
    if (_PS_VERSION_ >= 1.7) {
      $this->registerJavascript('modules-waiap-sdk', strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE, ['server' => 'remote']);
      $this->registerJavascript('modules-waiap-sipaysdk', self::SIPAY_JS_SDK, ['server' => 'remote']);
      $this->registerStylesheet('modules-waiap-css', strval($this->getEnviromentUrl()) . self::CSS_PWALL, ['server' => 'remote', 'media' => 'all']);
      $this->registerJavascript('modules-waiap-appjs', 'modules/'. $this->module->name.'/views/js/waiap_checkout_review.js', ['position' => 'bottom', 'priority' => 200]);
    } else {
      $this->addCSS(strval($this->getEnviromentUrl()) . self::CSS_PWALL, 'all');
      $this->addJS(strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE);
      $this->addJS(self::SIPAY_JS_SDK);
      $this->addJS(__PS_BASE_URI__.'modules/'.$this->module->name.'/views/js/waiap_checkout_review.js');
    }
    $customer = new Customer((int) (Context::getContext()->customer->id));
    Media::addJsDef(['waiap_review' => [
      "enviroment"      => Configuration::get('waiap_environment'),
      "backendUrl"      => Context::getContext()->link->getModuleLink('waiap', 'backend', [], Configuration::get('PS_SSL_ENABLED')),
      "currency"        => $this->context->currency->iso_code,
      "amount"          => floatval($cart->getOrderTotal(true)),
      "customer_id"     => $customer->is_guest ? "0" : $customer->id,
      "tags"            => $this->waiap_checkout_helper->getCartTags($cart),
      ]
    ]);
  }

  private function getEnviromentUrl(){
        if(Configuration::get('waiap_environment') == 'sandbox'){
            return 'https://sandbox.waiap.com';
        }
        return 'https://live.waiap.com';
    }

}