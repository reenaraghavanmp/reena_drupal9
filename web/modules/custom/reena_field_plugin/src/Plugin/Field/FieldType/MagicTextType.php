<?php

namespace Drupal\reena_field_plugin\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'magictext_type' field type.
 *
 * @FieldType(
 *   id = "magictext_type",
 *   label = @Translation("Background Color for text field"),
 *   module = "reena_field_plugin.js",
 *   description = @Translation("Demonstrates a field composed of Background color."),
 *   default_widget = "magictext_widget",
 *   default_formatter = "magictext_formatter"
 * )
 */

class MagicTextType extends FieldItemBase {
  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'tiny',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Hex value'));

    return $properties;

  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';

  }
}
