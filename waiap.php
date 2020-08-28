<?php

if (!defined('_PS_VERSION_'))
    exit;

class Waiap extends PaymentModule{

    CONST JS_SDK_BUNDLE             = "/pwall_sdk/pwall_sdk.bundle.js";
    CONST CSS_PWALL                 = "/pwall_app/css/app.css";
    CONST JS_APP                    = "/pwall_app/js/app.js";
    CONST ROUTES_LOAD_WAIAP_BUNDLE  = ["order","order-opc","supercheckoutcustom"];
    CONST SIPAY_JS_SDK              = "https://cdn.jsdelivr.net/gh/waiap/javascript-sdk@2.0.0/dist/2.0.0/pwall-sdk.min.js";

    public function __construct()
    {
        $this->name                     = 'waiap';
        $this->tab                      = 'payments_gateways';
        $this->version                  = '4.1.3';
        $this->author                   = 'Bankia';
        $this->need_instance            = 0;
        $this->ps_versions_compliancy   = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap                = true;
        $this->is_eu_compatible         = 1;
        $this->controllers              = ['backend'];

        $this->currencies               = true;
        $this->currencies_mode          = 'checkbox';

        parent::__construct();

        $this->displayName = Configuration::get('waiap_displayed_name') != null || Configuration::get('waiap_displayed_name') != "" ? Configuration::get('waiap_displayed_name') : $this->l('Pay with card or other alternative methods');
        $this->description = $this->l('With the Waiap Payment Wall you can accept multiple payment methods (Visa, MasterCard, Amazon Pay, PayPal, Google Pay, Apple Pay, Bizum, Payment with bank account and Payment with financing) in a single module');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() 
            || !$this->registerHook('payment') 
            || !$this->registerHook('paymentReturn') 
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayAdminOrderContentOrder')
            || !$this->registerHook('displayBackOfficeHeader')){
                return false;
        }

        $this->createOrderExtraDataTable();
        $this->addOrderState($this->l('Pending payment'));

