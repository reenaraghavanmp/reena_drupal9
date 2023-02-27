<?php

namespace Drupal\reena_event_example\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\reena_event_example\Event\ExampleEvent;

class EventExampleForm extends FormBase {


  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['select_option'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => t('Select any one Option'),
      '#options' => [
        'allow' => $this->t('Allow'),
        'none' => $this->t('None'),

      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Dispatch'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_example_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getValue('select_option');
    \Drupal::messenger()->addMessage($this->t('Event Subscriber Form Submitted successfully '. $type), 'status', TRUE);
//    $dispatcher = \Drupal::service('event_dispatcher');
//    $event = new ExampleEvent($form_state->getValue('type'));
//    $dispatcher->dispatch(ExampleEvent::SUBMIT, $event);
  }

}
