<?php
if (!defined('ABSPATH')) {
    exit;
}
function dapfforwc_settings_init() {
    $dapfforwc_options = get_option('dapfforwc_options') ?: [
        'show_categories' =>"on",
        'show_attributes' => "on",
        'show_tags' => "on",
        'show_price_range' => "",
        'show_rating' => "",
        'show_search' => "",
        'use_url_filter' => 'query_string',
        'update_filter_options' => 0,
        'show_loader' => "on",
        'pages' => [],
        'loader_html'=>'<div id="loader" style="display:none;"></div>',
        'loader_css'=>'#loader { width: 56px; height: 56px; border-radius: 50%; background: conic-gradient(#0000 10%,#474bff); -webkit-mask: radial-gradient(farthest-side,#0000 calc(100% - 9px),#000 0); animation: spinner-zp9dbg 1s infinite linear; } @keyframes spinner-zp9dbg { to { transform: rotate(1turn); } }',
        'use_custom_template' => 0,
        'custom_template_code' => '',
        'product_selector' => '.products',
        'pagination_selector' => '.woocommerce-pagination ul.page-numbers',
        'use_filters_word_in_permalinks' => 0,
        'filters_word_in_permalinks' => 'filters',
    ];
    update_option('dapfforwc_options', $dapfforwc_options);

    register_setting(
        'dapfforwc_options_group', 
        'dapfforwc_options','sanitize_dapfforwc_options'
    );
    
    add_settings_section('dapfforwc_section', __('Filter Settings', 'dynamic-ajax-product-filters-for-woocommerce'), null, 'dapfforwc-admin');

    $fields = [
        'show_categories' => __('Show Categories', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_attributes' => __('Show Attributes', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_tags' => __('Show Tags', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_price_range' => __('Show Price Range', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_rating' => __('Show Rating', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_search' => __('Show Search', 'dynamic-ajax-product-filters-for-woocommerce'),
        'use_url_filter' => __('Use URL-Based Filter', 'dynamic-ajax-product-filters-for-woocommerce'),
        'use_filters_word_in_permalinks' => __('use filters word in permalinks', 'dynamic-ajax-product-filters-for-woocommerce'),
        'update_filter_options' => __('Update filter options', 'dynamic-ajax-product-filters-for-woocommerce'),
        'show_loader' => __('Show Loader', 'dynamic-ajax-product-filters-for-woocommerce'),
        'use_custom_template' => __('Use Custom Product Template', 'dynamic-ajax-product-filters-for-woocommerce'),
    ];

    foreach ($fields as $key => $label) {
        add_settings_field($key, $label, "dapfforwc_{$key}_render", 'dapfforwc-admin', 'dapfforwc_section');
    }

    // custom code template
    add_settings_field('custom_template_code', __('product custom template code', 'dynamic-ajax-product-filters-for-woocommerce'), 'dapfforwc_custom_template_code_render', 'dapfforwc-admin', 'dapfforwc_section');

    $default_style = get_option('dapfforwc_style_options') ?: [
        'price' => ['type'=>'price', 'sub_option'=>'price','auto_price' => "on"],
        'rating' => ['type'=>'rating', 'sub_option'=>'rating'],
    ];
    update_option('dapfforwc_style_options', $default_style);
    // form style register
    register_setting(
        'dapfforwc_style_options_group', 
        'dapfforwc_style_options','sanitize_dapfforwc_options'
    );

        // Add Form Style section
    add_settings_section(
        'dapfforwc_style_section',
        __('Form Style Options', 'dynamic-ajax-product-filters-for-woocommerce'),
        function () {
            echo '<p>' . esc_html__('Select the filter box style for each attribute below. Additional options will appear based on your selection.', 'dynamic-ajax-product-filters-for-woocommerce') . '</p>';
        },
        'dapfforwc-style'
    );

//   advance settings register
$Advance_options = get_option('dapfforwc_advance_options') ?: [
    'product_selector' => 'ul.products',
    'pagination_selector' => '.woocommerce-pagination ul.page-numbers',
    'product_shortcode' => 'products',
    'use_anchor' => 0,
    'remove_outofStock' => 0,
];
    update_option('dapfforwc_advance_options', $Advance_options);
    register_setting(
        'dapfforwc_advance_settings', 
        'dapfforwc_advance_options','sanitize_dapfforwc_options'
    );
    // Add the "Advance Settings" section
    add_settings_section(
        'dapfforwc_advance_settings_section',
        __('Advance Settings', 'dynamic-ajax-product-filters-for-woocommerce'),
        null,
        'dapfforwc-advance-settings'
    );

    // Add the "Product Selector" field
    add_settings_field(
        'product_selector',
        __('Product Selector', 'dynamic-ajax-product-filters-for-woocommerce'),
        'dapfforwc_product_selector_callback',
        'dapfforwc-advance-settings',
        'dapfforwc_advance_settings_section'
    );
    // Add the "Pagination Selector" field
    add_settings_field(
        'pagination_selector',
        __('Pagination Selector', 'dynamic-ajax-product-filters-for-woocommerce'),
        'dapfforwc_pagination_selector_callback',
        'dapfforwc-advance-settings',
        'dapfforwc_advance_settings_section'
    );
    // Add the "Product shotcode Selector" field
    add_settings_field(
        'product_shortcode',
        __('Product Shortcode Selector', 'dynamic-ajax-product-filters-for-woocommerce'),
        'dapfforwc_product_shortcode_callback',
        'dapfforwc-advance-settings',
        'dapfforwc_advance_settings_section'
    );

    add_settings_field('use_anchor', __('Make filter link indexable for best SEO', 'dynamic-ajax-product-filters-for-woocommerce'), "dapfforwc_use_anchor_render", 'dapfforwc-advance-settings', 'dapfforwc_advance_settings_section');
    add_settings_field('remove_outofStock', __('Remove out of stock product', 'dynamic-ajax-product-filters-for-woocommerce'), "dapfforwc_remove_outofStock_render", 'dapfforwc-advance-settings', 'dapfforwc_advance_settings_section');

}
add_action('admin_init', 'dapfforwc_settings_init');

function sanitize_dapfforwc_options($input) {
    // If input is not an array, make it one
    if (!is_array($input)) {
        return array();
    }
    
    $sanitized = array();
    
    // Loop through each element of the array
    foreach ($input as $key => $value) {
        // Sanitize based on what type of data this is
        if (is_array($value)) {
            // Recursively sanitize nested arrays
            $sanitized[$key] = sanitize_dapfforwc_options($value);
        } else {
            // Determine the right sanitization based on the key or value type
            switch ($key) {
                // Examples for different types of fields
                case 'custom_template_code':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                    
                case 'url_field':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                    
                case 'email_field':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                    
                case 'number_field':
                    $sanitized[$key] = intval($value);
                    break;
                    
                default:
                    // Default sanitization for text fields
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
    }
    
    return $sanitized;
}

