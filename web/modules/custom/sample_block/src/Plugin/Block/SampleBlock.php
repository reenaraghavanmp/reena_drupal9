<?php

namespace Drupal\sample_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with a text
 * @Block (
 *   id = "sample_block",
 *   admin_label = @Translation("Sample Block")
 * )
 */

class SampleBlock extends BlockBase{
/**
 * {@inheritdoc}
 */
  public function build() {
    return [
      "#type" => "markup",
      "#markup" => "This a Sample Custom Block Created by Reena Raghavan",
    ];
  }

}
