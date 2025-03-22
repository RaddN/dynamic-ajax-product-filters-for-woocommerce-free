<?php

if (!defined('ABSPATH')) {
    exit;
}


function dapfforwc_product_filter_shortcode($atts)
{
    global $dapfforwc_styleoptions, $post, $dapfforwc_options, $dapfforwc_advance_settings, $wp;
    $use_anchor = isset($dapfforwc_advance_settings["use_anchor"]) ? $dapfforwc_advance_settings["use_anchor"] : "";
    $use_filters_word = isset($dapfforwc_options["use_filters_word_in_permalinks"]) ? $dapfforwc_options["use_filters_word_in_permalinks"] : "";
    $dapfforwc_slug = isset($post) ? dapfforwc_get_full_slug($post->ID) : "";
    $request = $wp->request;
    $shortcode = $dapfforwc_advance_settings["product_shortcode"] ?? 'products'; // Shortcode to search for
    $attributes_list = dapfforwc_get_shortcode_attributes_from_page($post->post_content, $shortcode);
    foreach ($attributes_list as $attributes) {
        // Ensure that the 'category', 'attribute', and 'terms' keys exist
        $arrayCata = isset($attributes['category']) ? explode(",", $attributes['category']) : [];
        $tagValue = isset($attributes['tags']) ? $attributes['tags'] : [];
        $termsValue = isset($attributes['terms']) ? $attributes['terms'] : [];
        $filters = !empty($arrayCata) ? $arrayCata : (!empty($tagValue) ? $tagValue : $termsValue);

        // Use the combined full slug as the key in default_filters
        $dapfforwc_options['default_filters'][$dapfforwc_slug] = $filters;
        $dapfforwc_options['product_show_settings'][$dapfforwc_slug] = [
            'per_page'        => $attributes['limit'] ?? $attributes['per_page'] ?? '',
            'orderby'         => $attributes['orderby'] ?? '',
            'order'           => $attributes['order'] ?? '',
            'operator_second' => $attributes['terms_operator'] ?? $attributes['tag_operator'] ?? $attributes['cat_operator'] ?? 'IN'
        ];
    }
    update_option('dapfforwc_options', $dapfforwc_options);
    $second_operator = strtoupper($dapfforwc_options["product_show_settings"][$dapfforwc_slug]["operator_second"] ?? "IN");
    // Validate and sanitize host
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';

    // Validate and sanitize request URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    // Build the sanitized URL
    if (!empty($host) && !empty($request_uri)) {
        $url_page = esc_url("http://{$host}{$request_uri}");
    } else {
        $url_page = home_url(); // Fallback to homepage if values are missing
    }

    // Parse the URL
    $parsed_url = wp_parse_url($url_page);
    // Parse the query string into an associative array
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
    }
    // Get the value of 'filters'
    $filters = $query_params['filters'] ?? null;
    $default_filter = array_merge(
        $dapfforwc_options["default_filters"][$dapfforwc_slug] ?? [],
        explode(',', $filters ?? ''),
        explode('/', $request)
    );
    $ratings = array_values(array_filter($default_filter, 'is_numeric'));
    // Define default attributes and merge with user-defined attributes
    $atts = shortcode_atts(array(
        'attribute' => '',
        'terms' => '',
        'category' => '',
        'tag' => '',
        'product_selector' => '',
        'pagination_selector' => '',
        'mobile_responsive' => 'style_1',
        'use_custom_template_design' => 'no'
    ), $atts);

    if (!empty($atts['use_custom_template_design']) && $atts['use_custom_template_design'] === "yes") {
        // Ensure it's an array
        $dapfforwc_options['use_custom_template_in_page'] = isset($dapfforwc_options['use_custom_template_in_page']) ? $dapfforwc_options['use_custom_template_in_page'] : [];

        // Merge and ensure uniqueness
        if (is_string($dapfforwc_slug)) {
            $dapfforwc_options['use_custom_template_in_page'] = array_unique(array_merge($dapfforwc_options['use_custom_template_in_page'], [$dapfforwc_slug]));
        }

        // Update options
        update_option('dapfforwc_options', $dapfforwc_options);
    } else {
        // Remove the slug from the settings if it exists
        if (isset($dapfforwc_options['use_custom_template_in_page']) && is_array($dapfforwc_options['use_custom_template_in_page'])) {
            $dapfforwc_options['use_custom_template_in_page'] = array_values(array_diff($dapfforwc_options['use_custom_template_in_page'], [$dapfforwc_slug]));

            // Update options after removal
            update_option('dapfforwc_options', $dapfforwc_options);
        }
    }

    $formOutPut = "";
    $product_details = array_values(dapfforwc_get_woocommerce_product_details()["products"] ?? []);
    $products_id_by_rating = [];
    if (!empty($ratings)) {
        // Get product ids by rating
        foreach ($ratings as $rating) {
            $products_id_by_rating[] = array_column(array_filter($product_details, function ($product) use ($rating) {
                return $product['rating'] == $rating;
            }), 'ID');
        }
        $products_id_by_rating = array_merge(...$products_id_by_rating);
    }
    // Get Categories, Tags, attributes using the existing function
    $all_data = dapfforwc_get_woocommerce_attributes_with_terms();
    $all_cata = $all_data['categories'] ?? [];
    $all_tags = $all_data['tags'] ?? [];
    $all_attributes = $all_data['attributes'] ?? [];
    // Create Lookup Arrays
    $cata_lookup = array_combine(
        array_column($all_cata, 'slug'),
        array_column($all_cata, 'products')
    );
    $tag_lookup = array_combine(
        array_column($all_tags, 'slug'),
        array_column($all_tags, 'products')
    );
    // Match Filters
    $matched_cata_with_ids = array_intersect_key($cata_lookup, array_flip(array_filter($default_filter)));
    if ($second_operator === 'AND') {
        $products_id_by_cata = empty($matched_cata_with_ids) ? [] : array_values(array_intersect(...array_values($matched_cata_with_ids)));
    } else {
        $products_id_by_cata = empty($matched_cata_with_ids) ? [] : array_values(array_unique(array_merge(...array_values($matched_cata_with_ids))));
    }
    $matched_tag_with_ids = array_intersect_key($tag_lookup, array_flip(array_filter($default_filter)));
    if ($second_operator === 'AND') {
        $products_id_by_tag = empty($matched_tag_with_ids) ? [] : array_values(array_intersect(...array_values($matched_tag_with_ids)));
    } else {
        $products_id_by_tag = empty($matched_tag_with_ids) ? [] : array_values(array_unique(array_merge(...array_values($matched_tag_with_ids))));
    }

    // Match Attributes
    $products_id_by_attributes = [];
    $match_attributes_with_ids = [];
    if ((is_array($all_attributes) || is_object($all_attributes))) {
        foreach ($all_data['attributes'] as $taxonomy => $lookup) {
            // Ensure 'terms' key exists and is an array
            if (isset($lookup['terms']) && is_array($lookup['terms'])) {
                foreach ($lookup['terms'] as $term) {
                    if (in_array($term['slug'], $default_filter)) {
                        $match_attributes_with_ids[$taxonomy][] = $term['products'];
                        $products_id_by_attributes[] = $term['products'];
                    }
                }
            }
        }
    }

    $common_values = empty($products_id_by_attributes) ? [] : array_intersect(...$products_id_by_attributes);

    if (empty($products_id_by_cata) && empty($products_id_by_tag) && empty($common_values)) {
        $products_ids = [];
    } elseif (empty($products_id_by_cata) && empty($products_id_by_tag) && !empty($common_values)) {
        $products_ids = $common_values;
    } elseif (empty($products_id_by_cata) && !empty($products_id_by_tag) && empty($common_values)) {
        $products_ids = $products_id_by_tag;
    } elseif (!empty($products_id_by_cata) && empty($products_id_by_tag) && empty($common_values)) {
        $products_ids = $products_id_by_cata;
    } elseif (!empty($products_id_by_cata) && !empty($products_id_by_tag) && empty($common_values)) {
        $products_ids = array_values(array_intersect($products_id_by_cata, $products_id_by_tag));
    } elseif (!empty($products_id_by_cata) && empty($products_id_by_tag) && !empty($common_values)) {
        $products_ids = array_values(array_intersect($products_id_by_cata, $common_values));
    } elseif (empty($products_id_by_cata) && !empty($products_id_by_tag) && !empty($common_values)) {
        $products_ids = array_values(array_intersect($products_id_by_tag, $common_values));
    } else {
        $products_ids = array_values(array_intersect($products_id_by_cata, $products_id_by_tag, $common_values));
    }
    if (!empty($products_id_by_rating)) {
        $products_ids = array_values(array_intersect($products_ids, $products_id_by_rating));
    }


    $updated_filters = dapfforwc_get_updated_filters($products_ids, $all_data) ?? [];
    // Cache file path
    $cache_file = __DIR__ . '/min_max_prices_cache.json';
    $min_max_prices = dapfforwc_get_min_max_price($product_details, $products_ids);
    // Save to cache
    file_put_contents($cache_file, json_encode($min_max_prices, JSON_UNESCAPED_UNICODE));


    ob_start(); // Start output buffering
