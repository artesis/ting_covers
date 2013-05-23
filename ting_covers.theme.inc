<?php

function template_preprocess_ting_object_cover(&$variables) {
  $object = $variables['object'] = $variables['elements']['#object'];
  $variables['image_style'] = $variables['elements']['#image_style'];

  $local_id = $object->getLocalId();

  // Set initial values required by the template.
  $variables['classes'] = array(
    'ting-cover',
    'ting-cover-object-id-' . $local_id,
    'ting-cover-style-' . $variables['image_style'],
  );

  // Add isbn-* class, used by VoxB.
  $isbn = $object->getIsbn();
  if (!empty($isbn)) {
    $variables['classes'][] = 'isbn-' . $isbn[0];
  }

  $variables['image'] = '';

  // If we already have the image available locally then just use it.
  $path = ting_covers_object_path($local_id);
  if (file_exists($path)) {
    // Generate an alt tag.
    if (!empty($variables['elements']['#alt'])) {
      // Pass '#alt' value in elements to overwrite default alt.
      $alt = $variables['elements']['#alt'];
    }
    else {
      $alt = implode(', ', $object->getCreators()) . ': ' . $object->getTitle();
    }

    $variables['image'] = theme('image_style', array('style_name' => $variables['image_style'], 'path' => $path, 'alt' => $alt));

    // Avoid further javascript processing.
    $variables['classes'][] = 'ting-cover-processed';
  }
  elseif (cache_get('ting_covers:' . $local_id)) {
    // We know that there is no cover available for this object so avoid
    // further javascript processing.
    $variables['classes'][] = 'ting-cover-processed';
  }
}

function theme_ting_object_cover($variables) {
  return '<div class="' . implode(' ', $variables['classes']) . '">' . $variables['image'] . '</div>';
}
