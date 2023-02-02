<?php
/**
 * @file
 * Contains \Drupal\custom_route\Controller\CustomRouteController.
 */
namespace Drupal\custom_route\Controller;

use Drupal\Core\Controller\ControllerBase;


class CustomRouteController extends ControllerBase
{
  public function content()
  {
    return array(
      '#type' => 'markup',
      '#markup' => t('Hello! I am your node listing page.'),
    );
  }

  public function content_list($arg)
  {
    return array(
      '#type' => 'markup',
      '#markup' => t('Hello! I am your ' . $arg . ' listing page.'),
    );
  }

  public function node_detail($node)
  {
      $entity_type = 'node';
      $entity_id = $node;
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
      if($entity != null) {
        $view_mode = 'full';
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $render_controller = \Drupal::entityTypeManager()->getViewBuilder($entity->getEntityTypeId());
        $render_output = $render_controller->view($entity, $view_mode, $langcode);
        return $render_output;
      }
      else {
        return array(
          '#type' => 'markup',
          '#markup' => t('Does not Exists'),
        );
      }
  }
}