?>
    <style>
        <?php if ($atts['mobile_responsive'] === 'style_1') { ?>
        /* responsive filter */
        @media (max-width: 781px) {
            .rfilterbuttons {
                display: none;
            }

            #product-filter .filter-group div .title {
                cursor: pointer !important;
            }

            #product-filter:before {
                content: "Filter";
                background: linear-gradient(90deg, #041a57, #d62229);
                color: white;
                padding: 10px 11px;
                width: 60px;
                height: 45px;
                position: absolute;
                left: 0px;
            }

            form#product-filter {
                display: flex;
                flex-direction: row !important;
                overflow: scroll;
                gap: 10px;
                height: 66px;
                margin-left: 64px;
            }

            .filter-group .title {
                font-size: 16px !important;
            }

            .child-categories {
                display: block !important;
            }

            .filter-group {
                min-width: max-content;
                height: min-content;
            }

            #product-filter .items {
                position: absolute;
                left: 0;
                background: white;
                padding: 20px 15px;
                box-shadow: #efefef99 0 -4px 10px 4px;
                z-index: 999;
            }
        }

        <?php } ?><?php if ($atts['mobile_responsive'] === 'style_2') { ?><?php } ?>
    </style>
    <?php if ($atts['mobile_responsive'] === 'style_3') { ?>

        <style>
            @media (min-width: 781px) {

                #mobileonly,
                #filter-button {
                    display: none !important;
                }
            }

            @media (max-width: 781px) {
                .items {
                    display: block !important;
                }

                .mobile-filter {
                    position: fixed;
                    z-index: 999;
                    background: #ffffff;
                    width: 95%;
                    padding: 30px 20px 300px 20px;
                    height: 100%;
                    overflow: scroll;
                    box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
                    border-radius: 30px;
                    margin: 5px !important;
                    display: none;
                }

                .rfilterselected ul {
                    flex-wrap: nowrap;
                    overflow: scroll;
                }
            }
        </style>
    <?php } ?>
    <?php if ($atts['mobile_responsive'] === 'style_4') { ?>

        <style>
            @media (min-width: 781px) {

                #mobileonly,
                #filter-button {
                    display: none !important;
                }
            }

            @media (max-width: 781px) {
                .items {
                    display: block !important;
                }

                .mobile-filter {
                    position: fixed;
                    z-index: 999;
                    background: #ffffff;
                    width: 80%;
                    height: 100%;
                    overflow: scroll;
                    box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
                    bottom: 0;
                    right: 0;
                    transition: transform 0.3s ease-in-out;
                    transform: translateX(150%);
                }

                .mobile-filter.open {
                    transform: translateX(0%);
                }

                .rfilterselected ul {
                    flex-wrap: nowrap;
                    overflow: scroll;
                }
            }
        </style>
    <?php }

    if ($atts['mobile_responsive'] === 'style_3' ||  $atts['mobile_responsive'] === 'style_4') { ?>
        <button id="filter-button" style="position: fixed; z-index:999;     bottom: 20px;
    right: 20px; background-color: #041a57; color: white; border: none; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
            <i class="fa fa-filter" aria-hidden="true"></i>
        </button>
        <div class="mobile-filter">
            <div class="sm-top-btn" id="mobileonly" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding: 20px;margin-bottom: 10px;">
                <button id="filter-cancel-button" style="background: none;padding:0;color: #000;"> Cancel </button>
                <p style="margin: 0;" id="rcountproduct">Show(5)</p>
            </div>
        <?php
        echo '<div class="rfilterselected" id="mobileonly"><div><ul></ul></div></div>';
    }
    if ($atts['mobile_responsive'] === 'style_3') { ?>
            <script>
                jQuery(document).ready(function($) {
                    function isMobile() {
                        return $(window).width() < 768; // Adjust the width as needed
                    }

                    if (isMobile()) {
                        $('#filter-cancel-button').on('click', function(event) {
                            event.preventDefault();
                            $('.mobile-filter').slideUp();
                        });

                        $('#filter-button').on('click', function(event) {
                            event.preventDefault();
                            $('.mobile-filter').slideDown();
                        });

                        $(document).on('click', function(event) {
                            if (!$(event.target).closest('.mobile-filter, #filter-button').length) {
                                $('.mobile-filter').slideUp();
                            }
                        });
                    }
                });
            </script>
        <?php }

    if ($atts['mobile_responsive'] === 'style_4') { ?>
            <script>
                jQuery(document).ready(function($) {
                    function isMobile() {
                        return $(window).width() < 768; // Adjust the width as needed
                    }

                    if (isMobile()) {
                        $('#filter-button').on('click', function(event) {
                            event.preventDefault();
                            $('.mobile-filter').toggleClass('open');
                        });

                        $('#filter-cancel-button').on('click', function(event) {
                            event.preventDefault();
                            $('.mobile-filter').removeClass('open');
                        });

                        $(document).on('click', function(event) {
                            if (!$(event.target).closest('.mobile-filter, #filter-button').length) {
                                $('.mobile-filter').removeClass('open');
                            }
                        });
                    }
                });
            </script>
        <?php } ?>
        <form id="product-filter" method="POST" data-product_show_settings='<?php echo json_encode($dapfforwc_options['product_show_settings'][$dapfforwc_slug]); ?>'
            <?php if (!empty($atts['product_selector'])) {
                echo 'data-product_selector="' . esc_attr($atts["product_selector"]) . '"';
            } ?>
            <?php if (!empty($atts['pagination_selector'])) {
                echo 'data-pagination_selector="' . esc_attr($atts["pagination_selector"]) . '"';
            } ?>>
            <?php
            wp_nonce_field('gm-product-filter-action', 'gm-product-filter-nonce');
            echo dapfforwc_filter_form($updated_filters, $default_filter, $use_anchor, $use_filters_word, $atts, $min_price = $dapfforwc_styleoptions["price"]["min_price"] ?? (intval($min_max_prices['min']) - 1) ?? 0, $max_price = $dapfforwc_styleoptions["price"]["max_price"] ?? (intval($min_max_prices['max']) ?? 100000000000) + 1, []);
            echo $formOutPut;
            echo '</form>';
            if ($atts['mobile_responsive'] === 'style_3' || $atts['mobile_responsive'] === 'style_4') { ?>
        </div>
    <?php }
    ?>

    <!-- Loader HTML -->
    <?php echo $dapfforwc_options["loader_html"] ?? '' ?>
    <style>
        <?php echo $dapfforwc_options["loader_css"] ?? '' ?>
    </style>
    <?php
    if (isset($dapfforwc_options["loader_html"])) {
        echo $dapfforwc_options["loader_html"];
    }

    if (isset($dapfforwc_options["loader_css"])) {
        echo '<style>' . $dapfforwc_options["loader_css"] . '</style>';
    }
    ?>
    <div id="roverlay" style="display: none;"></div>

    <div id="filtered-products">
        <!-- AJAX results will be displayed here -->
    </div>

