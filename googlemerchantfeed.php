<?php
/**
 * Google Merchant Feed Generator for PrestaShop 9
 * 
 * Module: googlemerchantfeed
 * Author: Custom development for airone.ch
 * Version: 1.0.0
 * Compatibility: PrestaShop 9.x
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GoogleMerchantFeed extends Module
{
    public function __construct()
    {
        $this->name = 'googlemerchantfeed';
        $this->tab = 'market_place';
        $this->version = '1.0.0';
        $this->author = 'Airone.ch';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Feed');
        $this->description = $this->l('Generate product feeds for Google Merchant Center');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            Configuration::updateValue('GMFEED_LANG', (int) Configuration::get('PS_LANG_DEFAULT')) &&
            Configuration::updateValue('GMFEED_CURRENCY', 'CHF') &&
            Configuration::updateValue('GMFEED_SHIPPING_COUNTRY', 'CH') &&
            Configuration::updateValue('GMFEED_SHIPPING_PRICE', '0.00') &&
            Configuration::updateValue('GMFEED_SECRET_KEY', bin2hex(random_bytes(16)));
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('GMFEED_LANG') &&
            Configuration::deleteByName('GMFEED_CURRENCY') &&
            Configuration::deleteByName('GMFEED_SHIPPING_COUNTRY') &&
            Configuration::deleteByName('GMFEED_SHIPPING_PRICE') &&
            Configuration::deleteByName('GMFEED_SECRET_KEY');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $lang = (int) Tools::getValue('GMFEED_LANG');
            $currency = pSQL(Tools::getValue('GMFEED_CURRENCY'));
            $shipping_country = pSQL(Tools::getValue('GMFEED_SHIPPING_COUNTRY'));
            $shipping_price = (float) Tools::getValue('GMFEED_SHIPPING_PRICE');

            Configuration::updateValue('GMFEED_LANG', $lang);
            Configuration::updateValue('GMFEED_CURRENCY', $currency);
            Configuration::updateValue('GMFEED_SHIPPING_COUNTRY', $shipping_country);
            Configuration::updateValue('GMFEED_SHIPPING_PRICE', $shipping_price);

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        if (Tools::isSubmit('regenerate_key')) {
            Configuration::updateValue('GMFEED_SECRET_KEY', bin2hex(random_bytes(16)));
            $output .= $this->displayConfirmation($this->l('Secret key regenerated'));
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $languages = Language::getLanguages(true);
        $lang_options = [];
        foreach ($languages as $lang) {
            $lang_options[] = [
                'id_option' => $lang['id_lang'],
                'name' => $lang['name'],
            ];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Google Merchant Feed Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Feed Language'),
                        'name' => 'GMFEED_LANG',
                        'options' => [
                            'query' => $lang_options,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Currency'),
                        'name' => 'GMFEED_CURRENCY',
                        'size' => 5,
                        'desc' => $this->l('ISO currency code (e.g., CHF, EUR)'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Shipping Country'),
                        'name' => 'GMFEED_SHIPPING_COUNTRY',
                        'size' => 5,
                        'desc' => $this->l('ISO country code (e.g., CH, FR)'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Default Shipping Price'),
                        'name' => 'GMFEED_SHIPPING_PRICE',
                        'size' => 10,
                        'desc' => $this->l('Default shipping cost (set 0 for free shipping)'),
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Feed URL'),
                        'name' => 'feed_url_display',
                        'html_content' => '<div class="alert alert-info">' .
                            '<strong>' . $this->l('Your feed URL:') . '</strong><br>' .
                            '<code>' . $this->getFeedUrl() . '</code>' .
                            '<br><br>' . $this->l('Use this URL in Google Merchant Center for scheduled fetch.') .
                            '</div>',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
                'buttons' => [
                    [
                        'href' => $this->getFeedUrl(),
                        'title' => $this->l('Preview Feed'),
                        'icon' => 'process-icon-preview',
                        'target' => '_blank',
                    ],
                    [
                        'type' => 'submit',
                        'name' => 'regenerate_key',
                        'title' => $this->l('Regenerate Secret Key'),
                        'icon' => 'process-icon-refresh',
                        'class' => 'btn btn-warning',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit' . $this->name;

        $helper->fields_value['GMFEED_LANG'] = Configuration::get('GMFEED_LANG');
        $helper->fields_value['GMFEED_CURRENCY'] = Configuration::get('GMFEED_CURRENCY');
        $helper->fields_value['GMFEED_SHIPPING_COUNTRY'] = Configuration::get('GMFEED_SHIPPING_COUNTRY');
        $helper->fields_value['GMFEED_SHIPPING_PRICE'] = Configuration::get('GMFEED_SHIPPING_PRICE');

        return $helper->generateForm([$fields_form]);
    }

    public function getFeedUrl()
    {
        $key = Configuration::get('GMFEED_SECRET_KEY');
        return Context::getContext()->shop->getBaseURL(true) . 'modules/' . $this->name . '/feed.php?key=' . $key;
    }
}
