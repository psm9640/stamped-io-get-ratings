# Stamped.io Rating Sync Plugin for WooCommerce

This WordPress plugin automatically syncs WooCommerce product ratings with the Stamped.io API every minute using WP Cron. It also ensures that the necessary meta fields are created when a WooCommerce product is saved for the first time, regardless of its status (publish, draft, pending, etc.).

This allows developers to add their own star rating widget to their product and category pages (and any other place a product card appears), avoiding the dreaded "javascript flash" on page load.

## Features

- **Automated Stamped.io Rating Sync**: The plugin uses WP Cron to send a request to the Stamped.io API every minute, updating WooCommerce product ratings and metadata if needed.
- **Initial Meta Fields Creation**: When a new WooCommerce product is saved, the plugin automatically adds two hidden meta fields (`_pm_stamped_update` and `_pm_stamped_rating`) for syncing with Stamped.io.
- **Scheduled Cron Task**: The plugin schedules a cron task that runs every minute, ensuring frequent updates from Stamped.io.
- **Handles All Product States**: The plugin ensures that the meta fields are added whether the product is published, drafted, or pending.

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- cURL enabled on your server
- Stamped.io API key

## Installation

1. Download the plugin and upload it to the `/wp-content/plugins/` directory or add it as a must-use plugin by placing it in `/wp-content/mu-plugins/`.
   
2. Activate the plugin through the **Plugins** menu in WordPress (if installed as a regular plugin).

3. Edit the plugin file and replace `"pubkey-YOURAPIKEYHERE"` with your actual Stamped.io public API key. Also, make sure the `storeUrl` is set to your actual store's domain.

## Usage

### Meta Fields Creation on Product Save

Whenever a new WooCommerce product is saved for the first time, two hidden meta fields are created:

- **`_pm_stamped_update`**: The timestamp of the last Stamped.io sync for the product.
- **`_pm_stamped_rating`**: The serialized Stamped.io rating data for the product.

This happens automatically on the initial save, whether the product is published, drafted, or set to any other status.

### WP-Cron Sync

Every minute, the plugin triggers a cron job that:

- Retrieves the product that needs to be updated based on the `_pm_stamped_update` meta field.
- Sends a POST request to the Stamped.io API to retrieve the latest rating information for that product.
- Updates the WooCommerce product meta with the latest Stamped.io rating if it has changed.
- Logs the time of the update in the `_pm_stamped_update` meta field.

### How to Schedule the Cron

The plugin automatically schedules the cron task on activation. It will run every minute, checking for products that need updates from the Stamped.io API. WP-Cron will handle this in the background.

If you need to adjust the interval or disable the cron task, you can modify the function:

```php
wp_schedule_event(time(), 'minute', 'stamped_sync_cron_event');