<?php

    // End output buffering and return content
    return ob_get_clean();
}
add_shortcode('plugincy_filters', 'dapfforwc_product_filter_shortcode');

// General sorting function
function dapfforwc_customSort($a, $b)
{
    // Try to convert to timestamp for date comparison
    $dateA = strtotime($a);
    $dateB = strtotime($b);

    if ($dateA && $dateB) {
        return $dateA <=> $dateB; // Both are dates
    }

    // Check if both are numeric
    if (is_numeric($a) && is_numeric($b)) {
        return $a <=> $b; // Both are numbers
    }

    // Fallback to string comparison
    return strcmp($a, $b);
}
function dapfforwc_render_filter_option($sub_option, $title, $value, $checked, $dapfforwc_styleoptions, $name, $attribute, $singlevalueSelect, $count, $min_price = 0, $max_price = 10000, $min_max_prices = [])
{
    $output = '';

    switch ($sub_option) {
        case 'checkbox':
            $output .= '<label><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-checkbox" name="' . $name . '[]" value="' . $value . '"' . $checked . '> ' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</label>';
            break;

        case 'radio_check':
            $output .= '<label><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-radio-check" name="' . $name . '[]" value="' . $value . '"' . $checked . '> ' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</label>';
            break;

        case 'radio':
            $output .= '<label><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-radio" name="' . $name . '[]" value="' . $value . '"' . $checked . '> ' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</label>';
            break;

        case 'square_check':
            $output .= '<label class="square-option"><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-square-check" name="' . $name . '[]" value="' . $value . '"' . $checked . '> <span>' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</span></label>';
            break;

        case 'square':
            $output .= '<label class="square-option"><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-square" name="' . $name . '[]" value="' . $value . '"' . $checked . '> <span>' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</span></label>';
            break;

        case 'checkbox_hide':
            $output .= '<label><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-checkbox" name="' . $name . '[]" value="' . $value . '"' . $checked . ' style="display:none;"> <span>' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</span></label>';
            break;

        case 'color':
        case 'color_no_border':
        case 'color_circle':
        case 'color_value':
            $color = $dapfforwc_styleoptions[$attribute]['colors'][$value] ?? '#000'; // Default color
            $border = ($sub_option === 'color_no_border') ? 'none' : '1px solid #000';
            $value_show = ($sub_option === 'color_value') ? 'block' : 'none';
            $output .= '<label style="position: relative;"><input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-color" name="' . $name . '[]" value="' . $value . '"' . $checked . '>
                <span class="color-box" style="background-color: ' . $color . '; border: ' . $border . '; width: 30px; height: 30px;"></span><span style="display:' . $value_show . ';">' . $value . '<span></label>';
            break;

        case 'image':
        case 'image_no_border':
            $image = $dapfforwc_styleoptions[$attribute]['images'][$value] ?? 'default-image.jpg'; // Default image
            $border_class = ($sub_option === 'image_no_border') ? 'no-border' : '';
            $output .= '<label class="image-option ' . $border_class . '">
                <input type="' . ($singlevalueSelect === "yes" ? 'radio' : 'checkbox') . '" class="filter-image" name="' . $name . '[]" value="' . $value . '"' . $checked . '>
                <img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '" /></label>';
            break;

        case 'select2':
        case 'select2_classic':
        case 'select':
            $output .= '<option class="filter-option" value="' . $value . '"' . ($checked ? 'selected' : '') . '> ' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</option>';
            break;
        case 'input-price-range':
            $default_min_price = $dapfforwc_styleoptions["price"]["min_price"] ?? $min_max_prices['min'] ?? 0;
            $default_max_price = $dapfforwc_styleoptions["price"]["max_price"] ?? ($min_max_prices['max'] ?? 10000) + 1;
            $output .= '<div class="range-input"><label for="min-price">Min Price:</label>
        <input type="number" id="min-price" name="min_price" min="' . $default_min_price . '" step="1" placeholder="Min" value="' . $min_price . '" style="position: relative; height: max-content; top: unset; pointer-events: all;">
        
        <label for="max-price">Max Price:</label>
        <input type="number" id="max-price" name="max_price" min="' . $default_min_price . '" step="1" placeholder="Max" value="' . $max_price . '" style="position: relative; height: max-content; top: unset; pointer-events: all;"></div>';
            break;
        case 'slider':
            $default_min_price = $dapfforwc_styleoptions["price"]["min_price"] ?? $min_max_prices['min'] ?? 0;
            $default_max_price = $dapfforwc_styleoptions["price"]["max_price"] ?? ($min_max_prices['max'] ?? 10000) + 1;
            $output .= '<div class="price-input">
        <div class="field">
          <span>Min</span>
          <input type="number" id="min-price" name="min_price" class="input-min" min="' . $default_min_price . '" value="' . $min_price . '">
        </div>
        <div class="separator">-</div>
        <div class="field">
          <span>Max</span>
          <input type="number" id="max-price" name="max_price" min="' . $default_min_price . '" class="input-max" value="' . $max_price . '">
        </div>
      </div>
      <div class="slider">
        <div class="progress"></div>
      </div>
      <div class="range-input">
        <input type="range" id="price-range-min" class="range-min" min="' . $default_min_price . '" max="' . $default_max_price . '" value="' . $min_price . '" >
        <input type="range" id="price-range-max" class="range-max" min="' . $default_min_price . '" max="' . $default_max_price . '" value="' . $max_price . '">
      </div>';
            break;
        case 'price':
            $default_min_price = $dapfforwc_styleoptions["price"]["min_price"] ?? $min_max_prices['min'] ?? 0;
            $default_max_price = $dapfforwc_styleoptions["price"]["max_price"] ?? ($min_max_prices['max'] ?? 10000) + 1;
            $output .= '<div class="price-input" style="visibility: hidden; margin: 0;">
        <div class="field">
            <input type="number" id="min-price" name="min_price" class="input-min" min="' . $default_min_price . '" value="' . $min_price . '">
        </div>
        <div class="separator">-</div>
        <div class="field">
            <input type="number" id="max-price" name="max_price" min="' . $default_min_price . '" class="input-max" value="' . $max_price . '">
        </div>
        </div>
        <div class="slider">
        <div class="progress progress-percentage"></div>
        </div>
        <div class="range-input">
        <input type="range" id="price-range-min" class="range-min" min="' . $default_min_price . '" max="' . $default_max_price . '" value="' . $min_price . '">
        <input type="range" id="price-range-max" class="range-max" min="' . $default_min_price . '" max="' . $default_max_price . '" value="' . $max_price . '">
        </div>';
            break;
        case 'rating-text':
            $output .= '<label><input type="checkbox" name="rating[]" value="5" ' . (in_array("5", $checked) ? ' checked' : '') . '> 5 Stars 
    </label>
        <label><input type="checkbox" name="rating[]" value="4" ' . (in_array("4", $checked) ? ' checked' : '') . '> 4 Stars & Up</label>
        <label><input type="checkbox" name="rating[]" value="3" ' . (in_array("3", $checked) ? ' checked' : '') . '> 3 Stars & Up</label>
        <label><input type="checkbox" name="rating[]" value="2" ' . (in_array("2", $checked) ? ' checked' : '') . '> 2 Stars & Up</label>
        <label><input type="checkbox" name="rating[]" value="1" ' . (in_array("1", $checked) ? ' checked' : '') . '> 1 Star & Up</label>';
            break;
        case 'rating':
            for ($i = 5; $i >= 1; $i--) {
                $output .= '<label>';
                $output .= '<input type="checkbox" name="rating[]" value="' . esc_attr($i) . '" ' . (in_array($i, $checked) ? ' checked' : '') . '>';
                $output .= '<span class="stars">';
                for ($j = 1; $j <= $i; $j++) {
                    $output .= '<i class="fa fa-star" aria-hidden="true"></i>';
                }
                $output .= '</span>';
                $output .= '</label>';
            }
            break;
        case 'dynamic-rating':
            $output .= '<input type="radio" id="star5" name="rating[]" value="5" />
  <label class="star" for="star5" title="Awesome" aria-hidden="true"></label>
  <input type="radio" id="star4" name="rating[]" value="4" />
  <label class="star" for="star4" title="Great" aria-hidden="true"></label>
  <input type="radio" id="star3" name="rating[]" value="3" />
  <label class="star" for="star3" title="Very good" aria-hidden="true"></label>
  <input type="radio" id="star2" name="rating[]" value="2" />
  <label class="star" for="star2" title="Good" aria-hidden="true"></label>
  <input type="radio" id="star1" name="rating[]" value="1" />
  <label class="star" for="star1" title="Bad" aria-hidden="true"></label>';
            break;
        default:
            $output .= '<label><input type="checkbox" class="filter-checkbox" name="' . $name . '[]" value="' . $value . '"' . $checked . '> ' . $title . ($count != 0 ? ' (' . $count . ')' : '') . '</label>';
            break;
    }

    return $output;
}
// Function to get child categories from $updated_filters["categories"]
function dapfforwc_get_child_categories($categories, $parent_id)
{
    $child_categories = array();

    foreach ($categories as $category) {
        // Check if the category is a WP_Term object
        if ($category instanceof WP_Term) {
            if ($category->parent == $parent_id) {
                $child_categories[] = $category;
            }
        }
        // Check if the category is a stdClass object
        elseif (is_object($category) && $category instanceof stdClass) {
            if (isset($category->parent) && $category->parent == $parent_id) {
                $child_categories[] = $category;
            }
        }
    }

    return $child_categories;
}
// Recursive function to render categories
function dapfforwc_render_category_hierarchy(
    $categories,
    $selected_categories,
    $sub_option,
    $dapfforwc_styleoptions,
    $singlevaluecataSelect,
    $show_count,
    $use_anchor,
    $use_filters_word,
    $hierarchical,
    $child_category
) {
    $categoryHierarchyOutput = "";
    foreach ($categories as $category) {
        if (is_object($category)) {
            $value = esc_attr($category->slug);
            $title = esc_html($category->name);
        } elseif (is_array($category)) {
            $value = esc_attr($category['slug']);
            $title = esc_html($category['name']);
        } else {
            // Handle cases where $category is neither an object nor an array
            $value = '';
            $title = '';
        }
        $count = $show_count === 'yes' ? (is_object($category) ? $category->count : $category["count"]) : 0;
        $checked = in_array($category->slug, $selected_categories) ? ($sub_option === 'select' || str_contains($sub_option, 'select2') ? ' selected' : ' checked') : '';
        $anchorlink = $use_filters_word === 'on' ? "filters/$value" : $value;

        // Fetch child categories
        $child_categories = dapfforwc_get_child_categories($child_category, $category->term_id);

        // Render current category
        $categoryHierarchyOutput .= $use_anchor === 'on'
            ? '<a href="' . esc_attr($anchorlink) . '" style="display:flex;align-items: center;">'
            . dapfforwc_render_filter_option($sub_option, $title, $value, $checked, $dapfforwc_styleoptions, 'category', 'category', $singlevaluecataSelect, $count)
            . (!empty($child_categories) && $hierarchical === 'enable_hide_child' ? '<span class="show-sub-cata">+</span>' : '')
            . '</a>'
            : '<a style="display:flex;align-items: center;text-decoration: none;">'
            . dapfforwc_render_filter_option($sub_option, $title, $value, $checked, $dapfforwc_styleoptions, 'category', 'category', $singlevaluecataSelect, $count) . (!empty($child_categories) && $hierarchical === 'enable_hide_child' ? '<span class="show-sub-cata" style="cursor:pointer;">+</span>' : '')
            . '</a>';

        // Render child categories
        if (!empty($child_categories)) {
            $categoryHierarchyOutput .= '<div class="child-categories" style="display:' . ($hierarchical === 'enable_hide_child' ? 'none;' : 'block;') . '">';
            $categoryHierarchyOutput .= dapfforwc_render_category_hierarchy($child_categories, $selected_categories, $sub_option, $dapfforwc_styleoptions, $singlevaluecataSelect, $show_count, $use_anchor, $use_filters_word, $hierarchical, $child_category);
            $categoryHierarchyOutput .= '</div>';
        }
    }
    return $categoryHierarchyOutput;
}

