<?php

/**
 * @file
 * Contains slider_program.page.inc.
 *
 * Page callback for Slider program entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Slider program templates.
 *
 * Default template: slider_program.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_slider_program(array &$variables) {
  // Fetch SliderProgram Entity Object.
  $slider_program = $variables['elements']['#slider_program'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
