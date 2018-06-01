<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Rave extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;
    
    public function __construct()
    {
        $this->name = 'rave';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Oluwole Adebiyi';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('RAVE_LIVE_SECRETKEY', 'RAVE_LIVE_PUBLICKEY', 'RAVE_TEST_SECRETKEY', 'RAVE_TEST_PUBLICKEY', 'RAVE_MERCHANT_LOGO','RAVE_PAYMENT_METHOD','RAVE_MERCHANT_COUNTRY','RAVE_ENV'));
     
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Rave', array(), 'Modules.Rave.Admin');
        $this->description = $this->trans('Accept payments via Rave.', array(), 'Modules.Rave.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing Rave?', array(), 'Modules.Rave.Admin');
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->registerHook('header')) {
            return false;
        }
        // TODO : Cek insert new state, Custom CSS
        $newState = new OrderState();
        
        $newState->send_email = true;
        $newState->module_name = $this->name;
        $newState->invoice = true;
        $newState->color = "#04b404";
        $newState->color = "#04b404";
        $newState->unremovable = false;
        $newState->logable = true;
        $newState->delivery = false;
        $newState->hidden = false;
        $newState->shipped = false;
        $newState->paid = true;
        $newState->delete = false;

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            if ($lang['iso_code'] == 'id') {
                $newState->name[(int)$lang['id_lang']] = 'Menunggu pembayaran via Rave';
            } else {
                $newState->name[(int)$lang['id_lang']] = 'Payment successful via Rave';
            }
            $newState->template = "payment";
        }

        if ($newState->add()) {
            Configuration::updateValue('PS_OS_RAVE', $newState->id);
            copy(dirname(__FILE__).'/logo.png', _PS_IMG_DIR_.'tmp/order_state_mini_'.(int)$newState->id.'_1.png');
        } else {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('RAVE_LIVE_SECRETKEY')
            || !Configuration::deleteByName('RAVE_LIVE_PUBLICKEY') 
            || !Configuration::deleteByName('RAVE_TEST_SECRETKEY')
            || !Configuration::deleteByName('RAVE_TEST_PUBLICKEY')
                || !Configuration::deleteByName('RAVE_MERCHANT_LOGO')
                || !Configuration::deleteByName('RAVE_PAYMENT_METHOD')
                || !Configuration::deleteByName('RAVE_MERCHANT_COUNTRY')
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('RAVE_LIVE_SECRETKEY', Tools::getValue('RAVE_LIVE_SECRETKEY'));
            Configuration::updateValue('RAVE_LIVE_PUBLICKEY', Tools::getValue('RAVE_LIVE_PUBLICKEY'));
            Configuration::updateValue('RAVE_TEST_SECRETKEY', Tools::getValue('RAVE_TEST_SECRETKEY'));
            Configuration::updateValue('RAVE_TEST_PUBLICKEY', Tools::getValue('RAVE_TEST_PUBLICKEY'));
            Configuration::updateValue('RAVE_MERCHANT_LOGO', Tools::getValue('RAVE_MERCHANT_LOGO'));
            Configuration::updateValue('RAVE_PAYMENT_METHOD', Tools::getValue('RAVE_PAYMENT_METHOD'));
            Configuration::updateValue('RAVE_MERCHANT_COUNTRY', Tools::getValue('RAVE_MERCHANT_COUNTRY'));
            Configuration::updateValue('RAVE_ENV', Tools::getValue('RAVE_ENV'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    private function _displayInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayInfo();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }


        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];
        return $payment_options;
    }

    public function getExternalPaymentOption($params)
    {
        $config = $this->getConfigFieldsValues();
        if ($config['RAVE_ENV'] == 1) {
            $env = 'staging';
            $publicKey = $config['RAVE_TEST_PUBLICKEY'];
            $secretKey = $config['RAVE_TEST_SECRETKEY'];
        } else {
            $env = 'live';
            $publicKey = $config['RAVE_LIVE_PUBLICKEY'];
            $secretKey = $config['RAVE_LIVE_SECRETKEY'];
        }

        $ref = 'order_' . $params['cart']->id . '_' . time();
        $redirectURL = $this->context->link->getModuleLink($this->name, 'success', array(), true);

        $country = $config['RAVE_MERCHANT_COUNTRY'];

            $cart = $this->context->cart;
            $gateway_chosen = 'rave';
            $customer = new Customer((int)($cart->id_customer));
            $currency_order = new Currency($cart->id_currency);
            $currency = $currency_order->iso_code;

            $amountToBePaid = $cart->getOrderTotal(true, Cart::BOTH);

            $postfields = array();
            $postfields['publicKey'] = $publicKey;
            $postfields['secretKey'] = $secretKey;
            $postfields['env'] = $env;
            $postfields['customer_email'] = $customer->email;
            $postfields['customer_firstname'] = $customer->firstname;
            $postfields['customer_lastname'] = $customer->lastname;
            $postfields['custom_logo'] = $config['RAVE_MERCHANT_LOGO'];
            $postfields['custom_description'] = "Payment for Cart: " . $cart->id . " on " . Configuration::get('PS_SHOP_NAME');
            $postfields['custom_title'] = Configuration::get('PS_SHOP_NAME');
            $postfields['country'] = $country;
            $postfields['redirect_url'] = $redirectURL;
            $postfields['txref'] = $ref;
            $postfields['payment_method'] = $config['RAVE_PAYMENT_METHOD'];
            $postfields['amount'] = $amountToBePaid + 0;
            $postfields['currency'] = $currency;
            $postfields['hosted_payment'] = 1;

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Rave'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:rave/views/templates/hook/css.tpl'))
            ->setAdditionalInformation($this->context->smarty->fetch('module:rave/views/templates/front/process.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/rave.png'));

        if ($gateway_chosen == 'rave') {
            $externalOption->setInputs([
                'json' => [
                    'name' => 'json',
                    'type' => 'hidden',
                    'value' => json_encode($postfields),
                ],
            ]);
        }


        return $externalOption;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        $reference = Tools::getValue('reference');
        if ($reference == "" || $reference == NULL) {
            $reference = $params['order']->reference;
        }
        if (in_array(
            $state,
            array(
                Configuration::get('PS_OS_RAVE'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
                Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
            )
        )) {
           
            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'status' => 'ok',
                'reference' => $reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:rave/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('User details', array(), 'Modules.Rave.Admin'),
                    'icon' => 'icon-user'
                ),
        
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Test Mode', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_ENV',
                        'is_bool' => true,
                        'required' => true,
                         'values' =>array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Test', array(), 'Modules.Rave.Admin')
                            ),array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('False', array(), 'Modules.Rave.Admin')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Live Public key', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_LIVE_PUBLICKEY',
                        'required' => true
                    ),

                    array(
                        'type' => 'text',
                        'label' => $this->trans('Live Secret key', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_LIVE_SECRETKEY',
                        'required' => true
                    ),

                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test Public key', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_TEST_PUBLICKEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test Secret key', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_TEST_SECRETKEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Logo (preferrably a square size)'),
                        'name' => 'RAVE_MERCHANT_LOGO',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Payment Method', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_PAYMENT_METHOD',
                        'options' =>array(
                            'query' => array(
                                array(
                                    'id_option_api' => 'both',
                                    'name_option_api' => $this->l('All')
                                ),
                                array(
                                    'id_option_api' => 'card',
                                    'name_option_api' => $this->l('Card Only')
                                ),
                                array(
                                    'id_option_api' => 'account',
                                    'name_option_api' => $this->l('Account Only')
                                ),
                                array(
                                    'id_option_api' => 'ussd',
                                    'name_option_api' => $this->l('USSD Only')
                                )
                            ),
                            'id' => 'id_option_api',
                            'name' => 'name_option_api'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Country', array(), 'Modules.Rave.Admin'),
                        'name' => 'RAVE_MERCHANT_COUNTRY',
                        'options' =>array(
                            'query' => array(
                                array(
                                    'id_option_api' => 'NG',
                                    'name_option_api' => $this->l('Nigeria')
                                ),
                                array(
                                    'id_option_api' => 'GH',
                                    'name_option_api' => $this->l('Ghana')
                                ),
                                array(
                                    'id_option_api' => 'KE',
                                    'name_option_api' => $this->l('Kenya')
                                ),
                                array(
                                    'id_option_api' => 'ZA',
                                    'name_option_api' => $this->l('South Africa')
                                )
                            ),
                            'id' => 'id_option_api',
                            'name' => 'name_option_api'
                        ),
                    ), 
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fields_form_customization = array();

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'RAVE_LIVE_SECRETKEY' => Tools::getValue('RAVE_LIVE_SECRETKEY', Configuration::get('RAVE_LIVE_SECRETKEY')),
            'RAVE_LIVE_PUBLICKEY' => Tools::getValue('RAVE_LIVE_PUBLICKEY', Configuration::get('RAVE_LIVE_PUBLICKEY')),
            'RAVE_TEST_SECRETKEY' => Tools::getValue('RAVE_TEST_SECRETKEY', Configuration::get('RAVE_TEST_SECRETKEY')),
            'RAVE_TEST_PUBLICKEY' => Tools::getValue('RAVE_TEST_PUBLICKEY', Configuration::get('RAVE_TEST_PUBLICKEY')),
            'RAVE_MERCHANT_LOGO' => Tools::getValue('RAVE_MERCHANT_LOGO', Configuration::get('RAVE_MERCHANT_LOGO')),
            'RAVE_PAYMENT_METHOD' => Tools::getValue('RAVE_PAYMENT_METHOD', Configuration::get('RAVE_PAYMENT_METHOD')),
            'RAVE_MERCHANT_COUNTRY' => Tools::getValue('RAVE_MERCHANT_COUNTRY', Configuration::get('RAVE_MERCHANT_COUNTRY')),
            'RAVE_ENV' => Tools::getValue('RAVE_ENV', Configuration::get('RAVE_ENV')),
        );
    }
}
