<?php

if (!defined('_PS_VERSION_'))
    exit;

class Waiap extends PaymentModule
{

    const JS_SDK_BUNDLE             = "/pwall_sdk/pwall_sdk.bundle.js";
    const CSS_PWALL                 = "/pwall_app/css/app.css";
    const JS_APP                    = "/pwall_app/js/app.js";
    const ROUTES_LOAD_WAIAP_BUNDLE  = ["order", "order-opc"];
    const SIPAY_JS_SDK              = "https://cdn.jsdelivr.net/gh/waiap/javascript-sdk@2.0.2/build/pwall-sdk.min.js";

    public function __construct()
    {
        $this->name                     = 'waiap';
        $this->tab                      = 'payments_gateways';
        $this->version                  = '5.1.2';
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
        if (
            !parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayAdminOrderContentOrder')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayProductButtons')
        ) {
            return false;
        }

        if (_PS_VERSION_ >= 1.7) {
            if (
                !$this->registerHook('displayProductAdditionalInfo')
                || !$this->registerHook('displayExpressCheckout')
            ) {
                return false;
            }
        }

        $this->createOrderExtraDataTable();
        $this->addOrderState();

        Configuration::deleteByName('waiap_key');
        Configuration::deleteByName('waiap_resource');
        Configuration::deleteByName('waiap_environment');
        Configuration::deleteByName('waiap_secret');
        Configuration::deleteByName('waiap_displayed_name');
        return true;
    }

    public function createOrderExtraDataTable()
    {
        Db::getInstance()->execute(
            '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'waiap_order_extradata` (
                `id_order` INT UNSIGNED NOT NULL,
                `data` LONGTEXT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;'
        );
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function addOrderState()
    {
        if (!Configuration::get('WAIAP_PENDING_PAYMENT')) {
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
                $order_state->name[$language['id_lang']] = $this->l('Pending payment');

            // Update object
            $order_state->add();
            Configuration::updateValue('WAIAP_PENDING_PAYMENT', (int) $order_state->id);
        }

        if (!Configuration::get('WAIAP_SUSPECTED_FRAUD')) {
            // create new order state
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->color = '#FFA500';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[$language['id_lang']] = $this->l('Suspected fraud');

            // Update object
            $order_state->add();
            Configuration::updateValue('WAIAP_SUSPECTED_FRAUD', (int) $order_state->id);
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
            $waiap_debug_path   = strval(Tools::getValue('waiap_debug_path'));

            //Express checkout
            $ec_enable                  = strval(Tools::getValue('waiap_ec_enable'));

            $ec_product_page_enable     = strval(Tools::getValue('waiap_ec_product_page_enable'));
            $ec_product_page_container  = strval(Tools::getValue('waiap_product_page_container'));
            $ec_product_page_container_border_color = strval(Tools::getValue('waiap_product_page_container_border_color'));
            $ec_product_page_container_custom_color = strval(Tools::getValue('waiap_product_page_container_custom_border_color'));
            $ec_product_page_container_header_title = strval(Tools::getValue('waiap_product_page_container_header_title'));
            $ec_product_page_container_header_typo = strval(Tools::getValue('waiap_product_page_container_header_typography'));
            $ec_product_page_container_descriptive_text = strval(Tools::getValue('waiap_product_page_container_descriptive_text'));
            $ec_product_page_container_descriptive_typo = strval(Tools::getValue('waiap_product_page_container_descriptive_text_typo'));
            $ec_product_page_position_mode = strval(Tools::getValue('waiap_product_page_position_mode'));
            $ec_product_page_position_selector = strval(Tools::getValue('waiap_product_page_position_selector'));
            $ec_product_page_position_insertion = strval(Tools::getValue('waiap_product_page_position_insertion'));
            $ec_product_page_position_style = strval(Tools::getValue('waiap_product_page_position_style'));

            //PSD2
            $tra_enable = boolval(Tools::getValue('waiap_tra_enable'));
            $tra_high_amount = floatval(Tools::getValue('waiap_tra_high_amount'));
            $lwv_enable = boolval(Tools::getValue('waiap_lwv_enable'));
            $lwv_low_amount = floatval(Tools::getValue('waiap_lwv_low_amount'));

            if (_PS_VERSION_ >= 1.7) {
                $ec_cart_enable     = strval(Tools::getValue('waiap_ec_cart_enable'));
                $ec_cart_container  = strval(Tools::getValue('waiap_cart_container'));
                $ec_cart_container_border_color = strval(Tools::getValue('waiap_cart_container_border_color'));
                $ec_cart_container_custom_color = strval(Tools::getValue('waiap_cart_container_custom_border_color'));
                $ec_cart_container_header_title = strval(Tools::getValue('waiap_cart_container_header_title'));
                $ec_cart_container_header_typo = strval(Tools::getValue('waiap_cart_container_header_typography'));
                $ec_cart_container_descriptive_text = strval(Tools::getValue('waiap_cart_container_descriptive_text'));
                $ec_cart_container_descriptive_typo = strval(Tools::getValue('waiap_cart_container_descriptive_text_typo'));
                $ec_cart_position_mode = strval(Tools::getValue('waiap_cart_position_mode'));
                $ec_cart_position_selector = strval(Tools::getValue('waiap_cart_position_selector'));
                $ec_cart_position_insertion = strval(Tools::getValue('waiap_cart_position_insertion'));
                $ec_cart_position_style = strval(Tools::getValue('waiap_cart_position_style'));
            }

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
                Configuration::updateValue('waiap_debug_path', $waiap_debug_path);

                //Express checkout
                Configuration::updateValue('waiap_ec_enable', $ec_enable);

                Configuration::updateValue('waiap_ec_product_page_enable', $ec_product_page_enable);
                Configuration::updateValue('waiap_product_page_container', $ec_product_page_container);
                Configuration::updateValue('waiap_product_page_container_border_color', $ec_product_page_container_border_color);
                Configuration::updateValue('waiap_product_page_container_custom_border_color', $ec_product_page_container_custom_color);
                Configuration::updateValue('waiap_product_page_container_header_title', $ec_product_page_container_header_title);
                Configuration::updateValue('waiap_product_page_container_header_typography', $ec_product_page_container_header_typo);
                Configuration::updateValue('waiap_product_page_container_descriptive_text', $ec_product_page_container_descriptive_text);
                Configuration::updateValue('waiap_product_page_container_descriptive_text_typo', $ec_product_page_container_descriptive_typo);
                Configuration::updateValue('waiap_product_page_position_mode', $ec_product_page_position_mode);
                Configuration::updateValue('waiap_product_page_position_selector', $ec_product_page_position_selector);
                Configuration::updateValue('waiap_product_page_position_insertion', $ec_product_page_position_insertion);
                Configuration::updateValue('waiap_product_page_position_style', $ec_product_page_position_style);

                //PSD2
                Configuration::updateValue('waiap_tra_enable', $tra_enable);
                Configuration::updateValue('waiap_tra_high_amount', $tra_high_amount);
                Configuration::updateValue('waiap_lwv_enable', $lwv_enable);
                Configuration::updateValue('waiap_lwv_low_amount', $lwv_low_amount);

                if (_PS_VERSION_ >= 1.7) {

                    Configuration::updateValue('waiap_ec_cart_enable', $ec_cart_enable);
                    Configuration::updateValue('waiap_cart_container', $ec_cart_container);
                    Configuration::updateValue('waiap_cart_container_border_color', $ec_cart_container_border_color);
                    Configuration::updateValue('waiap_cart_container_custom_border_color', $ec_cart_container_custom_color);
                    Configuration::updateValue('waiap_cart_container_header_title', $ec_cart_container_header_title);
                    Configuration::updateValue('waiap_cart_container_header_typography', $ec_cart_container_header_typo);
                    Configuration::updateValue('waiap_cart_container_descriptive_text', $ec_cart_container_descriptive_text);
                    Configuration::updateValue('waiap_cart_container_descriptive_text_typo', $ec_cart_container_descriptive_typo);
                    Configuration::updateValue('waiap_cart_position_mode', $ec_cart_position_mode);
                    Configuration::updateValue('waiap_cart_position_selector', $ec_cart_position_selector);
                    Configuration::updateValue('waiap_cart_position_insertion', $ec_cart_position_insertion);
                    Configuration::updateValue('waiap_cart_position_style', $ec_cart_position_style);
                }
                $output .= $this->displayConfirmation(
                    $this->l('Settings updated')
                );
            }
        }

        $this->context->smarty->assign([
            'waiap_pwall_bundle' => $this->getEnviromentUrl() . self::JS_SDK_BUNDLE,
            'waiap_pwall_css' => $this->getEnviromentUrl() . self::CSS_PWALL,
            'waiap_pwall_app' => $this->getEnviromentUrl() . self::JS_APP,
            'waiap_invalid_color' => $this->l('Value is not valid, example #F1F1F1'),
            'waiap_pwall_controller' => Context::getContext()->link->getModuleLink('waiap', 'backend', [], Configuration::get('PS_SSL_ENABLED')),
            'waiap_enviroment' => Configuration::get('waiap_environment'),
            'currency' => $this->context->currency
        ]);

        $output .= $this->display(__FILE__, 'views/templates/admin/paymentwall_app.tpl');

        return $this->displayForm() . $output;
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
                            ],
                            [
                                'id_option' => 'develop',
                                'name' => 'Develop'
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
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Debug path'),
                    'name' => 'waiap_debug_path',
                    'required' => false
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        //PSD2
        $fieldsForm[1]['form'] =
            [
                'legend' => [
                    'title' => $this->l('Waiap PSD2 Settings'),
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Enable TRA'),
                        'name' => 'waiap_tra_enable',
                        'required' => true,
                        'options' => [
                            'query' => $waiap_tra_enable_ids = [
                                [
                                    'waiap_tra_enable_id' => false,
                                    'name' => $this->l('Disabled')
                                ],
                                [
                                    'waiap_tra_enable_id' => true,
                                    'name' => $this->l('Enabled')
                                ]
                            ],
                            'id' => 'waiap_tra_enable_id',
                            'name' => 'name'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('High amount up to'),
                        'name' => 'waiap_tra_high_amount'
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Enable LWV'),
                        'name' => 'waiap_lwv_enable',
                        'required' => true,
                        'options' => [
                            'query' => $waiap_lwv_enable_ids = [
                                [
                                    'waiap_lwv_enable_id' => false,
                                    'name' => $this->l('Disabled')
                                ],
                                [
                                    'waiap_lwv_enable_id' => true,
                                    'name' => $this->l('Enabled')
                                ]
                            ],
                            'id' => 'waiap_lwv_enable_id',
                            'name' => 'name'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Low amount up to'),
                        'name' => 'waiap_lwv_low_amount'
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ];

        //Express Checkout
        $fieldsForm[2]['form'] =
            [
                'legend' => [
                    'title' => $this->l('Waiap Express Checkout Settings'),
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Enabled'),
                        'name' => 'waiap_ec_enable',
                        'required' => true,
                        'options' => [
                            'query' => [
                                [
                                    'waiap_ec_enable' => 'Disabled',
                                    'name' => 'false'
                                ],
                                [
                                    'waiap_ec_enable' => 'Enabled',
                                    'name' => 'true'
                                ]
                            ],
                            'id' => 'waiap_ec_enable',
                            'name' => 'waiap_ec_enable'
                        ]
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ];

        $fieldsForm[3]['form'] =
            [
                'legend' => [
                    'title' => $this->l('Waiap Express Checkout Product Page'),
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Enabled'),
                        'name' => 'waiap_ec_product_page_enable',
                        'required' => true,
                        'options' => [
                            'query' => [
                                [
                                    'waiap_ec_product_page_enable' => 'Disabled',
                                    'name' => 'false'
                                ],
                                [
                                    'waiap_ec_product_page_enable' => 'Enabled',
                                    'name' => 'true'
                                ]
                            ],
                            'id' => 'waiap_ec_product_page_enable',
                            'name' => 'waiap_ec_product_page_enable'
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Enable container customization'),
                        'name' => 'waiap_product_page_container',
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_container' => 'Disabled',
                                    'name' => 'false'
                                ],
                                [
                                    'waiap_product_page_container' => 'Enabled',
                                    'name' => 'true'
                                ]
                            ],
                            'id' => 'waiap_product_page_container',
                            'name' => 'waiap_product_page_container'
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Container border color'),
                        'name' => 'waiap_product_page_container_border_color',
                        'desc' => $this->l('Select color (border and text)'),
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_container_border_color' => 'Light',
                                    'name' => '#FFFFFF'
                                ],
                                [
                                    'waiap_product_page_container_border_color' => 'Dark',
                                    'name' => '#000000'
                                ],
                                [
                                    'waiap_product_page_container_border_color' => 'Custom',
                                    'name' => '#'
                                ]
                            ],
                            'id' => 'name',
                            'name' => 'waiap_product_page_container_border_color'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Container border custom color'),
                        'name' => 'waiap_product_page_container_custom_border_color',
                        'desc' => $this->l('Ex. #F1F1F1')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Container header title'),
                        'name' => 'waiap_product_page_container_header_title'
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Container header title typography'),
                        'name' => 'waiap_product_page_container_header_typography',
                        'hint' => 'This option let you configure the font type of the header title',
                        'desc' => $this->l('If you want a custom font that is not included in the selector leave it in "Without custom font" option and apply the font to #waiap_ec_container on your CSS stylesheet'),
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_container_header_typography' => $this->l('Without custom font'),
                                    'name' => '-'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Arial',
                                    'name' => 'Arial, Arial, Helvetica'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Arial Black',
                                    'name' => 'Arial Black, Arial Black, Gadget'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Comic Sans MS',
                                    'name' => 'Comic Sans MS'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Georgia',
                                    'name' => 'Georgia'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Impact',
                                    'name' => 'Impact, Impact, Charcoal'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Lucida Console',
                                    'name' => 'Lucida Console, Monaco'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Lucida Sans Unicode',
                                    'name' => 'Lucida Sans Unicode, Lucida Grande'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Palatino',
                                    'name' => 'Palatino Linotype, Book Antiqua, Palatino'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Tahoma',
                                    'name' => 'Tahoma, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Trebuchet MS',
                                    'name' => 'Trebuchet MS'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Verdana',
                                    'name' => 'Verdana, Verdana, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Symbol',
                                    'name' => 'Symbol'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Webdings',
                                    'name' => 'Webdings'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'Wingdings',
                                    'name' => 'Wingdings, Zapf Dingbats'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'MS Sans Serif',
                                    'name' => 'MS Sans Serif, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_header_typography' => 'MS Serif',
                                    'name' => 'MS Serif, New York'
                                ],
                            ],
                            'id' => 'name',
                            'name' => 'waiap_product_page_container_header_typography'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Container descriptive text'),
                        'name' => 'waiap_product_page_container_descriptive_text'
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Container descriptive text typography'),
                        'name' => 'waiap_product_page_container_descriptive_text_typo',
                        'hint' => 'This option let you configure the font type of the descriptive text',
                        'desc' => $this->l('If you want a custom font that is not included in the selector leave it in "Without custom font" option and apply the font to #waiap_ec_container on your CSS stylesheet'),
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => $this->l('Without custom font'),
                                    'name' => '-'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Arial',
                                    'name' => 'Arial, Arial, Helvetica'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Arial Black',
                                    'name' => 'Arial Black, Arial Black, Gadget'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Comic Sans MS',
                                    'name' => 'Comic Sans MS'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Georgia',
                                    'name' => 'Georgia'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Impact',
                                    'name' => 'Impact, Impact, Charcoal'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Lucida Console',
                                    'name' => 'Lucida Console, Monaco'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Lucida Sans Unicode',
                                    'name' => 'Lucida Sans Unicode, Lucida Grande'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Palatino',
                                    'name' => 'Palatino Linotype, Book Antiqua, Palatino'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Tahoma',
                                    'name' => 'Tahoma, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Trebuchet MS',
                                    'name' => 'Trebuchet MS'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Verdana',
                                    'name' => 'Verdana, Verdana, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Symbol',
                                    'name' => 'Symbol'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Webdings',
                                    'name' => 'Webdings'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'Wingdings',
                                    'name' => 'Wingdings, Zapf Dingbats'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'MS Sans Serif',
                                    'name' => 'MS Sans Serif, Geneva'
                                ],
                                [
                                    'waiap_product_page_container_descriptive_text_typo' => 'MS Serif',
                                    'name' => 'MS Serif, New York'
                                ]
                            ],
                            'id' => 'name',
                            'name' => 'waiap_product_page_container_descriptive_text_typo'
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Position Mode'),
                        'name' => 'waiap_product_page_position_mode',
                        'required' => true,
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_position_mode' => 'Automatic',
                                    'name' => 'false'
                                ],
                                [
                                    'waiap_product_page_position_mode' => 'Manual',
                                    'name' => 'true'
                                ]
                            ],
                            'id' => 'name',
                            'name' => 'waiap_product_page_position_mode'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Position DOM selector'),
                        'name' => 'waiap_product_page_position_selector',
                        'hint' => $this->l('Select the reference object in which you want to place the widget for a more custom configuration'),
                        'desc' => $this->l('Ex. #example')
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Position Insertion'),
                        'name' => 'waiap_product_page_position_insertion',
                        'required' => true,
                        'desc' => $this->l('Select where do you wanna put the widget relative to the reference object selected in the previous field'),
                        'options' => [
                            'query' => [
                                [
                                    'waiap_product_page_position_insertion' => 'Before',
                                    'name' => 'before'
                                ],
                                [
                                    'waiap_product_page_position_insertion' => 'Into',
                                    'name' => 'into'
                                ],
                                [
                                    'waiap_product_page_position_insertion' => 'After',
                                    'name' => 'after'
                                ]
                            ],
                            'id' => 'name',
                            'name' => 'waiap_product_page_position_insertion'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('DOM CSS custom style'),
                        'name' => 'waiap_product_page_position_style',
                        'desc' => $this->l('Ex. {"background-color":"red","color":"white"}')
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ]
            ];

        if (_PS_VERSION_ >= 1.7) {

            $fieldsForm[4]['form'] =
                [
                    'legend' => [
                        'title' => $this->l('Waiap Express Checkout Cart'),
                    ],
                    'input' => [
                        [
                            'type' => 'select',
                            'label' => $this->l('Enabled'),
                            'name' => 'waiap_ec_cart_enable',
                            'required' => true,
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_ec_cart_enable' => 'Disabled',
                                        'name' => 'false'
                                    ],
                                    [
                                        'waiap_ec_cart_enable' => 'Enabled',
                                        'name' => 'true'
                                    ]
                                ],
                                'id' => 'waiap_ec_cart_enable',
                                'name' => 'waiap_ec_cart_enable'
                            ]
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Enable container customization'),
                            'name' => 'waiap_cart_container',
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_container' => 'Disabled',
                                        'name' => 'false'
                                    ],
                                    [
                                        'waiap_cart_container' => 'Enabled',
                                        'name' => 'true'
                                    ]
                                ],
                                'id' => 'waiap_cart_container',
                                'name' => 'waiap_cart_container'
                            ]
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Container border color'),
                            'name' => 'waiap_cart_container_border_color',
                            'desc' => $this->l('Select color (border and text)'),
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_container_border_color' => 'Light',
                                        'name' => '#FFFFFF'
                                    ],
                                    [
                                        'waiap_cart_container_border_color' => 'Dark',
                                        'name' => '#000000'
                                    ],
                                    [
                                        'waiap_cart_container_border_color' => 'Custom',
                                        'name' => '#'
                                    ]
                                ],
                                'id' => 'waiap_cart_container_border_color',
                                'name' => 'waiap_cart_container_border_color'
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Container border custom color'),
                            'name' => 'waiap_cart_container_custom_border_color',
                            'desc' => $this->l('Ex. #F1F1F1')
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Container header title'),
                            'name' => 'waiap_cart_container_header_title'
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Container header title typography'),
                            'name' => 'waiap_cart_container_header_typography',
                            'hint' => 'This option let you configure the font type of the header title',
                            'desc' => $this->l('If you want a custom font that is not included in the selector leave it in "Without custom font" option and apply the font to #waiap_ec_container on your CSS stylesheet'),
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_container_header_typography' => $this->l('Without custom font'),
                                        'name' => '-'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Arial',
                                        'name' => 'Arial, Arial, Helvetica'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Arial Black',
                                        'name' => 'Arial Black, Arial Black, Gadget'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Comic Sans MS',
                                        'name' => 'Comic Sans MS'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Georgia',
                                        'name' => 'Georgia'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Impact',
                                        'name' => 'Impact, Impact, Charcoal'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Lucida Console',
                                        'name' => 'Lucida Console, Monaco'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Lucida Sans Unicode',
                                        'name' => 'Lucida Sans Unicode, Lucida Grande'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Palatino',
                                        'name' => 'Palatino Linotype, Book Antiqua, Palatino'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Tahoma',
                                        'name' => 'Tahoma, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Trebuchet MS',
                                        'name' => 'Trebuchet MS'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Verdana',
                                        'name' => 'Verdana, Verdana, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Symbol',
                                        'name' => 'Symbol'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Webdings',
                                        'name' => 'Webdings'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'Wingdings',
                                        'name' => 'Wingdings, Zapf Dingbats'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'MS Sans Serif',
                                        'name' => 'MS Sans Serif, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_header_typography' => 'MS Serif',
                                        'name' => 'MS Serif, New York'
                                    ],
                                    'id' => 'waiap_cart_container_header_typography',
                                    'name' => 'waiap_cart_container_header_typography'
                                ]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Container descriptive text'),
                            'name' => 'waiap_cart_container_descriptive_text'
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Container descriptive text typography'),
                            'name' => 'waiap_cart_container_descriptive_text_typo',
                            'hint' => 'This option let you configure the font type of the descriptive text',
                            'desc' => $this->l('If you want a custom font that is not included in the selector leave it in "Without custom font" option and apply the font to #waiap_ec_container on your CSS stylesheet'),
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => $this->l('Without custom font'),
                                        'name' => '-'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Arial',
                                        'name' => 'Arial, Arial, Helvetica'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Arial Black',
                                        'name' => 'Arial Black, Arial Black, Gadget'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Comic Sans MS',
                                        'name' => 'Comic Sans MS'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Georgia',
                                        'name' => 'Georgia'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Impact',
                                        'name' => 'Impact, Impact, Charcoal'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Lucida Console',
                                        'name' => 'Lucida Console, Monaco'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Lucida Sans Unicode',
                                        'name' => 'Lucida Sans Unicode, Lucida Grande'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Palatino',
                                        'name' => 'Palatino Linotype, Book Antiqua, Palatino'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Tahoma',
                                        'name' => 'Tahoma, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Trebuchet MS',
                                        'name' => 'Trebuchet MS'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Verdana',
                                        'name' => 'Verdana, Verdana, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Symbol',
                                        'name' => 'Symbol'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Webdings',
                                        'name' => 'Webdings'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'Wingdings',
                                        'name' => 'Wingdings, Zapf Dingbats'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'MS Sans Serif',
                                        'name' => 'MS Sans Serif, Geneva'
                                    ],
                                    [
                                        'waiap_cart_container_descriptive_text_typo' => 'MS Serif',
                                        'name' => 'MS Serif, New York'
                                    ]
                                ],
                                'id' => 'waiap_cart_container_descriptive_text_typo',
                                'name' => 'waiap_cart_container_descriptive_text_typo'
                            ]
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Position Mode'),
                            'name' => 'waiap_cart_position_mode',
                            'required' => true,
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_position_mode' => 'Automatic',
                                        'name' => 'false'
                                    ],
                                    [
                                        'waiap_cart_position_mode' => 'Manual',
                                        'name' => 'true'
                                    ]
                                ],
                                'id' => 'waiap_cart_position_mode',
                                'name' => 'waiap_cart_position_mode'
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Position DOM selector'),
                            'name' => 'waiap_cart_position_selector',
                            'hint' => $this->l('Select the reference object in which you want to place the widget for a more custom configuration'),
                            'desc' => $this->l('Ex. #example')
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Position Insertion'),
                            'name' => 'waiap_cart_position_insertion',
                            'required' => true,
                            'desc' => $this->l('Select where do you wanna put the widget relative to the reference object selected in the previous field'),
                            'options' => [
                                'query' => [
                                    [
                                        'waiap_cart_position_insertion' => 'Before',
                                        'name' => 'before'
                                    ],
                                    [
                                        'waiap_cart_position_insertion' => 'Into',
                                        'name' => 'into'
                                    ],
                                    [
                                        'waiap_cart_position_insertion' => 'After',
                                        'name' => 'after'
                                    ]
                                ],
                                'id' => 'waiap_cart_position_insertion',
                                'name' => 'waiap_cart_position_insertion'
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('DOM CSS custom style'),
                            'name' => 'waiap_cart_position_style',
                            'desc' => $this->l('Ex. {"background-color":"red","color":"white"}')
                        ]
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right'
                    ]
                ];
        }

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

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
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
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
        $helper->fields_value['waiap_debug_path'] = Configuration::get('waiap_debug_path');
        //Express checkout
        $helper->fields_value['waiap_ec_enable'] = Configuration::get('waiap_ec_enable');

        $helper->fields_value['waiap_ec_product_page_enable'] = Configuration::get('waiap_ec_product_page_enable');
        $helper->fields_value['waiap_product_page_container'] = Configuration::get('waiap_product_page_container');
        $helper->fields_value['waiap_product_page_container_border_color'] = Configuration::get('waiap_product_page_container_border_color');
        $helper->fields_value['waiap_product_page_container_custom_border_color'] = Configuration::get('waiap_product_page_container_custom_border_color');
        $helper->fields_value['waiap_product_page_container_header_title'] = Configuration::get('waiap_product_page_container_header_title');
        $helper->fields_value['waiap_product_page_container_header_typography'] = Configuration::get('waiap_product_page_container_header_typography');
        $helper->fields_value['waiap_product_page_container_descriptive_text'] = Configuration::get('waiap_product_page_container_descriptive_text');
        $helper->fields_value['waiap_product_page_container_descriptive_text_typo'] = Configuration::get('waiap_product_page_container_descriptive_text_typo');
        $helper->fields_value['waiap_product_page_position_mode'] = Configuration::get('waiap_product_page_position_mode');
        $helper->fields_value['waiap_product_page_position_selector'] = Configuration::get('waiap_product_page_position_selector');
        $helper->fields_value['waiap_product_page_position_insertion'] = Configuration::get('waiap_product_page_position_insertion');
        $helper->fields_value['waiap_product_page_position_style'] = Configuration::get('waiap_product_page_position_style');

        //PSD2
        $helper->fields_value['waiap_tra_enable'] = Configuration::get('waiap_tra_enable');
        $helper->fields_value['waiap_tra_high_amount'] = Configuration::get('waiap_tra_high_amount');
        $helper->fields_value['waiap_lwv_enable'] = Configuration::get('waiap_lwv_enable');
        $helper->fields_value['waiap_lwv_low_amount'] = Configuration::get('waiap_lwv_low_amount');

        if (_PS_VERSION_ >= 1.7) {
            $helper->fields_value['waiap_ec_cart_enable'] = Configuration::get('waiap_ec_cart_enable');
            $helper->fields_value['waiap_cart_container'] = Configuration::get('waiap_cart_container');
            $helper->fields_value['waiap_cart_container_border_color'] = Configuration::get('waiap_cart_container_border_color');
            $helper->fields_value['waiap_cart_container_custom_border_color'] = Configuration::get('waiap_cart_container_custom_border_color');
            $helper->fields_value['waiap_cart_container_header_title'] = Configuration::get('waiap_cart_container_header_title');
            $helper->fields_value['waiap_cart_container_header_typography'] = Configuration::get('waiap_cart_container_header_typography');
            $helper->fields_value['waiap_cart_container_descriptive_text'] = Configuration::get('waiap_cart_container_descriptive_text');
            $helper->fields_value['waiap_cart_container_descriptive_text_typo'] = Configuration::get('waiap_cart_container_descriptive_text_typo');
            $helper->fields_value['waiap_cart_position_mode'] = Configuration::get('waiap_cart_position_mode');
            $helper->fields_value['waiap_cart_position_selector'] = Configuration::get('waiap_cart_position_selector');
            $helper->fields_value['waiap_cart_position_insertion'] = Configuration::get('waiap_cart_position_insertion');
            $helper->fields_value['waiap_cart_position_style'] = Configuration::get('waiap_cart_position_style');
        }

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

    public function hookPaymentOptions($params)
    {
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
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ]);

        return $this->display(__FILE__, 'paymentwall_app.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (_PS_VERSION_ >= 1.7) {
            $this->context->controller->addCSS(strval($this->_path . 'views/css/express-checkout-admin.css'), 'all');
        } else {
            $this->context->controller->addCSS(strval($this->_path . 'views/css/express-checkout-admin.css'), 'all');
        }
    }

    public function hookDisplayAdminOrderContentOrder($data)
    {
        //get extra data from our table
        $order_id = $data["order"]->id;
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'waiap_order_extradata WHERE id_order = ' . $order_id;
        $orderData = Db::getInstance()->getRow($sql);
        if ($orderData) {
            //asign info to smarty
            $extraData = json_decode($orderData["data"], true);
            $this->context->smarty->assign('waiap_order_extradata', $extraData);
            return $this->display(__FILE__, 'paymentwall_order.tpl');
        }
    }

    public function hookDisplayHeader($params)
    {
        $route = $this->context->controller->php_self;
        if (in_array($route, self::ROUTES_LOAD_WAIAP_BUNDLE)) {
            if (_PS_VERSION_ >= 1.7) {
                $this->context->controller->registerJavascript('modules-waiap-sdk', strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE, ['server' => 'remote']);
                $this->context->controller->registerStylesheet('modules-waiap-css', strval($this->getEnviromentUrl()) . self::CSS_PWALL, ['server' => 'remote', 'media' => 'all']);
                $this->context->controller->registerJavascript('modules-waiap-appjs', 'modules/' . $this->name . '/views/js/waiap_checkout_paymentwall.js', ['position' => 'bottom', 'priority' => 200]);
            } else {
                $this->context->controller->addCSS(strval($this->getEnviromentUrl()) . self::CSS_PWALL, 'all');
                $this->context->controller->addJS(strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE);
                $this->context->controller->addJS($this->_path . 'views/js/waiap_checkout_paymentwall.js', true);
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
                'PS_16_OSC_PAYMENT_STEP_HASH'   => 'app',
                'waiap_js_sdk'                  => self::SIPAY_JS_SDK
            ]);
        } else if (_PS_VERSION_ >= 1.7 && Configuration::get('waiap_ec_enable') == "Enabled") {
            $this->context->controller->registerJavascript('modules-waiap-sdk', strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE, ['server' => 'remote']);
            $this->context->controller->registerJavascript('modules-waiap-sipaysdk', self::SIPAY_JS_SDK, ['server' => 'remote']);
            $this->context->controller->registerStylesheet('modules-waiap-css', strval($this->getEnviromentUrl()) . self::CSS_PWALL, ['server' => 'remote', 'media' => 'all']);
            $this->context->controller->registerJavascript('modules-waiap-ec-appjs', 'modules/' . $this->name . '/views/js/waiap-expresscheckout-state.js', ['position' => 'top', 'priority' => 200]);
            $this->context->controller->registerJavascript('modules-waiap-ec-statejs', 'modules/' . $this->name . '/views/js/waiap-expresscheckout-paymentwall.js', ['position' => 'top', 'priority' => 200]);

            $environment = Configuration::get('waiap_environment');
            $enabled_container_style = Configuration::get('waiap_product_page_container');
            $enabled_position = Configuration::get('waiap_product_page_position_mode');

            if ($route == 'product') {
                Media::addJsDef(["waiap_ec_config" => [
                    "enviroment" => $environment,
                    "backendUrl" => Context::getContext()->link->getModuleLink($this->name, 'backend', [], Configuration::get('PS_SSL_ENABLED')),
                    "profile" => "woocommerce_product_page",
                    "quoteInfoUrl" => Context::getContext()->link->getModuleLink($this->name, 'quoteec', [], Configuration::get('PS_SSL_ENABLED')),
                    "containerStyle" => $enabled_container_style === 'Enabled' ? [
                        "color" => Configuration::get('waiap_product_page_container_border_color'),
                        "custom_color" => Configuration::get('waiap_product_page_container_custom_border_color'),
                        "header_title" =>  Configuration::get('waiap_product_page_container_header_title'),
                        "header_title_typo" =>  Configuration::get('waiap_product_page_container_header_typography'),
                        "descriptive_text" =>  Configuration::get('waiap_product_page_container_descriptive_text'),
                        "descriptive_text_typo" =>  Configuration::get('waiap_product_page_container_descriptive_text_typo')
                    ] : [],
                    "positionConfig" => $enabled_position === 'Manual' ? [
                        "insertion" => Configuration::get('waiap_product_page_position_insertion'),
                        "position_selector" => Configuration::get('waiap_product_page_position_selector')
                    ] : [],
                    "positionStyleConfig" => $enabled_position === 'Manual' ? Configuration::get('waiap_product_page_position_style') : "",
                    "storeLogoUrl" => _PS_IMG_ . Configuration::get('PS_LOGO', null, null, (int) Context::getContext()->shop->id),
                    "element" => $enabled_position === '1' ? Configuration::get('waiap_product_page_position_selector') : "#waiap_express_checkout"
                ]]);
            } else if ($route == 'cart') {
                Media::addJsDef(["waiap_ec_config" => [
                    "enviroment" => $environment,
                    "backendUrl" => Context::getContext()->link->getModuleLink($this->name, 'backend', [], Configuration::get('PS_SSL_ENABLED')),
                    "profile" => "woocommerce_cart",
                    "quoteInfoUrl" => Context::getContext()->link->getModuleLink($this->name, 'quoteec', [], Configuration::get('PS_SSL_ENABLED')),
                    "containerStyle" => $enabled_container_style === 'Enabled' ? [
                        "color" => Configuration::get('waiap_product_page_container_border_color'),
                        "custom_color" => Configuration::get('waiap_product_page_container_custom_border_color'),
                        "header_title" =>  Configuration::get('waiap_product_page_container_header_title'),
                        "header_title_typo" =>  Configuration::get('waiap_product_page_container_header_typography'),
                        "descriptive_text" =>  Configuration::get('waiap_product_page_container_descriptive_text'),
                        "descriptive_text_typo" =>  Configuration::get('waiap_product_page_container_descriptive_text_typo')
                    ] : [],
                    "positionConfig" => $enabled_position === 'Manual' ? [
                        "insertion" => Configuration::get('waiap_product_page_position_insertion'),
                        "position_selector" => Configuration::get('waiap_product_page_position_selector')
                    ] : [],
                    "positionStyleConfig" => $enabled_position === 'Manual' ? Configuration::get('waiap_product_page_position_style') : "",
                    "storeLogoUrl" => _PS_IMG_ . Configuration::get('PS_LOGO', null, null, (int) Context::getContext()->shop->id),
                    "element" => $enabled_position === '1' ? Configuration::get('waiap_product_page_position_selector') : "#waiap_express_checkout"
                ]]);
            }
        }
    }

    private function getEnviromentUrl()
    {
        if (Configuration::get('waiap_environment') == 'sandbox') {
            return 'https://sandbox.waiap.com';
        } else if (Configuration::get('waiap_environment') == 'develop') {
            return 'https://develop.waiap.com';
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

    public function hookDisplayProductButtons()
    {

        if (Configuration::get('waiap_ec_enable') == "Enabled") {
            if (_PS_VERSION_ >= 1.7) {
                $this->context->controller->registerJavascript('modules-waiap-sdk', strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE, ['server' => 'remote']);
                $this->context->controller->registerJavascript('modules-waiap-sipaysdk', self::SIPAY_JS_SDK, ['server' => 'remote']);
                $this->context->controller->registerStylesheet('modules-waiap-css', strval($this->getEnviromentUrl()) . self::CSS_PWALL, ['server' => 'remote', 'media' => 'all']);
                $this->context->controller->registerJavascript('modules-waiap-ec-appjs', 'modules/' . $this->name . '/views/js/waiap-expresscheckout-state.js', ['position' => 'top', 'priority' => 200]);
                $this->context->controller->registerJavascript('modules-waiap-ec-statejs', 'modules/' . $this->name . '/views/js/waiap-expresscheckout-paymentwall.js', ['position' => 'top', 'priority' => 200]);
            } else {
                $this->context->controller->addCSS(strval($this->getEnviromentUrl()) . self::CSS_PWALL, 'all');
                $this->context->controller->addJS(strval($this->getEnviromentUrl()) . self::JS_SDK_BUNDLE);
                $this->context->controller->addJS(self::SIPAY_JS_SDK);
                $this->context->controller->addJS($this->_path . 'views/js/waiap-expresscheckout-state.js');
                $this->context->controller->addJS($this->_path . 'views/js/waiap-expresscheckout-paymentwall.js');
            }
        }

        if (Configuration::get('waiap_ec_product_page_enable') == "Enabled") {
            $environment = Configuration::get('waiap_environment');
            $enabled_container_style = Configuration::get('waiap_product_page_container');
            $enabled_position = Configuration::get('waiap_product_page_position_mode');

            Media::addJsDef(["waiap_ec_config" => [
                "enviroment" => $environment,
                "backendUrl" => Context::getContext()->link->getModuleLink($this->name, 'backend', [], Configuration::get('PS_SSL_ENABLED')),
                "profile" => "woocommerce_product_page",
                "quoteInfoUrl" => Context::getContext()->link->getModuleLink($this->name, 'quoteec', [], Configuration::get('PS_SSL_ENABLED')),
                "containerStyle" => $enabled_container_style === 'Enabled' ? [
                    "color" => Configuration::get('waiap_product_page_container_border_color'),
                    "custom_color" => Configuration::get('waiap_product_page_container_custom_border_color'),
                    "header_title" =>  Configuration::get('waiap_product_page_container_header_title'),
                    "header_title_typo" =>  Configuration::get('waiap_product_page_container_header_typography'),
                    "descriptive_text" =>  Configuration::get('waiap_product_page_container_descriptive_text'),
                    "descriptive_text_typo" =>  Configuration::get('waiap_product_page_container_descriptive_text_typo')
                ] : [],
                "positionConfig" => $enabled_position === 'Manual' ? [
                    "insertion" => Configuration::get('waiap_product_page_position_insertion'),
                    "position_selector" => Configuration::get('waiap_product_page_position_selector')
                ] : [],
                "positionStyleConfig" => $enabled_position === 'Manual' ? Configuration::get('waiap_product_page_position_style') : "",
                "storeLogoUrl" => _PS_IMG_ . Configuration::get('PS_LOGO', null, null, (int) Context::getContext()->shop->id),
                "element" => $enabled_position === '1' ? Configuration::get('waiap_product_page_position_selector') : "#waiap_express_checkout"
            ]]);
            return $this->display(__FILE__, 'product_view.tpl');
        }
    }

    public function hookDisplayProductAdditionalInfo()
    {
        return $this->display(__FILE__, 'product_view.tpl');
    }

    public function hookDisplayExpressCheckout()
    {
        return $this->display(__FILE__, 'cart_view.tpl');
    }
}
