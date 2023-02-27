<?php

namespace Drupal\reena_sample_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with a text
 * @Block (
 *   id = "reena_sample_block",
 *   admin_label = @Translation("Reena Sample Block")
 * )
 */

class ReenaSampleBlock extends BlockBase{
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
