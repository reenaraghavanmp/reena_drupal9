<?php
/**
 * @file Class file to get data.
 */

namespace Drupal\reena_weather_block;

/**
 * Class CustomGetServices to get Weather data.
 */
class CustomGetServices {
  public function getWeatherData() {
   
    $city = "Mumbai";
    $app_id = "2b5e8fc2e9331f6a81bd755273b69037";
    $url = "https://api.openweathermap.org/data/2.5/weather?q=$city&appid=$app_id";
      $response = \Drupal::httpClient()->get($url, array('headers' => array('Accept' => 'text/plain')));
      $data = (string) $response->getBody();
      $datavalue = json_decode($data,true);
    return ($datavalue);
  }
}
