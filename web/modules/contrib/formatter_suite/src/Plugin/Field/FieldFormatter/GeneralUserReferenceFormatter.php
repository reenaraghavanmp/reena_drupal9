<?php

namespace Drupal\formatter_suite\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\formatter_suite\Branding;

/**
 * Formats a user entity reference as one or more links.
 *
 * This formatter supports:
 *   - A selection of common formatting variants, including showing
 *     the referenced user's account name, display name, UID, email
 *     address, or several other combinations.
 *   - An option to enter a custom variant with user entity tokens.
 *   - Adding manually entered classes.
 *   - Showing a link to the user's profile:
 *     - Adding selected standard "rel" and "target" options.
 *
 * The "rel" and "target" options are grouped and presented as menu choices
 * and checkboxes.
 *
 * @ingroup formatter_suite
 *
 * @FieldFormatter(
 *   id          = "formatter_suite_general_user_reference",
 *   label       = @Translation("Formatter Suite - General user reference"),
 *   weight      = 1000,
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class GeneralUserReferenceFormatter extends EntityReferenceFormatterBase {

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

  /**
   * The usersettings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $userSettings;

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
   * @param \Drupal\Core\Config\Config $userSettings
   *   The user module's settings.
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
    $tokenTreeBuilder,
    Config $userSettings) {

    parent::__construct(
      $pluginId,
      $pluginDefinition,
      $fieldDefinition,
      $settings,
      $label,
      $viewMode,
      $thirdPartySettings);

    $this->tokenService     = $tokenService;
    $this->tokenTreeBuilder = $tokenTreeBuilder;
    $this->userSettings     = $userSettings;
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

    $userSettings = NULL;
    try {
      $userSettings = $container->get('config.factory')->get('user.settings');
    }
    catch (\Exception $e) {
      // The configuration is missing?
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
      $tokenTreeBuilder,
      $userSettings);
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
  protected static function getUserReferenceStyles() {
    return [
      'id'              => t("User's ID"),
      'email'           => t("User's email address"),
      'account'         => t("User's account name"),
      'account-id'      => t("User's account name and ID"),
      'account-email'   => t("User's account name and email address"),
      'account-display' => t("User's account and display names"),
      'account-roles'   => t("User's account name and roles"),
      'account-last'    => t("User's account name and last login date"),
      'account-since'   => t("User's account name and account creation date"),
      'display'         => t("User's display name"),
      'display-id'      => t("User's display name and ID"),
      'display-email'   => t("User's display name and email address"),
      'display-account' => t("User's display and account names"),
      'display-roles'   => t("User's display name and roles"),
      'display-last'    => t("User's display name and last login date"),
      'display-since'   => t("User's display name and account creation date"),
      'custom'          => t('Custom'),
    ];
  }

  /**
   * Returns an array of mappings from formatting styles to token strings.
   *
   * @return string[]
   *   Returns an associative array with internal names as keys and
   *   token strings as values.
   */
  protected static function getUserReferenceTokenPatterns() {
    $last = (string) t('Last login');
    $since = (string) t('Since');

    return [
      'id'              => '[user:uid]',
      'email'           => '[user:mail]',
      'account'         => '[user:account-name]',
      'account-id'      => '[user:account-name] ([user:uid])',
      'account-email'   => '[user:account-name] &lt;[user:mail]&gt;',
      'account-display' => '[user:account-name] ([user:display-name])',
      'account-roles'   => '[user:account-name] ([user:roles])',
      'account-last'    => '[user:account-name] (' . $last . ' [user:last-login])',
      'account-since'   => '[user:account-name] (' . $since . ' [user:created])',
      'display'         => '[user:display-name]',
      'display-id'      => '[user:display-name] ([user:uid])',
      'display-email'   => '[user:display-name] &lt;[user:mail]&gt;',
      'display-account' => '[user:display-name] ([user:account-name])',
      'display-roles'   => '[user:display-name] ([user:roles])',
      'display-last'    => '[user:display-name] (' . $last . ' [user:last-login])',
      'display-since'   => '[user:display-name] (' . $since . ' [user:created])',
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
      'canonical' => t('Canonical (preferred) form of this entity'),
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
        'userReferenceStyle'   => 'display',
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
    $styles = self::getUserReferenceStyles();
    $style = $this->getSetting('userReferenceStyle');
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
      return $this->t("Format field values as links that show the user's ID, account or display name, email address, and other options. Multiple field values are shown as a list on one line, bulleted, numbered, or in blocks.");
    }

    return $this->t("Format a field as a link that shows the user's ID, account or display name, email address, and other options.");
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $this->sanitizeSettings();
    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

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
    $elements['userReferenceStyle'] = [
      '#title'         => $this->t('Link title'),
      '#type'          => 'select',
      '#options'       => self::getUserReferenceStyles(),
      '#default_value' => $this->getSetting('userReferenceStyle'),
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-user-reference-style',
        ],
      ],
      '#attributes'    => [
        'class'        => [
          'userReferenceStyle-' . $marker,
        ],
      ],
    ];

    $tokenUi = '';
    if ($this->tokenTreeBuilder !== NULL) {
      try {
        $tokenUi = $this->tokenTreeBuilder->buildRenderable(
          ['user'],
          [
            // Focus on the entity type's tokens only.
            'global_types'    => FALSE,
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

    $elements['titleCustomText'] = [
      '#title'         => $this->t('Custom text'),
      '#type'          => 'textfield',
      '#size'          => 10,
      '#default_value' => $this->getSetting('titleCustomText'),
      '#description'   => $tokenUi,
      '#weight'        => $weight++,
      '#wrapper_attributes' => [
        'class'        => [
          'formatter_suite-general-user-reference-title-custom-text',
        ],
      ],
      '#states'        => [
        'visible'      => [
          '.userReferenceStyle-' . $marker => [
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
          'formatter_suite-general-user-reference-classes',
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
          'formatter_suite-general-user-reference-show-link',
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
          'formatter_suite-general-user-reference-open-link-in',
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
          'formatter_suite-general-user-reference-link-topic',
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
            'formatter_suite-general-user-reference-list-style',
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
            'formatter_suite-general-user-reference-list-separator',
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
    $userReferenceStyle = $this->getSetting('userReferenceStyle');
    $showLink           = $this->getSetting('showLink');
    $openLinkIn         = $this->getSetting('openLinkIn');
    $linkTopic          = $this->getSetting('linkTopic');

    $isMultiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    // Get setting defaults.
    $defaults = self::defaultSettings();

    // Sanitize & validate.
    //
    // While <select> inputs constrain choices to those we define in the
    // form, it is possible to hack a form response send other values back.
    // So check all <select> choices and use the default when a value is
    // empty or unknown.
    $userReferenceStyles = $this->getUserReferenceStyles();
    if (empty($userReferenceStyle) === TRUE ||
        isset($userReferenceStyles[$userReferenceStyle]) === FALSE) {
      $userReferenceStyle = $defaults['userReferenceStyle'];
      $this->setSetting('userReferenceStyle', $userReferenceStyle);
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
   *
   * Returns TRUE if the field's target type is a User entity.
   */
  public static function isApplicable(FieldDefinitionInterface $def) {
    $target = $def->getFieldStorageDefinition()->getSetting('target_type');
    return ($target === 'user');
  }

  /**
   * {@inheritdoc}
   *
   * Returns TRUE for all users if the display style is strictly a user ID.
   * Otherwise returns TRUE only if the user has "view label" access to
   * the target entity.
   */
  protected function checkAccess(EntityInterface $entity) {
    switch ($this->getSetting('userReferenceStyle')) {
      case 'id':
        return AccessResult::allowed();

      default:
        // Make sure we have access to the title.
        return $entity->access('view label', NULL, TRUE);
    }
  }

  /**
   * Replaces tokens for the anonymous user.
   *
   * @param string $pattern
   *   The token pattern to operate upon.
   *
   * @return string
   *   Returns the token pattern with specific tokens replaced for the
   *   anonymous user.
   */
  protected function replaceTokensForAnonymous(string $pattern) {
    $pattern = mb_ereg_replace(
      '\[user:created.*\]',
      (string) $this->t('site creation'),
      $pattern);

    $pattern = mb_ereg_replace(
      '\[user:last-login.*\]',
      (string) $this->t('never'),
      $pattern);

    $pattern = mb_ereg_replace(
      '\[user:user_picture.*\]',
      '',
      $pattern);

    $pattern = mb_ereg_replace(
      '\[user:url.*\]',
      '',
      $pattern);

    return str_replace(
      [
        '[user:uid]',
        '[user:account-name]',
        '[user:display-name]',
        '[user:name]',
        '[user:mail]',
        '[user:edit-url]',
        '[user:cancel-url]',
        '[user:one-time-login-url]',
      ],
      [
        0,
        'anonymous',
        $this->userSettings->get('anonymous'),
        'anonymous',
        $this->t('None'),
        '',
        '',
        '',
      ],
      $pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $this->sanitizeSettings();

    //
    // Get entities.
    // -------------
    // From the item list, get the entities to process. If there aren't
    // any, return nothing.
    $elements = [];
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
    // Prepare token pattern.
    // ----------------------
    // Get the pattern and pre-process it. If the admin entered a custom
    // pattern, clean it of dangerous HTML. Otherwise get a well-known
    // pattern.
    $userReferenceStyle = $this->getSetting('userReferenceStyle');
    if ($userReferenceStyle === 'custom') {
      // Custom token pattern.
      $pattern = $this->getSetting('titleCustomText');

      if (empty($pattern) === TRUE) {
        // If the custom pattern is empty, revert to the ID.
        $pattern = '[user:uid]';
      }
      else {
        // Security: A custom pattern is entered by an administrator.
        // It may legitimately include HTML entities and minor HTML, but
        // it should not include dangerous HTML.
        $pattern = Xss::filterAdmin($this->getSetting('titleCustomText'));
      }
    }
    else {
      // User a pre-defined token pattern.
      $userReferencePatterns = self::getUserReferenceTokenPatterns();
      $pattern = $userReferencePatterns[$userReferenceStyle];
    }

    //
    // Replace tokens.
    // ---------------
    // Loop through the entities and do token replacement for each one.
    foreach ($entities as $delta => &$entity) {
      $url = NULL;
      if ($entity->isAnonymous() === FALSE) {
        $url = $entity->toUrl();
        $urlOptions = $url->getOptions();
      }
      $title = '';

      if ($entity->isAnonymous() === TRUE) {
        // For anonymous users, some tokens cannot be replaced because the
        // user entity has no account name, display name, etc. Fill those in.
        $updatedPattern = $this->replaceTokensForAnonymous($pattern);
      }
      else {
        $updatedPattern = $pattern;
      }

      // Replace tokens in the pattern.
      $title = $this->tokenService->replace(
        $updatedPattern,
        [
          'user'     => $entity,
        ],
        [
          'langcode' => $langcode,
          'clear'    => TRUE,
        ]);

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