function dapfforwc_product_filter_shortcode_single($atts)
{
    $atts = shortcode_atts(
        array(
            'name' => '', // Default attribute name
        ),
        $atts,
        'get_terms_by_attribute'
    );

    // Check if the name is provided
    if (empty($atts['name'])) {
        return '<p style="background:red;background: red;text-align: center;color: #fff;">Please provide an attribute slug.</p>';
    }

    // Generate the output
    $output = '<form class="rfilterbuttons" id="' . $atts['name'] . '"><ul>';
    $output .= '<li>
                <input id="selected_category_1" type="checkbox" value="category_1" checked="">
                <label for="selected_category_1">Category 1</label>
            </li>
           <li>
                <input id="selected_category_2" type="checkbox" value="category_2" checked="">
                <label for="selected_category_2">Category 2</label>
            </li>
            <li class="checked">
                <input id="selected_category_3" type="checkbox" value="category_3" checked="">
                <label for="selected_category_3">Category 3</label>
            </li>';
    $output .= '</ul></form>';

    return $output;
}
add_shortcode('plugincy_filters_single', 'dapfforwc_product_filter_shortcode_single');

function dapfforwc_product_filter_shortcode_selected()
{

    // Generate the output
    $output = '<form class="rfilterselected"><div><ul>';
    $output .= '<li class="checked">
                <input id="selected_category_1" type="checkbox" value="category_1" checked="">
                <label for="selected_category_1">Category 1</label>
                <label style="font-size:12px;margin-left:5px;">x</label>
            </li>
           <li class="checked">
                <input id="selected_category_2" type="checkbox" value="category_2" checked="">
                <label for="selected_category_2">Category 2</label>
                <label style="font-size:12px;margin-left:5px;">x</label>
            </li>
            <li class="checked">
                <input id="selected_category_3" type="checkbox" value="category_3" checked="">
                <label for="selected_category_3">Category 3</label>
                <label style="font-size:12px;margin-left:5px;">x</label>
            </li>';
    $output .= '</ul></div></form>';

    return $output;
}
add_shortcode('plugincy_filters_selected', 'dapfforwc_product_filter_shortcode_selected');


