<?php

namespace Drupal\commerce_2checkout\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Crypt;

class OffsiteRedirectForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $payment = $this->entity;

    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();

    $extra = [
      'return_url' => $form['#return_url'],
      'cancel_url' => $form['#cancel_url'],
      'capture' => $form['#capture'],
    ];
    $data = $payment_gateway_plugin->createRequestParameters($payment, $extra);

    $order = $payment->getOrder();
    $order->setData('2checkout_offsite_redirect', [
      'flow' => '2co',
      'payment_redirect_key' => Crypt::hashBase64(REQUEST_TIME),
      'payerid' => FALSE,
      'offsite' => TRUE,
    ]);
    $order->save();

    return $this->buildRedirectForm($form, $form_state, $payment_gateway_plugin->getRedirectUrl(), $data, self::REDIRECT_GET);
  }

}
