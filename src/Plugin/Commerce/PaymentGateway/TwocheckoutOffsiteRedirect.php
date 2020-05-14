<?php

namespace Drupal\commerce_2checkout\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides the 2Checkout offsite redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "twocheckout_offsite_redirect",
 *   label = @Translation("2Checkout (Offsite Redirect)"),
 *   display_label = @Translation("2Checkout"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_2checkout\PluginForm\OffsiteRedirectForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 * )
 */
class TwocheckoutOffsiteRedirect extends OffsitePaymentGatewayBase implements TwocheckoutOffsiteRedirectInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The price rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new PaymentGatewayBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The price rounder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentTypeManager $payment_type_manager,
    PaymentMethodTypeManager $payment_method_type_manager,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_channel_factory,
    ClientInterface $client,
    RounderInterface $rounder,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $event_dispatcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->logger = $logger_channel_factory->get('commerce_2checkout');
    $this->httpClient = $client;
    $this->rounder = $rounder;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
      $container->get('http_client'),
      $container->get('commerce_price.rounder'),
      $container->get('module_handler'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Return 2Checkout supported languages.
   */
  public static function supportedLanguages() {
    // List taken from https://www.2checkout.com/documentation/checkout/parameter-sets/pass-through-products.
    return [
      'en' => t('English'),
      'zh' => t('Chinese'),
      'da' => t('Danish'),
      'nl' => t('Dutch'),
      'fr' => t('French'),
      'gr' => t('German'),
      'el' => t('Greek'),
      'it' => t('Italian'),
      'jp' => t('Japanese'),
      'no' => t('Norwegian'),
      'pt' => t('Portugese'),
      'sl' => t('Slovenian'),
      'es_ib' => t('Spanish (es_ib)'),
      'es_la' => t('Spanish (es_la)'),
      'sv' => t('Swedish'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'merchant_code' => '',
      'secret_word' => '',
      'language' => 'en',
      'demo' => TRUE,
      'skip_order_review' => FALSE,
      'direct_checkout' => FALSE,
      'third_party_cart' => FALSE,
      'tangible' => FALSE,
      'logging' => 'full',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    $form['merchant_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant code'),
      '#description' => $this->t('2Checkout merchant code.'),
      '#default_value' => $configuration['merchant_code'],
      '#required' => TRUE,
    ];

    $form['secret_word'] = [
      '#type' => 'password',
      '#title' => $this->t('Secret key'),
      '#description' => $this->t('The secret key from the 2Checkout manager.'),
      '#default_value' => $configuration['secret_word'],
      '#required' => empty($configuration['secret_word']),
    ];

    $form['demo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Demo'),
      '#description' => $this->t('Enable demo mode.'),
      '#default_value' => $configuration['demo'],
    ];

    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Checkout page language'),
      '#options' => self::supportedLanguages(),
      '#default_value' => $configuration['language'],
    ];

    $form['skip_order_review'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip order review'),
      '#description' => $this->t('Skip the order review page of the multi-page purchase routine.'),
      '#default_value' => $configuration['skip_order_review'],
    ];

    $form['direct_checkout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inline checkout'),
      '#description' => $this->t('If disabled, customer will go to standard checkout.'),
      '#default_value' => $configuration['direct_checkout'],
    ];

    $form['third_party_cart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Third-party cart'),
      '#description' => $this->t('Select to use third-party cart parameters. Do not select to use Pass Through Products (Lists line item prices at 2Checkout).'),
      '#default_value' => $configuration['third_party_cart'],
    ];

    $form['tangible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Tangible'),
      '#description' => $this->t('Order items are tangible.'),
      '#default_value' => $configuration['tangible'],
    ];

    $form['logging'] = [
      '#type' => 'radios',
      '#title' => t('Logging'),
      '#options' => [
        'notification' => $this->t('Log notifications during processing.'),
        'full' => $this->t('Log notifications with all data during validation and processing (used for debugging).'),
      ],
      '#default_value' => $configuration['logging'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      foreach ($this->defaultConfiguration() as $key => $value) {
        if (isset($values[$key])) {
          $this->configuration[$key] = $values[$key];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    // @TODO: Check whether this is still needed, as sandbox seems to be replaced with "demo" mode.
    return ($this->getMode() == 'test')
      ? 'https://sandbox.2checkout.com/checkout/purchase'
      : 'https://www.2checkout.com/checkout/purchase';
  }

  /**
   * Build an array of 2checkout request parameters.
   */
  public function createRequestParameters(PaymentInterface $payment, array $extra) {
    $order = $payment->getOrder();
    $configuration = $this->getConfiguration();
    $billing_address = $order->getBillingProfile()->get('address')->first();

    $name = $billing_address->getGivenName() . ' ' . $billing_address->getFamilyName();
    $data = [
      // General parameters.
      'sid' => $configuration['merchant_code'],
      'lang' => $configuration['language'],
      'merchant_order_id' => $order->id(),
      'pay_method' => 'CC',
      'skip_landing' => $configuration['skip_order_review'],
      'x_receipt_link_url' => $extra['return_url'],
      'coupon' => '',
      'mode' => '2CO',
      'currency_code' => $payment->getAmount()->getCurrencyCode(),

      // Billing address details.
      'card_holder_name' => substr($name, 0, 128),
      'street_address' => substr($billing_address->getAddressLine1(), 0, 64),
      'street_address2' => substr($billing_address->getAddressLine2(), 0, 64),
      'city' => substr($billing_address->getLocality(), 0, 64),
      'state' => substr($billing_address->getAdministrativeArea(), 0, 64),
      'country' => $billing_address->getCountryCode(),
      'zip' => substr($billing_address->getPostalCode(), 0, 16),
    ];

    // Y to enable demo mode. Do not pass this in for live sales.
    if ($configuration['demo'] == 1) {
      $data['demo'] = 'Y';
    }

    // Check if the order references shipments.
    // Many thanks to Commerce PayPal module developers!
    if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      // Gather the shipping profiles and only send shipping information if
      // there's only one shipping profile referenced by the shipments.
      $shipping_profiles = [];

      // Loop over the shipments to collect shipping profiles.
      foreach ($order->get('shipments')->referencedEntities() as $shipment) {
        if ($shipment->get('shipping_profile')->isEmpty()) {
          continue;
        }
        $shipping_profile = $shipment->getShippingProfile();
        $shipping_profiles[$shipping_profile->id()] = $shipping_profile;
      }

      // Don't send the shipping profile if we found more than one.
      if ($shipping_profiles && count($shipping_profiles) === 1) {
        $shipping_profile = reset($shipping_profiles);
        /** @var \Drupal\address\AddressInterface $address */
        $address = $shipping_profile->address->first();
        $name = $address->getGivenName() . ' ' . $address->getFamilyName();
        $shipping_info = [
          'ship_name' => substr($name, 0, 128),
          'ship_street_address' => substr($address->getAddressLine1(), 0, 64),
          'ship_street_address2' => substr($address->getAddressLine2(), 0, 64),
          'ship_city' => substr($address->getLocality(), 0, 64),
          'ship_state' => substr($address->getAdministrativeArea(), 0, 64),
          'ship_country' => $address->getCountryCode(),
          'ship_zip' => substr($address->getPostalCode(), 0, 16),
        ];

        // Filter out empty values.
        $data += array_filter($shipping_info);
      }
    }

    // Emit a line for every product.
    $i = 0;
    foreach ($order->getItems() as $line_item) {
      $item_amount = $this->rounder->round($line_item->getUnitPrice());
      $data['li_' . $i . '_type'] = 'product';
      $data['li_' . $i . '_name'] = $line_item->getTitle();
      $data['li_' . $i . '_quantity'] = $line_item->getQuantity();
      $data['li_' . $i . '_price'] = $item_amount->getNumber();
      $data['li_' . $i . '_tangible'] = $configuration['tangible'] ? 'Y' : 'N';
      $i++;
    }

    return $data;
  }

}
