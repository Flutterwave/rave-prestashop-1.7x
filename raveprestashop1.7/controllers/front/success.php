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

/**
 * @since 1.5.0
 */

include(dirname(__FILE__) . '/../../sdk/rave/rave.php');
include(dirname(__FILE__) . '/prestaEventHandler.php');


use Flutterwave\Rave;

class RavesuccessModuleFrontController extends ModuleFrontController
{
  /**
   * @see FrontController::postProcess()
   */
  public function postProcess()
  {


    if (Configuration::get('RAVE_ENV') == 1) {
      $env = 'staging';
      $publicKey = Configuration::get('RAVE_TEST_PUBLICKEY');
      $secretKey = Configuration::get('RAVE_TEST_SECRETKEY');
    } else {
      $env = 'live';
      $publicKey = Configuration::get('RAVE_LIVE_PUBLICKEY');
      $secretKey = Configuration::get('RAVE_LIVE_SECRETKEY');
    }

    $ref = '';

    $payment = new Rave($publicKey, $secretKey, $ref, $env, true);


    $cart = $this->context->cart;
        // Handle completed payments
    $payment->logger->notice('Payment completed. Now requerying payment.');
    $currency_order = new Currency($cart->id_currency);
    $currency = $currency_order->iso_code;
    $amount = $cart->getOrderTotal(true, Cart::BOTH);
    $amount = $amount + 0;

    $customer = new Customer($cart->id_customer);

    if (!empty($_GET['cancelled']) && $_GET['txref']) {
        // Handle canceled payments
      $transactionData = $payment
        ->requeryTransaction($_GET['txref']);

      if (!empty($transactionData->chargecode)) {
        if ($transactionData->chargecode === '00' || $transactionData->chargecode === '0') {
          if ($transactionData->currency == $currency && $transactionData->amount == $amount) {
            $extra_vars = array(
              'transaction_id' => $transactionData->txref,
              'flwref' => $transactionData->flwref,
              'id' => 1,
              'payment_method' => 'Rave',
              'status' => 'Paid',
              'currency' => $currency,
              'intent' => '$intent'
            );

            $this->module->validateOrder($cart->id, Configuration::get('PS_OS_RAVE'), $amount, $this->module->displayName, 'Rave txref: ' . $_GET['txref'], $extra_vars, (int)$cart->id_currency, false, $customer->secure_key);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);

          }
        }
      } else {

        Tools::redirect('index.php?controller=order&step=3&error_msg=' . urlencode('You cancelled the transaction from Rave'));

      }



    } elseif ($_GET['txref']) {



      $transactionData = $payment->requeryTransaction($_GET['txref']);

      if ($transactionData->chargecode === '00' || $transactionData->chargecode === '0') {
        if ($transactionData->currency == $currency && $transactionData->amount == $amount) {
          $extra_vars = array(
            'transaction_id' => $transactionData->txref,
            'flwref' => $transactionData->flwref,
            'id' => 1,
            'payment_method' => 'Rave',
            'status' => 'Paid',
            'currency' => $currency,
            'intent' => '$intent'
          );

          $this->module->validateOrder($cart->id, Configuration::get('PS_OS_RAVE'), $amount, $this->module->displayName, 'Rave txref: ' . $_GET['txref'], $extra_vars, (int)$cart->id_currency, false, $customer->secure_key);
          Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);

        }
      }
    }


    $this->module->validateOrder($cart->id, Configuration::get('PS_OS_ERROR'), $amount, $this->module->displayName, null, $extra_vars, (int)$cart->id_currency, false, $customer->secure_key);

    $contactURL = $this->context->link->getPageLink('contact', true);

    die('The order failed, please <a href="'.$contactURL.'">contact our Customer service.</a> or place the order again');
  }
}