function dapfforwc_get_updated_filters($product_ids, $all_data = [])
{
    $categories = [];
    $attributes = [];
    $tags = [];

    if (!empty($product_ids)) {
        // Get attributes with terms
        if (empty($all_data)) {
            $all_data = dapfforwc_get_woocommerce_attributes_with_terms();
        }

        // Extract categories and tags from all_data
        // Categories
        if (is_array($all_data['categories'] ?? []) || is_object($all_data['categories'] ?? [])) {
            foreach ($all_data['categories'] ?? [] as $term_id => $category) {
                if (!empty(array_intersect($product_ids, $category['products']))) {
                    $categories[$term_id] = (object) [
                        'term_id' => $term_id,
                        'name'    => $category['name'],
                        'slug'    => $category['slug'],
                        'parent'  => $category['parent'],
                        'taxonomy' => 'product_cat',
                        'count'   => count(array_intersect($category['products'], $product_ids)),
                    ];
                }
            }
        }

        // Tags
        if (is_array($all_data['tags'] ?? []) || is_object($all_data['tags'] ?? [])) {
            foreach ($all_data['tags'] ?? [] as $term_id => $tag) {
                if (!empty(array_intersect($product_ids, $tag['products']))) {
                    $tags[$term_id] = (object) [
                        'term_id' => $term_id,
                        'name'    => $tag['name'],
                        'slug'    => $tag['slug'],
                        'taxonomy' => 'product_tag',
                        'count'   => count(array_intersect($tag['products'], $product_ids)),
                    ];
                }
            }
        }

        // Extract attributes
        if (is_array($all_data['attributes'] ?? []) || is_object($all_data['attributes'] ?? [])) {
            foreach ($all_data['attributes'] ?? [] as $attribute) {
                $attribute_name = $attribute['attribute_name'];
                $terms = $attribute['terms'];

                if (is_array($terms) || is_object($terms)) {
                    foreach ($terms as $term) {
                        // Check if the term's products match the provided product IDs
                        if (!empty(array_intersect($product_ids, $term['products']))) {
                            $attributes[$attribute_name][] = [
                                'term_id' => $term['term_id'],
                                'attribute_label' => $term['name'],
                                'name'    => $term['name'],
                                'slug'    => $term['slug'],
                                'count'   => count(array_intersect($term['products'], $product_ids)),
                            ];
                        }
                    }
                }
            }
        }
    }
    return [
        'categories' => array_values($categories), // Return as array
        'attributes' => $attributes,
        'tags' => array_values($tags), // Return as array
    ];
}


