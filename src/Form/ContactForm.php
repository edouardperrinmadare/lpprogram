<?php

namespace Drupal\lp_programs\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Render\RendererInterface;

/**
 * Form import cotisations.
 */
class ContactForm extends FormBase {


  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  protected $renderer;

  protected $currentUser;

  protected $entityTypeManager;

  protected $fileSystem;

  protected $configFactory;

  protected $token;

  protected $routeMatch;

  /**
   * SendGridTestForm constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MailManagerInterface $mail_manager, RendererInterface $renderer, AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, TokenInterface $token, RouteMatchInterface $route_match) {
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('current_route_match')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lp_jobs.candidacy_job';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes'] = ['class' => ['hbspt-form']];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Sujet'),
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this
        ->t('Votre message'),
      '#required' => TRUE,
    ];
    $form['firstname'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Prénom'),
      '#required' => TRUE,
    ];
    $form['surname'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Nom'),
      '#required' => TRUE,
    ];
    $form['mail'] = [
      '#type' => 'mail',
      '#title' => $this
        ->t('E-mail'),
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type' => 'phone_international',
      '#title' => $this
        ->t('N° de téléphone'),
      '#country' => 'FR',
      '#default_value' => '',
      '#exclude_countries' => [],
      '#preferred_countries' => ['FR'],
      '#geolocation' => 1,
      '#required' => TRUE,
    ];
    $form['address'] = [
      '#type' => 'address',
      '#default_value' => ['country_code' => 'FR'],
      '#used_fields' => [
        AddressField::LOCALITY,
        AddressField::POSTAL_CODE,
      ],
      '#available_countries' => ['FR'],
      '#required' => TRUE,
    ];
    $form['newsletter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("J'accepte de recevoir les dernières informations de LP Promotion par mail."),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['hs_submit']],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t("J'envoie ma candidature"),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['hs-button', 'use-ajax']],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    // Add Honey Pot protection.
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('honeypot')) {
      honeypot_add_form_protection(
        $form,
        $form_state,
        ['honeypot', 'time_restriction']
      );
    }

    return $form;
  }

  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
      return $response;
    }
    $storage = $form_state->getStorage();

    // Build modal confirm or error.
    $modal_settings = $this->configFactory->get('lp_jobs.candidacy_jobs_settings')
      ->get('modal');
    $message = $modal_settings['text_error'] ?? t("Une erreur est surevenue lors de l'envoi de votre demande. Merci de réessayer ultérieurement.");
    $title = $modal_settings['title_error'] ?? t("Erreur lors l'envois");
    if ($storage['send_mail']) {
      $message = $modal_settings['text_confirm'] ?? t("Votre message a bien été envoyé");
      $title = $modal_settings['title_confirm'] ?? t("Message envoyé");
    }
    $response->addCommand(new OpenModalDialogCommand($title, $message));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $mail_settings = $this->configFactory->get('lp_programs.contact_settings')
      ->get('mail');
    $body = ['#theme' => 'lp_programs_contact_mail'];
    $values = $form_state->getValues();
    // Build data template and token.
    $token_replace = [];
    foreach ($values as $key => $value) {
      $body['#' . $key] = $value;
      $token_replace[$key] = $value;
      if ($key == 'address') {
        foreach ($value as $key_address => $address_value) {
          if (!empty($address_value)) {
            $token_replace[$key_address] = $address_value;
          }
        }
      }
    }
    if (!empty($mail_settings['body'])) {
      $node = $this->routeMatch->getParameter('node');
      // Insert body text with token replace.
      $body['#body'] = $this->token->replace($mail_settings['body'], [
        'lp_programs' => $token_replace,
        'node' => $node,
      ], [
        'clear' => TRUE,
        'sanitize' => TRUE,
      ]);
    }
    $params['message'] = $this->renderer->render($body);
    $params['subject'] = $form_state->getValue('subject');
    $send = TRUE;
    // Add "to" mail from settings.
    $to = $mail_settings['to'] ? implode(',', explode(PHP_EOL, $mail_settings['to'])) : '';
    if ((!empty($mail_settings['to_default_mail']) && $mail_settings['to_default_mail'] == TRUE) || empty($mail_settings['to'])) {
      $to = \Drupal::config('system.site')->get('mail');
    }
    // Send the mail.
    $result = $this->mailManager->mail('lp_programs', 'contact', $to, $this->currentUser->getPreferredAdminLangcode(), $params, NULL, $send);
    $storage = $form_state->getStorage();
    $storage['send_mail'] = !empty($result['result']);
    $form_state->setStorage($storage);
  }

}
