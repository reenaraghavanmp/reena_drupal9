<?php

namespace Drupal\reena_weather_block\Controller;

use Drupal\Core\Controller\ControllerBase;
class CustomController extends ControllerBase
{

  public function showWeather() {
    $service = \Drupal::service('reena_weather_block.custom_get_service');
    $result = $service->getWeatherData();
    echo '<pre>';
    print_r($result['main']);
    echo '<pre>';
    print_r($result['wind']);
    exit();


    return array(
      '#type' => 'markup',
      '#markup' => t('Hello world'),
    );
  }
}
