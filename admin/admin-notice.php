<?php

if (!defined('ABSPATH')) {
    exit;
}

// check duplicate slugs

function dapfforwc_check_woocommerce_duplicate_slugs() {
    // Only run in the admin area
    if (is_admin()) {
        // Check if the notice should be displayed
        $dismissed_time = get_option('woocommerce_slug_check_dismissed_time')?:false; // Use default `false` if option not set

        // Check if the option is not set or if the dismissed time is within the last 3 days
        if ($dismissed_time !== false && (time() - $dismissed_time) < 3 * DAY_IN_SECONDS) {
            return; // Don't show the notice
        }

        // Get all product category slugs
        $category_terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'fields'     => 'slugs',
            'hide_empty' => false, 
            'orderby'    => 'name',
        ));

        // Get all product tag slugs
        $tag_terms = get_terms(array(
            'taxonomy'   => 'product_tag',
            'fields'     => 'slugs',
            'hide_empty' => false, 
            'orderby'    => 'name',
        ));

        // Get all attribute slugs
        $attribute_terms = [];
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        foreach ($attribute_taxonomies as $attribute) {
            $terms = get_terms(array(
                'taxonomy'   => 'pa_' . $attribute->attribute_name,
                'fields'     => 'slugs',
                'hide_empty' => false,
            ));
            $attribute_terms = array_merge($attribute_terms, $terms);
        }

        // Merge all slugs into one array
        $all_slugs = array_merge($category_terms, $tag_terms, $attribute_terms);

        // Find duplicate slugs
        $dapfforwc_slug_counts = array_count_values($all_slugs);
        $duplicate_slugs = array_filter($dapfforwc_slug_counts, function($count) {
            return $count > 1;
        });

        // Show admin notice if duplicates exist
        if (!empty($duplicate_slugs)) {
            add_action('admin_notices', function() use ($duplicate_slugs) {
                ?>
                <div class="notice notice-error is-dismissible" id="woocommerce-slug-check-notice">
                    <p><?php esc_html_e('The following slugs are duplicated across categories, tags, or attributes:', 'dynamic-ajax-product-filters-for-woocommerce'); ?></p>
                    <ul>
                        <?php foreach (array_keys($duplicate_slugs) as $dapfforwc_slug) : ?>
                            <li><?php echo esc_html($dapfforwc_slug); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><?php esc_html_e('Please ensure each slug is unique to avoid filtering issues.', 'dynamic-ajax-product-filters-for-woocommerce'); ?></p>
                    <button type="button" id="woocommerce-slug-check-remind-later" class="button"><?php esc_html_e('Remind Me Later', 'dynamic-ajax-product-filters-for-woocommerce'); ?></button>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#woocommerce-slug-check-remind-later').on('click', function() {
                            $.post(ajaxurl, {
                                action: 'dapfforwc_dismiss_slug_check_notice',
                            }, function() {
                                $('#woocommerce-slug-check-notice').fadeOut();
                            });
                        });
                    });
                </script>
                <?php
            });
        }
    }
}
add_action('admin_init', 'dapfforwc_check_woocommerce_duplicate_slugs');

// Handle AJAX dismiss action
function dapfforwc_dismiss_slug_check_notice() {
    update_option('woocommerce_slug_check_dismissed_time', time());
    wp_send_json_success();
}
add_action('wp_ajax_dapfforwc_dismiss_slug_check_notice', 'dapfforwc_dismiss_slug_check_notice');


