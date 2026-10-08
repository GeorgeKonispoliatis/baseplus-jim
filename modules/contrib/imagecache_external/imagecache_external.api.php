<?php

/**
 * @file
 * Documentation of Imagecache External hooks.
 */

/**
 * Add custom image refresh logic.
 *
 * Use this hook to add extra validation(s) whether to refresh images.
 *
 * @param $needs_refresh
 *   Whether to refresh or not
 * @param $filepath
 *   The path is being checked.
 */
function hook_imagecache_external_needs_refresh_alter(&$needs_refresh, $filepath) {
  // Example: refresh images at least once a week.
  if (filemtime($filepath) > \Drupal::time()->getRequestTime() - 60 * 60 * 24 * 7) {
    $needs_refresh = TRUE;
  }
}

/**
 * Add ability to alter the destination of the file to download. Use this hook
 * to change the scheme and/or directory
 *
 * @param $alter
 *   An array which contains two keys: scheme and directory
 * @param $context
 *   An array which contains two keys: url and hash
 */
function hook_imagecache_external_destination_alter(&$alter, $context) {
  // Example: make scheme for imagecache_external s3.
  if ($context['url'] === 'foo.com') {
    $alter['scheme'] = 's3';
  }

  if ($context['url'] == 'example.com') {
    $alter['directory'] = 'my-custom-directory';
  }
}

/**
 * Allows modules to alter the list of file paths that should be deleted during
 * an Imagecache External flush operation.
 *
 * This hook is invoked after the module has collected the base file paths for
 * the derivatives scheduled for deletion. Modules may use this hook to add
 * additional derivative paths (e.g., optimized formats such as .webp or .avif),
 * remove entries, or modify existing ones.
 *
 * @param array $files_to_delete
 *   An array of file paths (relative to the image derivative directory).
 *   Implementations may append, prepend, or remove items from this array.
 */
function hook_imagecache_external_flush_filepath_alter(array &$files_to_delete) {
  $original = $files_to_delete;

  foreach ($original as $filepath) {
    $files_to_delete[] = $filepath . '.webp';
    $files_to_delete[] = $filepath . '.avif';
  }
}
