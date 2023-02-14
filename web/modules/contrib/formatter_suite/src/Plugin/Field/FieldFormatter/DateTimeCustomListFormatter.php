<?php

namespace Drupal\formatter_suite\Plugin\Field\FieldFormatter;

use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeCustomFormatter;

/**
 * Formats multiple custom-formatted dates as a list.
 *
 * See the EntityListTrait for a description of list formatting features.
 *
 * @ingroup formatter_suite
 *
 * @FieldFormatter(
 *   id          = "formatter_suite_datetime_custom_list",
 *   label       = @Translation("Formatter Suite - Custom date & time list"),
 *   weight      = 1000,
 *   field_types = {
 *     "datetime",
 *   }
 * )
 */
class DateTimeCustomListFormatter extends DateTimeCustomFormatter {
  use EntityListTrait;

  /*---------------------------------------------------------------------
   *
   * Settings form.
   *
   *---------------------------------------------------------------------*/
  /**
   * Returns a brief description of the formatter.
   *
   * @return string
   *   Returns a brief translated description of the formatter.
   */
  protected function getDescription() {
    return $this->t("Format multi-value date & time fields as a list. Values may be formatted using a custom date/time format using PHP's format syntax, along with an optional time zone.");
  }

  /**
   * Post-processes the settings form after it has been built.
   *
   * @param array $elements
   *   The form's elements.
   */
  protected function postProcessSettingsForm(array $elements) {
    // The Drupal core DateTimeCustomFormatter creates a textfield for the
    // date/time format, but doesn't set the textfield's size. This makes it
    // hard to lay it out in the formatter's UI. So, give it a small size.
    // CSS then widens it to the width of the UI.
    if (isset($elements['date_format']) === TRUE) {
      $elements['date_format']['#size']                         = 10;
      $elements['date_format']['#attributes']['size']           = 10;
      $elements['date_format']['#attributes']['spellcheck']     = FALSE;
      $elements['date_format']['#attributes']['autocomplete']   = 'off';
      $elements['date_format']['#attributes']['autocapitalize'] = 'none';
      $elements['date_format']['#attributes']['autocorrect']    = 'off';
    }

    return $elements;
  }

}
