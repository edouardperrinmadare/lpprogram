<?php

namespace Drupal\lp_programs\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'program_alert_mail_queue_worker' queue worker.
 *
 * @QueueWorker(
 *   id = "program_alert_mail_queue_worker",
 *   title = @Translation("Program Alert Mail Queue Worker"),
 *   cron = {"time" = 30}
 * )
 */
class ProgramAlertMailQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $result = $mailManager->mail($data['module'], $data['key'], $data['to'], $data['langcode'], $data['params'], NULL, $data['send']);
    if ($result['result'] !== TRUE) {
      \Drupal::logger('lp_progams')->error('There was a problem sending message to @to and it was not sent.',
        ['@to' => $data['to']]
      );
    }
    else {
      \Drupal::logger('lp_progams')->notice('Message to @to has been sent.',
        ['@to' => $data['to']]
      );
    }
  }

}