function dapfforwc_get_woocommerce_attributes_with_terms()
{
    global $wpdb;

    $cache_file = __DIR__ . '/woocommerce_attributes_cache.json';
    $cache_time = 43200; // 12 hours

    // Check and return cache if valid
    if (file_exists($cache_file) && (filemtime($cache_file) > (time() - $cache_time))) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $data = ['attributes' => [], 'categories' => [], 'tags' => []];

    // Optimized query with direct attribute taxonomy check
    $query = $wpdb->prepare("
    SELECT t.term_id, t.name, t.slug, tr.object_id, tt.taxonomy, a.attribute_name, a.attribute_label, tt.parent
    FROM {$wpdb->prefix}terms AS t
    INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
    LEFT JOIN {$wpdb->prefix}term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
    LEFT JOIN {$wpdb->prefix}woocommerce_attribute_taxonomies AS a ON tt.taxonomy = CONCAT('pa_', a.attribute_name)
    INNER JOIN {$wpdb->prefix}posts AS p ON tr.object_id = p.ID
    WHERE (tt.taxonomy IN (%s, %s) OR a.attribute_name IS NOT NULL)
    AND p.post_type = 'product' 
    AND p.post_status = 'publish'
    ORDER BY t.term_id
", 'product_cat', 'product_tag');

    $results = $wpdb->get_results($query, ARRAY_A);

    if (!empty($results)) {
        foreach ($results as $row) {
            $term_id = $row['term_id'];
            $taxonomy = $row['taxonomy'];

            if ($taxonomy === 'product_cat') {
                $data['categories'][$term_id] = $data['categories'][$term_id] ?? [
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'parent' => $row['parent'],
                    'products' => []
                ];

                if ($row['object_id'] && !in_array($row['object_id'], $data['categories'][$term_id]['products'])) {
                    $data['categories'][$term_id]['products'][] = $row['object_id'];
                }
            } elseif ($taxonomy === 'product_tag') {
                $data['tags'][$term_id] = $data['tags'][$term_id] ?? [
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'products' => []
                ];

                if ($row['object_id'] && !in_array($row['object_id'], $data['tags'][$term_id]['products'])) {
                    $data['tags'][$term_id]['products'][] = $row['object_id'];
                }
            } elseif (!empty($row['attribute_name'])) {
                $attr_name = $row['attribute_name'];
                $data['attributes'][$attr_name] = $data['attributes'][$attr_name] ?? [
                    'attribute_label' => $row['attribute_label'],
                    'attribute_name' => $attr_name,
                    'terms' => []
                ];

                // Store terms directly in arrays for faster lookups
                $data['attributes'][$attr_name]['terms'][$term_id] = $data['attributes'][$attr_name]['terms'][$term_id] ?? [
                    'term_id' => $term_id,
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'products' => []
                ];

                if ($row['object_id'] && !in_array($row['object_id'], $data['attributes'][$attr_name]['terms'][$term_id]['products'])) {
                    $data['attributes'][$attr_name]['terms'][$term_id]['products'][] = $row['object_id'];
                }
            }
        }
    }

    // Convert associative term arrays to indexed arrays
    foreach ($data['attributes'] as $key => $attr) {
        $data['attributes'][$key]['terms'] = array_values($attr['terms']);
    }

    // Save to cache
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_UNICODE));

    return $data;
}
function dapfforwc_get_woocommerce_product_details()
{
    global $wpdb;
    $cache_file = __DIR__ . '/woocommerce_product_details.json';
    $cache_time = 43200; // 12 hours

    // Check and return cache if valid
    if (file_exists($cache_file) && (filemtime($cache_file) > (time() - $cache_time))) {
        return json_decode(file_get_contents($cache_file), true);
    }

    // Query for all products with their meta data, categories, and thumbnail URLs
    $query = "
    SELECT p.ID, p.post_title, p.post_name, p.post_modified, p.menu_order, p.post_excerpt,
           MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) AS price,
           MAX(CASE WHEN pm.meta_key = '_sale_price' THEN pm.meta_value END) AS sale_price,
           MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) AS regular_price,
           MAX(CASE WHEN pm.meta_key = '_min_variation_price' THEN pm.meta_value END) AS min_variation_price,
           MAX(CASE WHEN pm.meta_key = '_max_variation_price' THEN pm.meta_value END) AS max_variation_price,
           MAX(CASE WHEN pm.meta_key = '_min_variation_regular_price' THEN pm.meta_value END) AS min_variation_regular_price,
           MAX(CASE WHEN pm.meta_key = '_min_variation_sale_price' THEN pm.meta_value END) AS min_variation_sale_price,
           MAX(CASE WHEN pm.meta_key = '_wc_average_rating' THEN pm.meta_value END) AS average_rating,
           MAX(CASE WHEN pm.meta_key = '_product_type' THEN pm.meta_value END) AS product_type,
           MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) AS sku,
           MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) AS stock_status,
           (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM {$wpdb->prefix}term_relationships tr
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
            WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat') AS categories,
           (SELECT GROUP_CONCAT(t.slug SEPARATOR ', ') FROM {$wpdb->prefix}term_relationships tr
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
            WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat') AS category_slugs,
           (SELECT CONCAT('" . home_url() . "/wp-content/uploads/', 
                          pm2.meta_value) 
            FROM {$wpdb->prefix}postmeta pm2 
            WHERE pm2.post_id = p.ID AND pm2.meta_key = '_thumbnail_id') AS thumbnail_id,
           (SELECT guid FROM {$wpdb->prefix}posts WHERE ID = (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = p.ID AND meta_key = '_thumbnail_id')) AS product_image
    FROM {$wpdb->prefix}posts p
    LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
    WHERE p.post_type = 'product'
    AND p.post_status = 'publish'
    GROUP BY p.ID
    ";

    $results = $wpdb->get_results($query, ARRAY_A);
    $products = [];

    if (!empty($results)) {
        foreach ($results as $row) {
            $product_id = $row['ID'];

            // Determine product type and pricing
            $product_type = $row['product_type'] ?: 'simple';
            $price = '';
            $sale_active = false;

            if ($product_type === 'variable') {
                $price = $row['min_variation_price'] ?: '';
                $min_sale_price = $row['min_variation_sale_price'] ?: '';
                $sale_active = !empty($min_sale_price) && $min_sale_price == $price;
            } else {
                $regular_price = $row['regular_price'] ?: '';
                $sale_price = $row['sale_price'] ?: '';
                $price = $row['price'] ?: $regular_price;
                $sale_active = !empty($sale_price) && $sale_price == $price;
            }

            // Get rating
            $rating = floatval($row['average_rating']) ?: 0;

            // Get product image directly from the query
            $product_image = $row['product_image'];

            // Get product categories
            $product_category = array_map(function ($name, $slug) {
                return ['name' => $name, 'slug' => $slug];
            }, explode(', ', $row['categories']), explode(', ', $row['category_slugs']));

            $products[$product_id] = [
                'ID' => $product_id,
                'post_title' => $row['post_title'],
                'post_name' => $row['post_name'],
                'price' => $price,
                'rating' => $rating,
                'post_modified' => $row['post_modified'],
                'menu_order' => intval($row['menu_order']),
                'on_sale' => $sale_active,
                'product_image' => $product_image,
                'product_excerpt' => $row['post_excerpt'],
                'product_sku' => $row['sku'] ?: '',
                'product_stock' => $row['stock_status'] ?: 'instock',
                'product_category' => $product_category,
            ];
        }
    }

    // Convert to indexed array for better JSON compatibility
    $product_data = ['products' => $products];

    // Save to cache with error handling
    if (file_put_contents($cache_file, json_encode($product_data, JSON_UNESCAPED_UNICODE)) === false) {
        error_log('Failed to write product details cache to ' . $cache_file);
    }

    return $product_data;
}



// Clear cache when a term is updated
add_action('edited_term', function ($term_id) {
    $cache_file = __DIR__ . '/woocommerce_attributes_cache.json';
    if (file_exists($cache_file)) {
        wp_delete_file($cache_file);
    }
});

// Clear cache when a product is updated
add_action('save_post_product', function ($post_id) {
    $cache_file = __DIR__ . '/woocommerce_attributes_cache.json';
    if (file_exists($cache_file)) {
        wp_delete_file($cache_file);
    }
});
function dapfforwc_get_shortcode_attributes_from_page($content, $shortcode)
{
    // Use regex to match the shortcode and capture its attributes
    preg_match_all('/\[' . preg_quote($shortcode, '/') . '([^]]*)\]/', $content, $matches);

    $attributes_list = [];
    foreach ($matches[1] as $shortcode_instance) {
        // Clean up the attribute string and parse it
        $shortcode_instance = trim($shortcode_instance);
        $attributes_list[] = shortcode_parse_atts($shortcode_instance);
    }

    return $attributes_list;
}