        Configuration::deleteByName('waiap_key');
        Configuration::deleteByName('waiap_resource');
        Configuration::deleteByName('waiap_environment');
        Configuration::deleteByName('waiap_secret');
        Configuration::deleteByName('waiap_displayed_name');
        return true;
    }

    public function createOrderExtraDataTable(){
        Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_. 'waiap_order_extradata` (
                `id_order` INT UNSIGNED NOT NULL,
                `data` LONGTEXT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE='._MYSQL_ENGINE_. ' CHARACTER SET utf8 COLLATE utf8_general_ci;'
        ); 
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function addOrderState($name)
    {
        if(!Configuration::get('WAIAP_PENDING_PAYMENT')){
            // create new order state
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->color = '#cdcdcd';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[$language['id_lang']] = $name;

            // Update object
            $order_state->add();
            Configuration::updateValue('WAIAP_PENDING_PAYMENT', (int) $order_state->id);
        }   


        return true;
    }

    public function getContent()
    {
        $output = null;
        

        if (Tools::isSubmit('submitAddconfiguration')) {
            $key                = strval(Tools::getValue('waiap_key'));
            $resource           = strval(Tools::getValue('waiap_resource'));
            $environment        = strval(Tools::getValue('waiap_environment'));
            $secret             = strval(Tools::getValue('waiap_secret'));
            $displayed_name     = strval(Tools::getValue('waiap_displayed_name'));
            $review_page_title  = strval(Tools::getValue('waiap_review_page_title'));

            if (
                $this->invalidEntry($key)
                || $this->invalidEntry($resource)
                || $this->invalidEntry($environment)
                || $this->invalidEntry($secret)
            ) {
                $output .= $this->displayError(
                    $this->l('Invalid Configuration value')
                );
            } else {
                Configuration::updateValue('waiap_key', $key);
                Configuration::updateValue('waiap_resource', $resource);
                Configuration::updateValue('waiap_environment', $environment);
                Configuration::updateValue('waiap_secret', $secret);
                Configuration::updateValue('waiap_displayed_name', $displayed_name);
                Configuration::updateValue('waiap_review_page_title', $review_page_title);
                $output .= $this->displayConfirmation(
                    $this->l('Settings updated')
                );
            }
        }

        $this->context->smarty->assign([
            'waiap_pwall_bundle' => $this->getEnviromentUrl() . self::JS_SDK_BUNDLE,
            'waiap_pwall_css' => $this->getEnviromentUrl() . self::CSS_PWALL,
            'waiap_pwall_app' => $this->getEnviromentUrl(). self::JS_APP,
            'waiap_pwall_controller' => Context::getContext()->link->getModuleLink('waiap', 'backend', [], Configuration::get('PS_SSL_ENABLED')),
            'waiap_enviroment' => Configuration::get('waiap_environment'),
            'currency' => $this->context->currency
        ]);

        $output.= $this->display(__FILE__, 'views/templates/admin/paymentwall_app.tpl');

        return $this->displayForm().$output;
    }
    
    public function displayForm()
    {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Waiap PaymentWall Settings'),
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('Enviroment'),
                    'name' => 'waiap_environment',
                    'required' => true,
                    'options' => [
                        'query' => [
                            [
                                'id_option' => 'sandbox',
                                'name' => 'Sandbox'
                            ],
                            [
                                'id_option' => 'live',
                                'name' => 'Live'
                            ]
                        ],
                        'id' => 'id_option',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Key'),
                    'name' => 'waiap_key',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret'),
                    'name' => 'waiap_secret',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Resource'),
                    'name' => 'waiap_resource',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Displayed name'),
                    'name' => 'waiap_displayed_name',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Review page title'),
                    'name' => 'waiap_review_page_title',
                    'required' => true
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        // $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['waiap_key'] = Configuration::get('waiap_key');
        $helper->fields_value['waiap_resource'] = Configuration::get('waiap_resource');
        $helper->fields_value['waiap_secret'] = Configuration::get('waiap_secret');
        $helper->fields_value['waiap_environment'] = Configuration::get('waiap_environment');
        $helper->fields_value['waiap_displayed_name'] = Configuration::get('waiap_displayed_name');
        $helper->fields_value['waiap_review_page_title'] = Configuration::get('waiap_review_page_title');

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Check for invalid value in the config form
     *
     * @return string
     */
    public function invalidEntry($entry)
    {
        return (!$entry || empty($entry) || !Validate::isGenericName($entry));
    }

    public function hookPaymentOptions($params){
        /*
         * Verify if this module is active
         */
        if (!$this->active) {
            return;
        }

        $formAction = Context::getContext()->link->getModuleLink($this->name, 'validation', [], Configuration::get('PS_SSL_ENABLED'));


        $paymentForm = $this->context->smarty->fetch('module:waiap/views/templates/front/paymentwall_app.tpl');

        /**
         * Create a PaymentOption object containing the necessary data
         * to display this module in the checkout
         */
        $newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setCallToActionText($this->displayName)
            ->setAction($formAction)
            ->setForm($paymentForm);

        return [$newOption];
    }

    public function hookPayment($params)
    {
        if (!$this->active)
            return;
        if (!$this->checkCurrency($params['cart']))
            return;

        $this->context->smarty->assign([
            'this_path' => $this->_path,
            'name' => $this->displayName,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
        ]);

        return $this->display(__FILE__, 'paymentwall_app.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (_PS_VERSION_ >= 1.7) {
            $this->context->controller->addCSS(strval($this->_path . 'views/css/express-checkout-admin.css'), 'all');
        }else{
            $this->context->controller->addCSS(strval($this->_path . 'views/css/express-checkout-admin.css'), 'all');
        }
    }

    public function hookDisplayAdminOrderContentOrder($data){
        //get extra data from our table
        $order_id = $data["order"]->id;
        $sql = 'SELECT * FROM '._DB_PREFIX_.'waiap_order_extradata WHERE id_order = '. $order_id;
        $orderData = Db::getInstance()->getRow($sql);
        if($orderData){
            //asign info to smarty
            $extraData = json_decode($orderData["data"], true);
            $this->context->smarty->assign('waiap_order_extradata', $extraData);
            return $this->display(__FILE__, 'paymentwall_order.tpl');
        }
    }

    public function hookDisplayHeader($params)
    {
        $route = $this->context->controller->php_self;
        if(!isset($route) && $this->context->controller instanceof ModuleFrontController) {
            $route = $this->context->controller->module->name;
        }
        if(in_array($route, self::ROUTES_LOAD_WAIAP_BUNDLE)){
            if (_PS_VERSION_ >= 1.7) {
                $this->context->controller->registerJavascript('modules-waiap-sdk', strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE, ['server' => 'remote']);
                $this->context->controller->registerJavascript('modules-waiap-sipaysdk', self::SIPAY_JS_SDK, ['server' => 'remote']);
                $this->context->controller->registerStylesheet('modules-waiap-css', strval($this->getEnviromentUrl()) . self::CSS_PWALL, ['server' => 'remote', 'media' => 'all']);
                $this->context->controller->registerJavascript('modules-waiap-appjs', 'modules/' . $this->name . '/views/js/waiap_checkout_paymentwall.js', ['position' => 'bottom', 'priority' => 200]);
            } else {
                $this->context->controller->addCSS(strval($this->getEnviromentUrl()) . self::CSS_PWALL, 'all');
                $this->context->controller->addJS(strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE);
                $this->context->controller->addJS(self::SIPAY_JS_SDK);
                $this->context->controller->addJS($this->_path . 'views/js/waiap_checkout_paymentwall.js');
            }
            $customer = new Customer((int) (Context::getContext()->customer->id));
            Media::addJsDef([
                'waiap_customerId'              => $customer->is_guest ? "0" : $customer->id,
                'waiap_currency'                => $this->context->currency->iso_code,
                'waiap_app_js'                  => strval($this->getEnviromentUrl()) . self::JS_APP,
                'waiap_backend_url'             => Context::getContext()->link->getModuleLink($this->name, 'backend', [], Configuration::get('PS_SSL_ENABLED')),
                'waiap_quote_rest'              => Context::getContext()->link->getModuleLink($this->name, 'quote', [], Configuration::get('PS_SSL_ENABLED')),
                'waiap_payment_error'           => $this->l('There was an error processing your payment, please try again.'),
                'waiap_enviroment'              => Configuration::get('waiap_environment'),
                'ps_version'                    => _PS_VERSION_,
                'osc_checkout'                  => _PS_VERSION_ >= 1.7 ? 1 : Configuration::get('PS_ORDER_PROCESS_TYPE'),
                'PS_17_PAYMENT_STEP_HASH'       => 'checkout-payment-step',
                'PS_16_PAYMENT_STEP'            => 'step=3',
                'PS_16_PAYMENT_STEP_HASH'       => 'osc_payment',
                'PS_16_OSC_PAYMENT_STEP_HASH'   => 'app'
            ]);
        }
    }

    private function getEnviromentUrl(){
        if(Configuration::get('waiap_environment') == 'sandbox'){
            return 'https://sandbox.sipay.es';
        }
        return 'https://live.waiap.com';
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
            return;
        return $this->display(__FILE__, 'paymentwall_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

}
