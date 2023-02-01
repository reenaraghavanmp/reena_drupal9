<?php

namespace Drupal\reena_block_plugin\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with a text
 * @Block (
 *   id = "reena_block_plugin",
 *   admin_label = @Translation("Reena Block Plugin")
 * )
 */

class ReenaBlockPlugin extends BlockBase{
  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      "#type" => "markup",
      "#markup" => "This Block is Created using Block plugin",
    ];
  }

}
