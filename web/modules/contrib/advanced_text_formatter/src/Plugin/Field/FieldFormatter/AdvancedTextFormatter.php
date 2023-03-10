<?php

namespace Drupal\advanced_text_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use \Drupal\Core\Render\Markup;

/**
 * Plugin implementation of the 'advanced_text_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "advanced_text",
 *   module = "advanced_text_formatter",
 *   label = @Translation("Advanced Text"),
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class AdvancedTextFormatter extends FormatterBase {

  const FORMAT_DRUPAL = 'drupal';
  const FORMAT_INPUT = 'input';
  const FORMAT_NONE = 'none';
  const FORMAT_PHP = 'php';
  const FORMAT_LIMIT_HTML = 'limit_html';

  /**
   * Entity type manger.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'trim_length' => 600,
      'ellipsis' => 1,
      'word_boundary' => 1,
      'token_replace' => 0,
      'filter' => 'input',
      'format' => 'plain_text',
      'allowed_html' => '<a> <b> <br> <dd> <dl> <dt> <em> <i> <li> <ol> <p> <strong> <u> <ul>',
      'autop' => 0,
      'use_summary' => 0,
      'link_to_entity' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elTrimLengthId = Html::getUniqueId('advanced_text_formatter_trim');
    $elFilterId     = Html::getUniqueId('advanced_text_formatter_filter');
    $entity_type    = $this->entityManager
      ->getDefinition($this->fieldDefinition
      ->getTargetEntityTypeId());

    $element['trim_length'] = [
      '#id'            => $elTrimLengthId,
      '#type'          => 'number',
      '#title'         => $this->t('Trim length'),
      '#description'   => $this->t("Set this to 0 if you don't want to cut the text. Otherwise, input a positive integer."),
      '#size'          => 10,
      '#default_value' => $this->getSetting('trim_length'),
      '#required'      => TRUE,
    ];

    $element['ellipsis'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Ellipsis'),
      '#description'   => $this->t('If checked, a "..." will be added if a field was trimmed.'),
      '#default_value' => $this->getSetting('ellipsis'),
      '#states'        => [
        'visible' => [
          '#' . $elTrimLengthId => ['!value' => '0'],
        ],
      ],
    ];

    $element['word_boundary'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Word Boundary'),
      '#description'   => $this->t('If checked, this field be trimmed only on a word boundary.'),
      '#default_value' => $this->getSetting('word_boundary'),
      '#states'        => [
        'visible' => [
          '#' . $elTrimLengthId => ['!value' => '0'],
        ],
      ],
    ];

    $element['use_summary'] = [
      '#type'           => 'checkbox',
      '#title'          => $this->t('Use Summary'),
      '#description'    => $this->t('If a summary exists, use it.'),
      '#default_value'  => $this->getSetting('use_summary'),
    ];

    $token_link = _advanced_text_formatter_browse_tokens($this->fieldDefinition->getTargetEntityTypeId());

    $element['token_replace'] = [
      '#type'          => 'checkbox',
      '#description'   => $this->t('Replace text pattern. e.g %node-title-token or %node-author-name-token, by token values.', [
        '%node-title-token'       => '[node:title]',
        '%node-author-name-token' => '[node:author:name]',
      ]) . ' ' /*. $token_link*/,
      '#title'         => $this->t('Token Replace'),
      '#default_value' => $this->getSetting('token_replace'),
    ];

    $element['link_to_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to the @entity_label', [
        '@entity_label' => $entity_type->getLabel(),
      ]),
      '#default_value' => $this->getSetting('link_to_entity'),
    ];

    $element['filter'] = [
      '#id'      => $elFilterId,
      '#title'   => $this->t('Filter'),
      '#type'    => 'select',
      '#options' => [
        static::FORMAT_NONE   => $this->t('None'),
        static::FORMAT_INPUT  => $this->t('Selected Text Format'),
        static::FORMAT_LIMIT_HTML => $this->t('Limit allowed HTML tags'),
        static::FORMAT_DRUPAL => $this->t('Drupal'),
        static::FORMAT_PHP    => $this->t('PHP (Deprecated)'),
      ],
      '#default_value' => $this->getSetting('filter'),
    ];

    $element['format'] = [
      '#title'         => $this->t('Format'),
      '#type'          => 'select',
      '#options'       => [],
      '#default_value' => $this->getSetting('format'),
      '#states'        => [
        'visible' => [
          '#' . $elFilterId  => ['value' => 'drupal'],
        ],
      ],
    ];

    $formats = filter_formats();

    foreach ($formats as $formatId => $format) {
      $element['format']['#options'][$formatId] = $format->get('name');
    }

    $allowedHtml = $this->getSetting('allowed_html');

    if (empty($allowedHtml)) {
      $tags = '';
    }
    elseif (is_string($allowedHtml)) {
      $tags = $allowedHtml;
    }
    else {
      $tags = '<' . implode('> <', $allowedHtml) . '>';
    }

    $element['allowed_html'] = [
      '#type'             => 'textfield',
      '#title'            => $this->t('Allowed HTML tags'),
      '#description'      => $this->t('See <a href="@link" target="_blank">filter_xss()</a> for more information', [
        '@link' => 'http://api.drupal.org/api/drupal/core%21includes%21common.inc/function/filter_xss/8',
      ]),
      '#default_value'    => $tags,
      '#element_validate' => ['_advanced_text_formatter_validate_allowed_html'],
      '#states'           => [
        'visible' => [
          '#' . $elFilterId => ['value' => static::FORMAT_LIMIT_HTML],
        ],
      ],
    ];

    $element['autop'] = [
      '#title'         => $this->t('Converts line breaks into HTML (i.e. &lt;br&gt; and &lt;p&gt;) tags.'),
      '#type'          => 'checkbox',
      '#return_value'  => 1,
      '#default_value' => $this->getSetting('autop'),
      '#states'        => [
        'invisible' => [
          '#' . $elFilterId => ['!value' => static::FORMAT_LIMIT_HTML],
        ],
      ],
    ];

    $element['br'] = ['#markup' => '<br/>'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $yes     = $this->t('Yes');
    $no      = $this->t('No');

    if ($this->getSetting('trim_length') > 0) {
      $summary[] = $this->t('Trim length: @length', ['@length' => $this->getSetting('trim_length')]);
      $summary[] = $this->t('Ellipsis: @ellipsis', ['@ellipsis' => $this->getSetting('ellipsis') ? $yes : $no]);
      $summary[] = $this->t('Word Boundary: @word', ['@word' => $this->getSetting('word_boundary') ? $yes : $no]);
      $summary[] = $this->t('Use Summary: @summary', ['@summary' => $this->getSetting('use_summary') ? $yes : $no]);
    }

    $summary[] = $this->t('Token Replace: @token', ['@token' => $this->getSetting('token_replace') ? $yes : $no]);

    if ($this->getSetting('link_to_entity')) {
      $entity_type = $this->entityManager
        ->getDefinition($this->fieldDefinition
        ->getTargetEntityTypeId());
      $summary[] = $this->t('Linked to the @entity_label', [
        '@entity_label' => $entity_type->getLabel(),
      ]);
    }

    switch ($this->getSetting('filter')) {
      case static::FORMAT_DRUPAL:
        $formats = filter_formats();
        $format  = $this->getSetting('format');
        $format  = isset($formats[$format]) ? $formats[$format]->get('name') : $this->t('Unknown');

        $summary[] = $this->t('Filter: @filter', ['@filter' => $this->t('Drupal')]);
        $summary[] = $this->t('Format: @format', ['@format' => $format]);

        break;

      case static::FORMAT_PHP:
        $this->messenger()->addWarning(t('The PHP filter has been deprecated. Please use the "Limit allowed HTML tags" filter instead.'));

      case static::FORMAT_LIMIT_HTML:
        $text  = [];
        $tags  = $this->getSetting('allowed_html');
        $autop = $this->getSetting('autop');

        if (is_array($tags) && !empty($tags)) {
          $tags = '<' . implode('> <', $tags) . '>';
        }

        if (empty($tags)) {
          $text[] = $this->t('Remove all HTML tags.');
        }
        else {
          $text[] = $this->t('Limit allowed HTML tags: @tags.', ['@tags' => $tags]);
        }

        if (!empty($autop)) {
          $text[] = $this->t('Convert line breaks into HTML.');
        }

        $summary[] = $this->t('Filter: @filter', ['@filter' => implode(' ', $text)]);

        break;

      case static::FORMAT_INPUT:
        $summary[] = $this->t('Filter: @filter', ['@filter' => $this->t('Selected Text Format')]);

        break;

      default:
        $summary[] = $this->t('Filter: @filter', ['@filter' => $this->t('None')]);

        break;
    }

    $summary = array_filter($summary);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $token_data = [
      'user' => \Drupal::currentUser(),
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    ];

    $url = NULL;
    if ($this->getSetting('link_to_entity')) {
      // For the default revision this falls back to 'canonical'
      $url = $items
        ->getEntity()
        ->toUrl('revision');
    }

    foreach ($items as $delta => $item) {
      if ($this->getSetting('use_summary') && !empty($item->summary)) {
        $output = $item->summary;
      }
      else {
        $output = $item->value;
      }

      if ($this->getSetting('token_replace')) {
        $output = \Drupal::token()->replace($output, $token_data);
      }

      switch ($this->getSetting('filter')) {
        case static::FORMAT_DRUPAL:
          $output = check_markup($output, $this->getSetting('format'), $item->getLangcode());

          break;

        case static::FORMAT_PHP:
          $this->messenger()->addWarning(t('The PHP filter has been deprecated. Please use the "Limit allowed HTML tags" filter instead.'));

        case static::FORMAT_LIMIT_HTML:
          $output = Xss::filter($output, $this->getSetting('allowed_html'));

          if ($this->getSetting('autop')) {
            $output = _filter_autop($output);
          }

          break;

        case static::FORMAT_INPUT:
          $output = check_markup($output, $item->format, $item->getLangcode());

          break;
      }

      if ($this->getSetting('trim_length') > 0) {
        $options = [
          'word_boundary' => $this->getSetting('word_boundary'),
          'max_length'    => $this->getSetting('trim_length'),
          'ellipsis'      => $this->getSetting('ellipsis'),
        ];

        $output = advanced_text_formatter_trim_text($output, $options);
      }

      if ($url) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => Markup::create($output),
          '#url' => $url,
        ];
      }
      else {
        $elements[$delta] = [
          '#markup' => $output,
          '#langcode' => $item->getLangcode(),
        ];
      }
    }

    return $elements;
  }

}
