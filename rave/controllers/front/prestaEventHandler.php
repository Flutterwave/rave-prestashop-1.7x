<?php

include(dirname(__FILE__) . '/../../sdk/rave/raveEventHandlerInterface.php');
use Flutterwave\Rave\EventHandlerInterface;

class PrestaEventHandler implements EventHandlerInterface
{
  private $cart, $info;

  function __construct($cart=null, $info=null)
  {
    $this->cart = $cart;
    $this->info = json_decode($info,false);
  }
  /**
   * This is called when the Rave class is initialized
   * */
  function onInit($initializationData)
  {
        // Save the transaction to your DB.
    //echo 'Payment started......' . json_encode($initializationData) . '<br />'; //Remember to delete this line
  }

  /**
   * This is called only when a transaction is successful
   * */
  function onSuccessful($transactionData)
  {
    if ($transactionData->chargecode === '00' || $transactionData->chargecode === '0') {
      if ($transactionData->currency == $this->info->currency && $transactionData->amount == $this->info->amount) {

// var_dump($this->info->module);
// die();

        $extra_vars = array(
          'transaction_id' => $transactionData->txref,
          'flwref' => $transactionData->flwref,
          'id' => 1,
          'payment_method' => 'Rave',
          'status' => 'Paid',
          'currency' => $this->info->currency,
          'intent' => '$intent'
        );


        $this->info->module->validateOrder(
          $cart->id,
          Configuration::get('PS_OS_RAVE'),
          $amount,
          $this->info->module->displayName,
          'Rave Reference: ' . $transactionData->txref,
          $extra_vars,
          (int)$cart->id_currency,
          false,
          $this->info->customer->secure_key
        );

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->info->module->id . '&id_order=' . $this->info->module->currentOrder . '&key=' . $this->info->customer->secure_key);

      } else {

        $date = date("Y-m-d h:i:sa");
        $email = $email;
        $total = $amount;
        $status = 'failed';
      }
    } else {

    }
    
  }

  /**
   * This is called only when a transaction failed
   * */
  function onFailure($transactionData)
  {

    return false;
  }

  /**
   * This is called when a transaction is requeryed from the payment gateway
   * */
  function onRequery($transactionReference)
  {
  }

  /**
   * This is called a transaction requery returns with an error
   * */
  function onRequeryError($requeryResponse)
  {

  }

  /**
   * This is called when a transaction is canceled by the user
   * */
  function onCancel($transactionReference)
  {
    return false;
  }

  /**
   * This is called when a transaction doesn't return with a success or a failure response. This can be a timedout transaction on the Rave server or an abandoned transaction by the customer.
   * */
  function onTimeout($transactionReference, $data)
  {

  }
}