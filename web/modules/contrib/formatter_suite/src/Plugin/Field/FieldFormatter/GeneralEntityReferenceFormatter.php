<?php

namespace Drupal\formatter_suite\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\formatter_suite\Branding;

/**
 * Formats an entity reference as one or more links.
 *
 * An entity reference indicates the entity ID of a target entity. Every
 * entity has a label.
 *
 * This formatter supports:
 *   - Showing a title:
 *     - Using the reference entity's title.
 *     - Using the reference entity's ID.
 *     - Using the URL in plain text.
 *     - Using manually entered text.
 *   - Adding manually entered classes.
 *   - Showing a link:
 *     - Adding selected standard "rel" and "target" options.
 *
 * The "rel" and "target" options are grouped and presented as menu choices
 * and checkboxes.
 *
 * @ingroup formatter_suite
 *
 * @FieldFormatter(
 *   id          = "formatter_suite_general_entity_reference",
 *   label       = @Translation("Formatter Suite - General entity reference"),
 *   weight      = 1000,
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class GeneralEntityReferenceFormatter extends EntityReferenceFormatterBase {

  /*---------------------------------------------------------------------
   *
   * Fields - dependency injection.
   *
   *---------------------------------------------------------------------*/
  /**
   * The token selection UI builder if the Token module is installed.
   *
   * If the Token module is not installed, this is a NULL.
   *
   * @var \Drupal\token\TreeBuilder
   */
  protected $tokenTreeBuilder;

  /**
   * The token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /*---------------------------------------------------------------------
   *
   * Construction.
   *
   *---------------------------------------------------------------------*/
  /**
   * Constructs a FormatterBase object.
   *
   * @param string $pluginId
   *   The pluginId for the formatter.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $viewMode
   *   The view mode.
   * @param array $thirdPartySettings
   *   Any third party settings.
   * @param \Drupal\Core\Utility\Token $tokenService
   *   The token replacement service.
   * @param \Drupal\token\TreeBuilder|null $tokenTreeBuilder
   *   The token selection UI builder, if the Token module is enabled.
   */
  public function __construct(
    $pluginId,
    $pluginDefinition,
    FieldDefinitionInterface $fieldDefinition,
    array $settings,
    $label,
    $viewMode,
    array $thirdPartySettings,
    Token $tokenService,
    $tokenTreeBuilder) {

    parent::__construct(
      $pluginId,
      $pluginDefinition,
      $fieldDefinition,
      $settings,
      $label,
      $viewMode,
      $thirdPartySettings);

    $this->tokenService = $tokenService;
    $this->tokenTreeBuilder = $tokenTreeBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition) {

    $tokenService = NULL;
    try {
      $tokenService = $container->get('token');
    }
    catch (\Exception $e) {
      // Token services are not available? How? This is a core service.
    }

    $tokenTreeBuilder = NULL;
    try {
      $tokenTreeBuilder = $container->get('token.tree_builder');
    }
    catch (\Exception $e) {
      // The contributed Token module is not installed.
    }

    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $tokenService,
      $tokenTreeBuilder);
  }

  /*---------------------------------------------------------------------
   *
   * Configuration.
   *
   *---------------------------------------------------------------------*/
  /**
   * Returns an array of formatting styles.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   human-readable translated names as values.
   */
  protected static function getEntityReferenceStyles() {
    // Some of these have special case code below so that they work
    // well for msot entity types.
    return [
      'id'                 => t("Entity's ID"),
      'title'              => t("Entity's title"),
      'title-id'           => t("Entity's title and ID"),
      'title-author'       => t("Entity's title and author"),
      'title-created'      => t("Entity's title and creation date"),
      'title-changed'      => t("Entity's title and update date"),
      'custom'             => t('Custom'),
    ];
  }

  /**
   * Returns a minimal array of formatting styles.
   *
   * The returned array of styles may be used for entity types that
   * do not support tokens. The list is reduced to only those styles
   * that can be supported with generic code.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   human-readable translated names as values.
   */
  protected static function getMinimalEntityReferenceStyles() {
    // Some of these have special case code below so that they work
    // well for msot entity types.
    return [
      'id'                 => t("Entity's ID"),
      'title'              => t("Entity's title"),
      'title-id'           => t("Entity's title and ID"),
      'custom'             => t('Custom'),
    ];
  }

  /**
   * Returns an array of mappings from formatting styles to token strings.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   token strings as values.
   */
  protected static function getEntityReferenceTokenPatterns() {
    $by      = (string) t('by');
    $created = (string) t('Created');
    $updated = (string) t('Updated');

    // While generic patterns are listed here for the standard styles,
    // some of these are handled by custom code below so that they work
    // well for most entity types, even if the type doesn't support the
    // specific tokens used here.
    return [
      'id'                 => '[__PREFIX:id]',
      'title'              => '[__PREFIX:title]',
      'title-id'           => '[__PREFIX:title] ([__PREFIX:id])',
      'title-author'       => '[__PREFIX:title] ' . $by . ' [__PREFIX:author:display-name]',
      'title-created'      => '[__PREFIX:title] (' . $created . ' [__PREFIX:created])',
      'title-changed'      => '[__PREFIX:title] (' . $updated . ' [__PREFIX:changed])',
    ];
  }

  /**
   * Returns an array of mappings from general patterns to specific patterns.
   *
   * Most entity types define tokens that map names to field values. For
   * instance, "[node:nid]" maps to a node entity's unique numeric ID, and
   * "[comment:title]" maps to the comment's title.
   *
   * Unfortunately, there is no standardization across entity types, though
   * there are quite a few common tokens like "created" and "changed".
   * This makes it impossible to generically refer to "[ENTITTYPE:id]"
   * and always get the numeric entity ID no matter what __PREFIX is.
   *
   * To make generic patterns work, like those returned by the
   * getEntityReferenceTokenPatterns() method, we need to "fix" the
   * pattern after replacing __PREFIX and swap generic patterns for
   * patterns for specific entity types. So "[__PREFIX:id]" converts to
   * "[node:id]" for a node entity, and then the "fix" swaps that with
   * "[node:nid]" so that "nid" is used for nodes. The same goes for
   * multiple other common entity types.
   *
   * @return string[]
   *   Returns an associative array where the keys are general patterns to
   *   replace, and the values are entity-specific patterns.
   */
  protected static function getWellKnownTokenReplacements() {
    // Generic fields:
    // - id.
    // - name or title.
    // - author, owner, user, or uid.
    return [
      // Generic pattern        Entity type specific pattern.
      //
      // Core modules:
      '[aggregator_feed:id'              => '[aggregator_feed:fid',
      '[aggregator_feed:name'            => '[aggregator_feed:title',
      '[aggregator_feed:changed'         => '[aggregator_feed:modified',
      '[aggregator_item:id'              => '[aggregator_item:iid',
      '[aggregator_item:name'            => '[aggregator_item:title',
      '[aggregator_item:owner'           => '[aggregator_item:author',
      '[aggregator_item:user'            => '[aggregator_item:author',
      '[aggregator_item:uid'             => '[aggregator_item:author',
      '[aggregator_item:created'         => '[aggregator_item:timestamp',
      '[comment:id'                      => '[commend:cid',
      '[comment:name'                    => '[commend:title',
      '[comment:owner'                   => '[commend:author',
      '[comment:user'                    => '[commend:author',
      '[comment:uid'                     => '[commend:author',
      '[content_moderation_state:author' => '[content_moderation_state:uid',
      '[content_moderation_state:owner'  => '[content_moderation_state:uid',
      '[content_moderation_state:user'   => '[content_moderation_state:uid',
      '[file:id]'                        => '[file:fid',
      '[file:title'                      => '[file:name',
      '[file:author'                     => '[file:owner',
      '[file:user'                       => '[file:owner',
      '[file:uid'                        => '[file:owner',
      '[media:id'                        => '[media:mid',
      '[media:title'                     => '[media:name',
      '[media:author'                    => '[media:uid',
      '[media:owner'                     => '[media:uid',
      '[media:user'                      => '[media:uid',
      '[menu_link_content:name'          => '[menu_link_content:title',
      '[node:id'                         => '[node:nid',
      '[node:name'                       => '[node:title',
      '[node:owner'                      => '[node:author',
      '[node:user'                       => '[node:author',
      '[node:uid'                        => '[node:author',
      '[shortcut:name'                   => '[shortcut:title',
      '[term:id'                         => '[term:tid',
      '[term:title'                      => '[term:name',
      '[user:id'                         => '[user:uid',
      '[user:name'                       => '[user:display-name',
      '[user:title'                      => '[user:display-name',
      '[user:changed'                    => '[user:last-login',
      '[view:name'                       => '[view:title',
      '[vocabulary:id'                   => '[vocabulary:vid',
      '[vocabulary:title'                => '[vocabulary:name',
      '[workspace:title'                 => '[workspace:name',
      '[workspace:author'                => '[workspace:uid',
      '[workspace:owner'                 => '[workspace:uid',
      '[workspace:user'                  => '[workspace:uid',
      // A few contrib modules:
      '[foldershare:title'               => '[foldershare:name',
      '[foldershare:author'              => '[foldershare:owner',
      '[foldershare:user'                => '[foldershare:owner',
      '[foldershare:uid'                 => '[foldershare:owner',
      '[menu-link:id'                    => '[menu-link:mlid',
      '[menu-link:name'                  => '[menu-link:title',
    ];
  }

  /**
   * Returns an array of special mappings from entity types to token prefixes.
   *
   * By strong convention, the token prefix (e.g. "node") is the entity type.
   * However, this is not required. Unfortunately, Drupal core's token API
   * does not provide a way to look up the prefix for an entity type.
   *
   * This is a problem since we need to map the entity type to the prefix
   * so that the token list added to the formatter UI is appropriate to
   * the entity type being formatted.
   *
   * The table returned here is a mapping from known core entity types to
   * their prefixes *IF* the prefix does not match the entity type.
   *
   * @return string[]
   *   Returns an associative array where keys are entity types and values
   *   are the corresponding token prefix. Only exceptions are included
   *   where the prefix does not match the entity type.
   */
  protected static function getWellKnownTokenPrefixExceptions() {
    // The following content entity types are defined in Drupal core:
    //
    // Content entity type       Token prefix              Defined.
    // -------------------       ------------              -------.
    // aggregator_feed           aggregator_feed           automatic.
    // aggregator_item           aggregator_item           automatic.
    // block_content             block_content             automatic.
    // comment                   comment                   custom.
    // contact_message           contact_message           automatic.
    // content_moderation_state  content_moderation_state  automatic.
    // file                      file                      custom.
    // media                     media                     automatic.
    // menu_link_content         menu_link_content         automatic.
    // node                      node                      custom.
    // path_alias                path_alias                automatic.
    // shortcut                  shortcut                  automatic.
    // taxonomy_term             term                      custom.
    // user                      user                      custom.
    // workspace                 workspace                 automatic.
    //
    // The following configuration entity types are defined in Drupal core:
    //
    // Config entity type        Token prefix              Defined.
    // ------------------        ------------              -------.
    // block                     --NO TOKENS--.
    // block_cntent_type         --NO TOKENS--.
    // comment_type              --NO TOKENS--.
    // configurable_language     --NO TOKENS--.
    // contact_form              --NO TOKENS--.
    // editor                    --NO TOKENS--.
    // field_config              --NO TOKENS--.
    // field_storage_config      --NO TOKENS--.
    // filter_format             --NO TOKENS--.
    // image_style               --NO TOKENS--.
    // language_content_settings --NO TOKENS--.
    // media_type                --NO TOKENS--.
    // node_type                 --NO TOKENS--.
    // rdf_mapping               --NO TOKENS--.
    // responsive_image_style    --NO TOKENS--.
    // rest_resource_config      --NO TOKENS--.
    // search_page               --NO TOKENS--.
    // shortcut_set              --NO TOKENS--.
    // taxonomy_vocabulary       vocabulary                custom.
    // tour                      --NO TOKENS--.
    // user_role                 --NO TOKENS--.
    // view                      view                      custom.
    // workflow                  --NO TOKENS--.
    //
    // The following content entity types are defined in common contrib modules:
    //
    // Content entity type       Token prefix              Defined.
    // -------------------       ------------              -------.
    // foldershare               foldershare               custom.
    //
    // The following configuration entity types are defined in common contrib
    // modules:
    //
    // Config entity type        Token prefix              Defined.
    // ------------------        ------------              -------.
    // login_destination         --NO TOKENS--.
    // pathauto_pattern          array                     custom.
    return [
      // Drupal core entity types where the entity type name DOES NOT
      // match the entity's token prefix.
      'taxonomy_term'       => 'term',
      'taxonomy_vocabulary' => 'vocabulary',
    ];
  }

  /**
   * Returns an array of choices for how to open a link.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   human-readable translated names as values.
   */
  protected static function getOpenLinkInValues() {
    return [
      '_self'    => t('Open linked entity in the same tab/window'),
      '_blank'   => t('Open linked entity in a new tab/window'),
      'download' => t('Download the linked entity'),
    ];
  }

  /**
   * Returns an array of link topic annotation.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   human-readable translated names as values.
   */
  protected static function getLinkTopicValues() {
    return [
      'any'       => t('- Unspecified -'),
      'alternate' => t('Alternate form of this entity'),
      'author'    => t('Author information'),
      'bookmark'  => t('Bookmarkable permalink'),
      'canonical' => t('Canonical (preferred) form of this entity'),
      'help'      => t('Help information'),
      'license'   => t('License information'),
    ];
  }

  /**
   * Returns an array of list styles.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   human-readable translated names as values.
   */
  protected static function getListStyles() {
    return [
      'span' => t('Single line list'),
      'ol'   => t('Numbered list'),
      'ul'   => t('Bulleted list'),
      'div'  => t('Non-bulleted block list'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array_merge(
      [
        'entityReferenceStyle' => 'title',
        'titleCustomText'      => '',
        'classes'              => '',
        'showLink'             => TRUE,
        'openLinkIn'           => '_self',
        'linkTopic'            => 'any',
        'listStyle'            => 'span',
        'listSeparator'        => ', ',
      ],
      parent::defaultSettings());
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    // Sanitize current settings.
    $this->sanitizeSettings();
    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    // Summarize.
    $summary = [];
    $styles = self::getEntityReferenceStyles();
    $style = $this->getSetting('entityReferenceStyle');
    if (isset($styles[$style]) === TRUE) {
      $summary[] = $styles[$style];
    }
    else {
      $summary[] = $this->t("Unknown style");
    }

    if ($this->getSetting('showLink') === FALSE) {
      $summary[] = $this->t('No link');
    }
    else {
      switch ($this->getSetting('openLinkIn')) {
        case '_self':
          $summary[] = $this->t('Open in current tab/window');
          break;

        case '_blank':
          $summary[] = $this->t('Open in new tab/window');
          break;

        case 'download':
          $summary[] = $this->t('Download');
          break;
      }
    }

    // If the field can store multiple values, then summarize list style.
    if ($isMultiple === TRUE) {
      $listStyles    = self::getListStyles();
      $listStyle     = $this->getSetting('listStyle');
      $listSeparator = $this->getSetting('listSeparator');

      $text = $listStyles[$listStyle];
      if ($listStyle === 'span' && empty($listSeparator) === FALSE) {
        $text .= $this->t(', with separator');
      }
      $summary[] = $text;
    }

    return $summary;
  }

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
    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    if ($isMultiple === TRUE) {
      return $this->t("Format field values as links that show the entity's title, ID, author, creation date, and other options. Multiple field values are shown as a list on one line, bulleted, numbered, or in blocks.");
    }

    return $this->t("Format a field as a link that shows the entity name or ID.");
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $this->sanitizeSettings();
    $storageDef = $this->fieldDefinition->getFieldStorageDefinition();
    $isMultiple = $storageDef->isMultiple();

    // Get the token UI.
    //
    // The Drupal core token API can return a UI for selecting tokens and
    // adding them an input field. This is returned by the token tree
    // builder, optionally filtered by the token prefix.
    //
    // Showing all tokens in the system doesn't make sense here. All we
    // want are the tokens relevant for the field's entity type. Normally,
    // the token prefix is the entity type, but this is not enforced and
    // the token API has no way to look up the prefix. So, we assume the
    // prefix is the entity type, except for known Drupal core exceptions.
    $entityType = $storageDef->getSetting('target_type');
    $fixPrefixes = self::getWellKnownTokenPrefixExceptions();
    if (isset($fixPrefixes[$entityType]) === TRUE) {
      $prefix = $fixPrefixes[$entityType];
    }
    else {
      $prefix = $entityType;
    }

    $tokenUi = '';
    $tokensAvailable = FALSE;
    if ($this->tokenService !== NULL) {
      // Get token info and confirm that the prefix determined above has tokens.
      // If it doesn't, then we'll need to fall back to showing all tokens
      // since we cannot determine the right prefix to use.
      $tokenInfo = $this->tokenService->getInfo();
      if (isset($tokenInfo['tokens'][$prefix]) === FALSE) {
        // Prefix is unknown. Don't use a prefix.
        $prefix = '';
      }

      // Get the token UI.
      if ($this->tokenTreeBuilder !== NULL) {
        try {
          $tokensAvailable = ($prefix !== '');;
          $tokenUi = $this->tokenTreeBuilder->buildRenderable(
            ($prefix === '') ? [] : [$prefix],
            [
              // Focus on the entity type's tokens only, if
              // we found a usable prefix. Otherwise show everything in
              // the hope that the user recognizes the tokens wanted.
              'global_types'    => !$tokensAvailable,
              // Use the click-to-insert UI.
              'click_insert'    => TRUE,
              // Don't show restricted info.
              'show_restricted' => FALSE,
              // Do show nested tokens, such as for customized dates.
              'show_nested'     => TRUE,
            ]);
        }
        catch (\Exception $e) {
          // Cannot create the token tree builder. Show nothing.
        }
      }
    }

    // Get entity reference styles.
    //
    // If the above checks found tokens, then use a larger list of
    // available styles, some of which depend upon tokens. But if
    // the above checks did not find tokens, then reduce the style
    // list to those we can safely implement in code.
    if ($tokensAvailable === TRUE) {
      $styles = self::getEntityReferenceStyles();
    }
    else {
      $styles = self::getMinimalEntityReferenceStyles();
    }

    // Below, some checkboxes and select choices show/hide other form
    // elements. We use Drupal's obscure 'states' feature, which adds
    // Javascript to elements to auto show/hide based upon a set of
    // simple conditions.
    //
    // Those conditions need to reference the form elements to check
    // (e.g. a checkbox), but the element ID and name are automatically
    // generated by the parent form. We cannot set them, or predict them,
    // so we cannot use them. We could use a class, but this form may be
    // shown multiple times on the same page, so a simple class would not be
    // unique. Instead, we create classes for this form only by adding a
    // random number marker to the end of the class name.
    $marker = rand();

    // Add branding.
    $elements = parent::settingsForm($form, $formState);
    $elements = Branding::addFieldFormatterBranding($elements);
    $elements['#attached']['library'][] =
      'formatter_suite/formatter_suite.fieldformatter';

    // Add description.
    //
    // Use a large negative weight to insure it comes first.
    $elements['description'] = [
      '#type'          => 'html_tag',
      '#tag'           => 'div',
      '#value'         => $this->getDescription(),
      '#weight'        => -1000,
      '#attributes'    => [
        'class'        => [
          'formatter_suite-settings-description',
        ],
      ],
    ];

    $weight = 0;

    // Prompt for each setting.
    $elements['entityReferenceStyle'] = [
      '#title'         => $this->t('Link title'),
      '#type'          => 'select',
      '#options'       => $styles,
      '#default_value' => $this->getSetting('entityReferenceStyle'),
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-style',
        ],
      ],
      '#attributes'    => [
        'class'        => [
          'entityReferenceStyle-' . $marker,
        ],
      ],
    ];

    $elements['titleCustomText'] = [
      '#title'         => $this->t('Custom text'),
      '#type'          => 'textfield',
      '#size'          => 10,
      '#default_value' => $this->getSetting('titleCustomText'),
      '#description'   => $tokenUi,
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-title-custom-text',
        ],
      ],
      '#states'        => [
        'visible'      => [
          '.entityReferenceStyle-' . $marker => [
            'value'    => 'custom',
          ],
        ],
      ],
    ];

    $elements['sectionBreak1'] = [
      '#markup' => '<div class="formatter_suite-section-break"></div>',
      '#weight' => $weight++,
    ];

    $elements['classes'] = [
      '#title'         => $this->t('Custom classes'),
      '#type'          => 'textfield',
      '#size'          => 10,
      '#default_value' => $this->getSetting('classes'),
      '#weight'        => $weight++,
      '#attributes'    => [
        'autocomplete' => 'off',
        'autocapitalize' => 'none',
        'spellcheck'   => 'false',
        'autocorrect'  => 'off',
      ],
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-classes',
        ],
      ],
    ];

    $elements['sectionBreak2'] = [
      '#markup' => '<div class="formatter_suite-section-break"></div>',
      '#weight' => $weight++,
    ];

    $elements['showLink'] = [
      '#title'         => $this->t('Link to the entity'),
      '#type'          => 'checkbox',
      '#default_value' => $this->getSetting('showLink'),
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-show-link',
        ],
      ],
      '#attributes'    => [
        'class'        => [
          'showLink-' . $marker,
        ],
      ],
    ];

    $elements['openLinkIn'] = [
      '#title'         => $this->t('Use link to'),
      '#type'          => 'select',
      '#options'       => self::getOpenLinkInValues(),
      '#default_value' => $this->getSetting('openLinkIn'),
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-open-link-in',
        ],
      ],
      '#states'        => [
        'visible'      => [
          '.showLink-' . $marker => [
            'checked'  => TRUE,
          ],
        ],
      ],
    ];

    $elements['linkTopic'] = [
      '#title'         => $this->t('Annotate link as'),
      '#type'          => 'select',
      '#options'       => self::getLinkTopicValues(),
      '#default_value' => $this->getSetting('linkTopic'),
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-entity-reference-link-topic',
        ],
      ],
      '#states'        => [
        'visible'      => [
          '.showLink-' . $marker => [
            'checked'  => TRUE,
          ],
        ],
      ],
    ];

    if ($isMultiple === TRUE) {
      $elements['sectionBreak3'] = [
        '#markup' => '<div class="formatter_suite-section-break"></div>',
        '#weight' => $weight++,
      ];

      $elements['listStyle'] = [
        '#title'         => $this->t('List style'),
        '#type'          => 'select',
        '#options'       => self::getListStyles(),
        '#default_value' => $this->getSetting('listStyle'),
        '#weight'        => $weight++,
        '#wrapper_attributes' => [
          'class'        => [
            'formatter_suite-general-entity-reference-list-style',
          ],
        ],
        '#attributes'    => [
          'class'        => [
            'listStyle-' . $marker,
          ],
        ],
      ];

      $elements['listSeparator'] = [
        '#title'         => $this->t('Separator'),
        '#type'          => 'textfield',
        '#size'          => 10,
        '#default_value' => $this->getSetting('listSeparator'),
        '#weight'        => $weight++,
        '#attributes'    => [
          'autocomplete' => 'off',
          'autocapitalize' => 'none',
          'spellcheck'   => 'false',
          'autocorrect'  => 'off',
        ],
        '#wrapper_attributes' => [
          'class'        => [
            'formatter_suite-general-entity-reference-list-separator',
          ],
        ],
        '#states'        => [
          'visible'      => [
            '.listStyle-' . $marker => [
              'value'    => 'span',
            ],
          ],
        ],
      ];
    }

    return $elements;
  }

  /**
   * Sanitize settings to insure that they are safe and valid.
   *
   * @internal
   * Drupal's class hierarchy for plugins and their settings does not
   * include a 'validate' function, like that for other classes with forms.
   * Validation must therefore occur on use, rather than on form submission.
   * @endinternal
   */
  protected function sanitizeSettings() {
    // Get current settings.
    $entityReferenceStyle = $this->getSetting('entityReferenceStyle');
    $showLink             = $this->getSetting('showLink');
    $openLinkIn           = $this->getSetting('openLinkIn');
    $linkTopic            = $this->getSetting('linkTopic');

    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    // Get setting defaults.
    $defaults = self::defaultSettings();

    // Legacy settings.
    //
    // An earlier version of this formatter supported an entity reference
    // style of "titlelink". We now treat this as "title", and $showLink TRUE.
    if ($entityReferenceStyle === 'titlelink') {
      $entityReferenceStyle = 'title';
      $showLink = TRUE;
    }

    // Sanitize & validate.
    //
    // While <select> inputs constrain choices to those we define in the
    // form, it is possible to hack a form response send other values back.
    // So check all <select> choices and use the default when a value is
    // empty or unknown.
    $entityReferenceStyles = self::getEntityReferenceStyles();
    if (empty($entityReferenceStyle) === TRUE ||
        isset($entityReferenceStyles[$entityReferenceStyle]) === FALSE) {
      $entityReferenceStyle = $defaults['entityReferenceStyle'];
      $this->setSetting('entityReferenceStyle', $entityReferenceStyle);
    }

    $openLinkInValues = self::getOpenLinkInValues();
    if (empty($openLinkIn) === TRUE ||
        isset($openLinkInValues[$openLinkIn]) === FALSE) {
      $openLinkIn = $defaults['openLinkIn'];
      $this->setSetting('openLinkIn', $openLinkIn);
    }

    $linkTopicValues = self::getLinkTopicValues();
    if (empty($linkTopic) === TRUE ||
        isset($linkTopicValues[$linkTopic]) === FALSE) {
      $linkTopic = $defaults['linkTopic'];
      $this->setSetting('linkTopic', $linkTopic);
    }

    // Insure boolean values are boolean.
    $showLink = boolval($showLink);
    $this->setSetting('showLink', $showLink);

    $listStyle = $this->getSetting('listStyle');
    $listStyles = self::getListStyles();

    if ($isMultiple === TRUE) {
      if (empty($listStyle) === TRUE ||
          isset($listStyles[$listStyle]) === FALSE) {
        $listStyle = $defaults['listStyle'];
        $this->setSetting('listStyle', $listStyle);
      }
    }

    // Classes and custom title text are not sanitized or validated.
    // They will be added to the link, with appropriate Xss filtering.
  }

  /*---------------------------------------------------------------------
   *
   * View.
   *
   *---------------------------------------------------------------------*/
  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    switch ($this->getSetting('entityReferenceStyle')) {
      case 'id':
        return AccessResult::allowed();

      default:
        // Make sure we have access to the title.
        return $entity->access('view label', NULL, TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if ($items->isEmpty() === TRUE) {
      return [];
    }

    $this->sanitizeSettings();

    //
    // Get entities.
    // -------------
    // From the item list, get the entities to process. If there aren't
    // any, return nothing.
    $entities = $this->getEntitiesToView($items, $langcode);
    if (empty($entities) === TRUE) {
      return [];
    }

    // Prepare custom classes.
    // -----------------------
    // If the admin entered custom classes, add them.
    $classes = $this->getSetting('classes');
    if (empty($classes) === TRUE) {
      $classes = [];
    }
    else {
      // Security: Class names are entered by an administrator.
      // They may not include anything but CSS-compatible words, and
      // certainly no HTML.
      //
      // Here, the class text is stripped of HTML tags as a start.
      // A regex tosses unacceptable characters in a CSS class name.
      $classes = strip_tags($classes);
      $classes = mb_ereg_replace('[^_a-zA-Z0-9- ]', '', $classes);
      if ($classes === FALSE) {
        $classes = [];
      }

      $classes = explode(' ', $classes);
    }

    //
    // Prepare token prefix.
    // ---------------------
    // Tokens have a prefix (e.g. "node") that distinguishes the tokens of
    // one entity type or generic purpose from another. Normally, the prefix
    // matches the entity type, but this is not enforced. Since the token
    // API has no way to map entity types to token prefixes, assume the
    // prefix is the entity type except for known Drupal core special cases.
    $entityType = reset($entities)->getEntityType()->id();
    $fixPrefixes = self::getWellKnownTokenPrefixExceptions();
    if (isset($fixPrefixes[$entityType]) === TRUE) {
      $prefix = $fixPrefixes[$entityType];
    }
    else {
      $prefix = $entityType;
    }

    // Verify that the prefix exists. If it doesn't, then token replacement
    // cannot work for the standard reference styles.
    if ($this->tokenService === NULL) {
      $prefix = '';
    }
    else {
      $tokenInfo = $this->tokenService->getInfo();
      if (isset($tokenInfo['tokens'][$prefix]) === FALSE) {
        // Prefix is unknown. Don't use a prefix.
        $prefix = '';
      }
    }

    //
    // Prepare token pattern.
    // ----------------------
    // Get the pattern and pre-process it. If the admin entered a custom
    // pattern, clean it of dangerous HTML. Otherwise get a well-known
    // pattern.
    $entityReferenceStyle = $this->getSetting('entityReferenceStyle');
    if ($entityReferenceStyle === 'custom') {
      // Custom token pattern.
      $pattern = $this->getSetting('titleCustomText');

      if (empty($pattern) === TRUE) {
        // If the custom pattern is empty, revert to the title.
        $entityReferenceStyle = 'title';
      }
      else {
        // Security: A custom pattern is entered by an administrator.
        // It may legitimately include HTML entities and minor HTML, but
        // it should not include dangerous HTML.
        $pattern = Xss::filterAdmin($this->getSetting('titleCustomText'));
      }
    }
    elseif ($prefix === '') {
      // A standard style is requested, but a token prefix for the entity
      // type could not be found. This prevents us from using token replacement
      // for the standard styles. Below, several of these are implemented
      // with explicit code. But anything else needs to be reduced.
      switch ($entityReferenceStyle) {
        case 'id':
        case 'title':
        case 'title-id':
          // These have custom code below.
          break;

        default:
          // All other style choices do not. Reduce them to 'title'.
          $entityReferenceStyle = 'title';
          break;
      }

      // Because these styles are hard-coded below, we don't need to
      // get a token pattern.
      $pattern = '';
    }
    else {
      // Get the token pattern for a standard style.
      $entityReferencePatterns = self::getEntityReferenceTokenPatterns();
      $pattern = $entityReferencePatterns[$entityReferenceStyle];

      // Replace the placeholder "__PREFIX" with the entity type's prefix
      // determined above.
      $pattern = str_replace('__PREFIX', $prefix, $pattern);

      // Also map from generic tokens to the specific tokens for
      // well-known entity types.
      $fixPatterns = self::getWellKnownTokenReplacements();
      $pattern = str_replace(
        array_keys($fixPatterns),
        array_values($fixPatterns),
        $pattern);
    }

    //
    // Replace tokens.
    // ---------------
    // Loop through the entities and do token replacement for each one.
    $elements = [];
    foreach ($entities as $delta => $entity) {
      if ($entity === NULL) {
        continue;
      }

      $url = $entity->toUrl();
      if (empty($url) === TRUE) {
        $url = NULL;
      }
      else {
        $urlOptions = $url->getOptions();
      }

      // We'd like to use generic token patterns for all styles
      // in the formatter's menu, but this doesn't work across all entity
      // types because there are no standards for tokens. For example,
      // the entity ID for a node is "nid", for a file is "fid", for
      // a comment is "cid", and for a user is "uid". Other entity types
      // will generically support an "id" token. We get similar issues
      // for the name and author of an entity.
      //
      // While we do support generic pattern fixes below, we do so only
      // for custom patterns where the user entering the tokens may not
      // know to use the right tokens for the right entity type.
      switch ($entityReferenceStyle) {
        case 'id':
          $title = (string) $entity->id();
          break;

        case 'title':
          $title = $entity->label();
          break;

        case 'title-id':
          $title = $entity->label() . ' (' . (string) $entity->id() . ')';
          break;

        default:
          // Replace tokens in the pattern.
          $title = $this->tokenService->replace(
            $pattern,
            [
              $entityType => $entity,
            ],
            [
              'langcode' => $langcode,
              'clear'    => TRUE,
            ]);
          break;
      }

      // Because replaced text may include HTML, we cannot pass it directly as
      // the '#title' on a link, which will escape the HTML. Instead we
      // use FormattableMarkup.
      $title = new FormattableMarkup($title, []);

      // If the link is disabled, show the title text within a <span>.
      // Otherwise, build a URL and create a link.
      if ($this->getSetting('showLink') === FALSE ||
          $url === NULL) {
        $elements[$delta] = [
          '#type'       => 'html_tag',
          '#tag'        => 'span',
          '#value'      => $title,
          '#attributes' => [
            'class'     => $classes,
          ],
          '#cache'      => [
            'tags'      => $entity->getCacheTags(),
          ],
        ];
      }
      else {
        $rel = '';
        $target = '';
        $download = FALSE;

        switch ($this->getSetting('openLinkIn')) {
          case '_self':
            $target = '_self';
            break;

          case '_blank':
            $target = '_blank';
            break;

          case 'download':
            $download = TRUE;
            break;
        }

        $topic = $this->getSetting('linkTopic');
        if ($topic !== 'any') {
          $rel .= $topic;
        }

        if (empty($rel) === FALSE) {
          $urlOptions['attributes']['rel'] = $rel;
        }

        if (empty($target) === FALSE) {
          $urlOptions['attributes']['target'] = $target;
        }

        if ($download === TRUE) {
          $urlOptions['attributes']['download'] = '';
        }

        $url->setOptions($urlOptions);

        $elements[$delta] = [
          '#type'       => 'link',
          '#title'      => $title,
          '#options'    => $url->getOptions(),
          '#url'        => $url,
          '#attributes' => [
            'class'     => $classes,
          ],
          '#cache'      => [
            'tags'      => $entity->getCacheTags(),
          ],
        ];

        if (empty($items[$delta]->_attributes) === FALSE) {
          // There are item attributes. Add them to the link's options.
          $elements[$delta]['#options'] += [
            'attributes' => $items[$delta]->_attributes,
          ];

          // And remove them from the item since they are now included
          // on the link.
          unset($items[$delta]->_attributes);
        }
      }
    }

    if (empty($elements) === TRUE) {
      return [];
    }

    //
    // Add multi-value field processing.
    // ---------------------------------
    // If the field has multiple values, redirect to a theme and pass
    // the list style and separator.
    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    if ($isMultiple === TRUE) {
      // Replace the 'field' theme with ours, which supports lists.
      $elements['#theme'] = 'formatter_suite_field_list';

      // Set the list style.
      $elements['#list_style'] = $this->getSetting('listStyle');

      // Set the list separator.
      //
      // Security: The list separator is entered by an administrator.
      // It may legitimately include HTML entities and minor HTML, but
      // it should not include dangerous HTML.
      //
      // Because it may include HTML, we cannot pass it as-is and let a TWIG
      // template use {{ }}, which will process the text and corrupt any
      // entered HTML or HTML entities.
      //
      // We therefore use an Xss admin filter to remove any egreggious HTML
      // (such as scripts and styles), and then FormattableMarkup() to mark the
      // resulting text as safe.
      $listSeparator = Xss::filterAdmin($this->getSetting('listSeparator'));
      $elements['#list_separator'] = new FormattableMarkup($listSeparator, []);
    }

    return $elements;
  }

}
