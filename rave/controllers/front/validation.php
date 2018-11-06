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

class RaveValidationModuleFrontController extends ModuleFrontController
{
  public function initContent() {

    $vars = Tools::getValue('json');
    $vars = json_decode($vars);

    $publicKey = $vars->publicKey;
    $secretKey = $vars->secretKey;
    $env = $vars->env;
    $ref = $vars->txref;

    $payment = new Rave($publicKey, $secretKey, $ref, $env, true);

    $payment
      ->eventHandler(new PrestaEventHandler)
      ->setAmount($vars->amount)
      ->setPaymentMethod($vars->payment_method) // value can be card, account or both
      ->setDescription($vars->custom_description)
      ->setLogo($vars->custom_logo)
      ->setTitle($vars->custom_title)
      ->setCountry($vars->country)
      ->setCurrency($vars->currency)
      ->setEmail($vars->customer_email)
      ->setFirstname($vars->customer_firstname)
      ->setLastname($vars->customer_lastname)
      ->setRedirectUrl($vars->redirect_url)
      ->setMetaData(array('metaname' => ($vars->metaname), 'metavalue' => ($vars->metavalue)))
      //->setRedirectUrl($URL)
      // ->setMetaData(array('metaname' => 'SomeDataName', 'metavalue' => 'SomeValue')) // can be called multiple times. Uncomment this to add meta datas
      // ->setMetaData(array('metaname' => 'SomeOtherDataName', 'metavalue' => 'SomeOtherValue')) // can be called multiple times. Uncomment this to add meta datas
      ->initialize();
    $this->setTemplate('module:rave/views/templates/hook/css.tpl');
  }
}