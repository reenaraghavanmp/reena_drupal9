<?php

/**
 * @file
 * Contains Drupal\reena_weather_block\Form\WeatherAppIdForm
 */

namespace Drupal\reena_weather_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class WeatherAppIdForm extends ConfigFormBase {
  /**
   * {@inheritdoc }
   */
 public function getEditableConfigNames()
 {
   return [
     'reena_weather_block.settings',
   ];
 }

  public function getFormId()
  {
    return 'weather_app_id_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);
    $config =$this->config('reena_weather_block.settings');
    $form['weather_app_id_settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Weather APP ID'),
      '#default_value' => $config->get('reena_weather_block.weather_app_id_settings'),
      '#description' =>  $this->t('Enter Weather APP ID'),
    ];
    return $form;

  }
  /**
   * {@inheritdoc }
   */
//  public function validateForm(array &$form, FormStateInterface $form_state)
//  {
//    $form =  parent::validateForm($form, $form_state);
//  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->config('reena_weather_block.settings');
    $config->set('reena_weather_block.weather_app_id_settings', $form_state->getValue('weather_app_id_settings'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }


}
