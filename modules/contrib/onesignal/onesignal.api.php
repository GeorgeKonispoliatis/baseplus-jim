<?php

/**
 * @file
 * Hooks specific to the OneSignal module.
 */

/**
 * Add extra js to be fired on OneSignal.push().
 *
 * Invoked on onesignal_page_attachments().
 *
 * @return string
 *   Returns the extra js to be added.
 */
function hook_onesignal_extra_js(): string {
  return '';
}
