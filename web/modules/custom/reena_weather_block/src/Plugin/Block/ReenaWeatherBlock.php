<?php

namespace Drupal\reena_weather_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a custom block with Weather Data
 * @Block (
 *   id = "reena_custom_weather_block",
 *   admin_label = @Translation("Weather Information")
 * )
 */

class ReenaWeatherBlock extends BlockBase {
/**
 * {@inheritdoc}
 */
  public function build() {
    $service = \Drupal::service('reena_weather_block.custom_get_service');
    $result = $service->getWeatherData();
    $rows = [
      [Markup::create('<strong>Temp Min: </strong>'),$result['main']['temp_min']],
      [Markup::create('<strong>Temp Max: <strong>'), $result['main']['temp_max']],
      [Markup::create('<strong>Pressure: <strong>'), $result['main']['pressure']],
      [Markup::create('<strong>Humidity: <strong>'), $result['main']['humidity']],
      [Markup::create('<strong>Wind Speed: <strong>'), $result['wind']['speed']],
    ];
    $header = [
      'title' => t(''),
      'content' => t('Mumbai'),
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No content has been found.'),
    ];

    return [
      '#type' => '#markup',
      '#markup' => render($build)
    ];
  }
}
