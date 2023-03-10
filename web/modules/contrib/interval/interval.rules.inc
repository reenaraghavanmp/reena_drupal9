<?php

/**
 * @file
 * Provides rules integration for interval module.
 * @copyright Copyright(c) 2011 Lee Rowlands
 * @license GPL v2+ http://www.fsf.org/licensing/licenses/gpl.html
 * @author Lee Rowlands contact at rowlandsgroup dot com
 *
 * @todo Move this to action plugin once there is a semi-stable rules api.
 */

/**
 * Implements hook_rules_action_info().
 */
function interval_rules_action_info() {

  $actions['interval_apply'] = [
    'label' => t('Apply an interval to a date'),
    'parameter' => [
      'interval' => [
        'type' => 'integer',
        'label' => t('Interval number'),
        'description' => t('The number of multiples of the interval period.'),
      ],
      'period' => [
        'type' => 'token',
        'label' => t('Interval period'),
        'options list' => 'interval_period_options_list',
      ],
      'date' => [
        'type' => 'date',
        'label' => t('Date'),
      ],
    ],
    'provides' => [
      'date' => [
        'type' => 'date',
        'label' => t('New date'),
      ],
    ],
    'group' => t('Data'),
    'base' => 'interval_rules_apply_interval',
  ];
  return $actions;
}

/**
 * Rules action callback: Apply an interval to a date.
 */
function interval_rules_apply_interval($interval, $period, $datestamp) {
  $date = new DateObject("@$datestamp");
  $item = [
    'interval' => $interval,
    'period' => $period,
  ];
  interval_apply_interval($date, $item);
  return ['date' => $date->format('U')];
}
