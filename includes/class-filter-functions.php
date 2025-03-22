<?php
if (!defined('ABSPATH')) {
    exit;
}
class dapfforwc_Filter_Functions
{

    public function process_filter()
    {
        global $dapfforwc_options, $dapfforwc_styleoptions, $dapfforwc_advance_settings, $dapfforwc_front_page_slug;
        // Initialize variables with default values
        $update_filter_options =  isset($dapfforwc_options["update_filter_options"]) ? $dapfforwc_options["update_filter_options"] : "";
        $remove_outofStock_product = isset($dapfforwc_advance_settings["remove_outofStock"]) ? $dapfforwc_advance_settings["remove_outofStock"] : "";

        if (!isset($_POST['gm-product-filter-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gm-product-filter-nonce'])), 'gm-product-filter-action')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
            wp_die();
        }
        // Determine the current page number
        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
        $currentpage_slug = isset($_POST['current-page']) ? sanitize_text_field(wp_unslash($_POST['current-page'])) : "";
        $orderby = $this->get_orderby() !== "" ? $this->get_orderby() : ($wcapf_options['product_show_settings'][$currentpage_slug]['orderby'] ?? 'date');
        $default_filter = [];
        // Check if 'selectedvalues' is set and not empty
        if (!empty($_POST['selectedvalues'])) {
            // Convert the string to an array
            $default_filter = array_map('sanitize_text_field', explode(',', sanitize_text_field(wp_unslash($_POST['selectedvalues']))));
        }
        $ratings = array_values(array_filter($default_filter, 'is_numeric'));
        $second_operator = isset($dapfforwc_options["product_show_settings"]["upcoming-conferences"]["operator_second"]) ? strtoupper($dapfforwc_options["product_show_settings"]["upcoming-conferences"]["operator_second"]) : "IN";
        $product_details = array_values(dapfforwc_get_woocommerce_product_details()["products"] ?? []);
        $product_details_json = dapfforwc_get_woocommerce_product_details()["products"] ?? [];
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
            $products_id_by_cata = empty($matched_cata_with_ids) ? [] : array_intersect(...array_values($matched_cata_with_ids));
        } else {
            $products_id_by_cata = empty($matched_cata_with_ids) ? [] : array_values(array_unique(array_merge(...array_values($matched_cata_with_ids))));
        }
        $matched_tag_with_ids = array_intersect_key($tag_lookup, array_flip(array_filter($default_filter)));
        if ($second_operator === 'AND') {
            $products_id_by_tag = empty($matched_tag_with_ids) ? [] : array_intersect(...array_values($matched_tag_with_ids));
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
        $price_search_value = $this->getpricevalue_search();
        $products_id_by_price = [];
        $products_id_by_search_term = [];
        if (!empty($price_search_value)) {
            $products_id_by_price = array_filter($product_details, function ($product) use ($price_search_value, $remove_outofStock_product) {
                if ($remove_outofStock_product !== "on" && empty($product['price'])) {
                    return true;
                }
                return $product['price'] >= $price_search_value['min'] && $product['price'] <= $price_search_value['max'];
            });
            $products_id_by_price = array_column($products_id_by_price, 'ID');

            if (!empty($price_search_value['s'])) {
                $products_id_by_search_term = array_filter($product_details, function ($product) use ($price_search_value) {
                    return strpos(strtolower($product['post_title']), strtolower($price_search_value['s'])) !== false;
                });
                $products_id_by_search_term = array_column($products_id_by_search_term, 'ID');
            }
        }
        if (!empty($products_id_by_price)) {
            $products_ids = array_intersect($products_ids, $products_id_by_price);
        }
        if (!empty($products_id_by_search_term)) {
            $products_ids = array_intersect($products_ids, $products_id_by_search_term);
        }
        $missing_product_ids = array_diff($products_ids, array_keys($product_details_json));
        if (!empty($missing_product_ids)) {
            $missing_products = wc_get_products(array('include' => $missing_product_ids));
            foreach ($missing_products as $product) {
                $product_details_json[$product->get_id()] = [
                    'ID' => $product->get_id(),
                    'post_title' => $product->get_title(),
                    'post_name' => $product->get_slug(),
                    'price' => $product->get_price(),
                    'product_image' => $product->get_image(),
                    'product_excerpt' => $product->get_short_description(),
                    'rating' => $product->get_average_rating(),
                    'product_category' => wc_get_product_category_list($product->get_id()),
                    'product_sku' => $product->get_sku(),
                    'product_stock' => $product->get_stock_quantity(),
                    'on_sale' => $product->is_on_sale(),
                    'menu_order' => $product->get_menu_order(),
                    'post_modified' => $product->get_date_modified()->date('Y-m-d H:i:s')
                ];
            }
        }
        // Order products based on $orderby
        if (!empty($orderby)) {
            $orderby = $orderby === 'menu_order date' ? 'menu_order' : ($orderby === 'date' ? 'post_modified' : $orderby);
            usort($products_ids, function ($a, $b) use ($product_details_json, $orderby) {
                if (!isset($product_details_json[$a][$orderby]) || !isset($product_details_json[$b][$orderby])) {
                    return 0;
                }
                return $product_details_json[$a][$orderby] <=> $product_details_json[$b][$orderby];
            });
        }
        $count_total_showing_product = count($products_ids);

        $updated_filters = dapfforwc_get_updated_filters($products_ids);

        $min_max_prices = dapfforwc_get_min_max_price($product_details, $products_ids);



        $min_price = isset($_POST['min_price']) ? floatval(sanitize_text_field(wp_unslash($_POST['min_price']))) : ($dapfforwc_styleoptions["price"]["min_price"] ?? $min_max_prices['min']);

        $max_price = isset($_POST['max_price']) ? floatval(sanitize_text_field(wp_unslash($_POST['max_price']))) : ($dapfforwc_styleoptions["price"]["max_price"] ?? $min_max_prices['max'] + 1);

        // Pass sanitized values to the function
        $filterform = dapfforwc_filter_form($updated_filters, $default_filter, "", "", "", $min_price, $max_price, [], $price_search_value['s'] ?? '');
        $cache_file = __DIR__ . '/permalinks_cache.json';
        $cache_time = 12 * 60 * 60; // 12 hours in seconds

        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
            $permalinks = json_decode(file_get_contents($cache_file), true);
        } else {
            $permalinks = get_option('woocommerce_permalinks');
            file_put_contents($cache_file, json_encode($permalinks));
        }

        // Capture the product listing
        ob_start();

        $currentpage_slug = $currentpage_slug == "/" ? $dapfforwc_front_page_slug : $currentpage_slug;
        $per_page = isset($dapfforwc_options["product_show_settings"][$currentpage_slug]["per_page"]) ? intval($dapfforwc_options["product_show_settings"][$currentpage_slug]["per_page"]) : 12;
        $total_pages = ceil($count_total_showing_product / $per_page);
        $start_index = ($paged - 1) * $per_page;
        $end_index = min($start_index + $per_page, $count_total_showing_product);

        for ($i = $start_index; $i < $end_index; $i++) {
            if (isset($products_ids[$i])) {
                $product_id = $products_ids[$i];
                if (isset($product_details_json[$product_id])) {
                    $product = $product_details_json[$product_id];
                    $this->display_product($product, $currentpage_slug, $permalinks);
                }
            }
        }

        $product_html = ob_get_clean();

        // Send both the filtered products and updated filters back to the AJAX request
        wp_send_json_success(array(
            'products' => $product_html,
            'total_product_fetch' => $count_total_showing_product,
            'pagination' => $this->pagination($paged, $total_pages),
            'filter_options' => $filterform
        ));

        wp_die();
    }
    private function get_orderby()
    {
        return isset($_POST['orderby']) && $_POST['orderby'] !== "undefined" ? sanitize_text_field(wp_unslash($_POST['orderby'])) : "";
    }
    private function getpricevalue_search()
    {
        $price_search_txt = [];
        if (!isset($_POST['gm-product-filter-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gm-product-filter-nonce'])), 'gm-product-filter-action')) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
            wp_die();
        }
        if (isset($_POST['min_price']) && $_POST['min_price'] !== '') {
            $price_search_txt["min"] = floatval($_POST['min_price']);
        }

        // Maximum Price Filter
        if (isset($_POST['max_price']) && $_POST['max_price'] !== '') {
            $price_search_txt["max"] = floatval($_POST['max_price']);
        }
        if (!empty($_POST['s'])) {
            $search_term = sanitize_text_field(wp_unslash($_POST['s']));
            $price_search_txt["s"] = $search_term;
        }

        return $price_search_txt;
    }
    private function display_product($product, $currentpage_slug, $permalinks)
    {
        global $dapfforwc_options;
        // Get product details
        $product_link = home_url($permalinks['product_base'] . '/' . $product['post_name']);
        $product_title = $product['post_title'];
        $product_price = $product['price'];
        $product_image = $product['product_image'] === null ? '/wp-content/uploads/woocommerce-placeholder-300x300.png' : $product['product_image'];
        $product_excerpt = $product['product_excerpt'];
        $rating = $product['rating'];
        $product_category = $product['product_category'];
        $cata_output = "";
        foreach (is_array($product_category) ? $product_category : [] as $index => $category) {
            $cata_output .= '<a href="' . home_url($permalinks['category_base'] . '/' . $category['slug']) . '">' . htmlspecialchars($category['name']) . '</a>';
            if ($index < count($product_category) - 1) {
                $cata_output .= ', ';
            }
        }
        $product_sku = $product['product_sku'];
        $product_stock = $product['product_stock'];
        $on_sale = $product['on_sale'];
        $add_to_cart_url = esc_url(add_query_arg('add-to-cart', $product['ID'], $product_link));
        if (isset($dapfforwc_options['use_custom_template']) && $dapfforwc_options['use_custom_template'] === "on" && in_array($currentpage_slug, $dapfforwc_options['use_custom_template_in_page'] ?? [])) {

            // Retrieve the custom template from the database
            $custom_template = $dapfforwc_options['custom_template_code'];

            // Replace placeholders with actual values
            $custom_template = str_replace('{{product_link}}', esc_url($product_link), $custom_template);
            $custom_template = str_replace('{{product_title}}', esc_html($product_title), $custom_template);
            $custom_template = str_replace('{{product_image}}', esc_url($product_image), $custom_template);
            $custom_template = str_replace('{{product_excerpt}}', apply_filters('the_excerpt', $product_excerpt), $custom_template);
            $custom_template = str_replace('{{product_price}}', wp_kses_post($product_price), $custom_template);
            $custom_template = str_replace('{{product_category}}', $cata_output, $custom_template);
            $custom_template = str_replace('{{product_sku}}', esc_html($product_sku), $custom_template);
            $custom_template = str_replace('{{product_stock}}', esc_html($product_stock), $custom_template);
            $custom_template = str_replace('{{add_to_cart_url}}', $add_to_cart_url, $custom_template);
            $custom_template = str_replace('{{product_id}}', esc_html($product['ID']), $custom_template);
            $allowed_tags = array(
                'a' => array(
                    'href' => array(),
                    'title' => array(),
                    'class' => array(),
                    'target' => array(), // Allow target attribute for links
                ),
                'strong' => array(),
                'em' => array(),
                'li' => array(
                    'class' => array(),
                ),
                'div' => array(
                    'class' => array(),
                    'id' => array(), // Allow id for divs
                ),
                'img' => array(
                    'src' => array(),
                    'alt' => array(),
                    'class' => array(),
                    'width' => array(), // Allow width attribute
                    'height' => array(), // Allow height attribute
                ),
                'h1' => array('class' => array()), // Allow h1
                'h2' => array('class' => array()),
                'h3' => array('class' => array()), // Allow h3
                'h4' => array('class' => array()), // Allow h4
                'h5' => array('class' => array()), // Allow h5
                'h6' => array('class' => array()), // Allow h6
                'span' => array('class' => array()),
                'p' => array('class' => array()),
                'br' => array(), // Allow line breaks
                'blockquote' => array(
                    'cite' => array(), // Allow cite attribute for blockquotes
                    'class' => array(),
                ),
                'table' => array(
                    'class' => array(),
                    'style' => array(), // Allow inline styles
                ),
                'tr' => array(
                    'class' => array(),
                ),
                'td' => array(
                    'class' => array(),
                    'colspan' => array(), // Allow colspan attribute
                    'rowspan' => array(), // Allow rowspan attribute
                ),
                'th' => array(
                    'class' => array(),
                    'colspan' => array(),
                    'rowspan' => array(),
                ),
                'ul' => array('class' => array()), // Allow unordered lists
                'ol' => array('class' => array()), // Allow ordered lists
                'script' => array(), // Be cautious with scripts
            );

            echo wp_kses(do_shortcode($custom_template), $allowed_tags);
        } else {
            echo '<li class="product type-product">
	<div class="astra-shop-thumbnail-wrap">
	<a href="' . esc_url($product_link) . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
    <img fetchpriority="high" decoding="async" width="300" height="300" src="' . esc_url($product_image) . '" class="woocommerce-placeholder wp-post-image" alt="Placeholder" srcset="' . esc_url($product_image) . ' 300w">
        </a>
        ' . ($on_sale ? '<span class="ast-on-card-button ast-onsale-card" data-notification="default">Sale!</span>' : '') . '
        <a href="?add-to-cart=' . esc_attr($product['ID']) . '" data-quantity="1" class="ast-on-card-button ast-select-options-trigger product_type_simple add_to_cart_button ajax_add_to_cart" data-product_id="' . esc_attr($product['ID']) . '" data-product_sku="" aria-label="Add to cart: “' . esc_attr($product_title) . '”" rel="nofollow"> <span class="ast-card-action-tooltip"> Add to cart </span> <span class="ahfb-svg-iconset"> <span class="ast-icon icon-bag"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="ast-bag-icon-svg" x="0px" y="0px" width="100" height="100" viewBox="826 826 140 140" enable-background="new 826 826 140 140" xml:space="preserve">
                    <path d="M960.758,934.509l2.632,23.541c0.15,1.403-0.25,2.657-1.203,3.761c-0.953,1.053-2.156,1.579-3.61,1.579H833.424  c-1.454,0-2.657-0.526-3.61-1.579c-0.952-1.104-1.354-2.357-1.203-3.761l2.632-23.541H960.758z M953.763,871.405l6.468,58.29H831.77  l6.468-58.29c0.15-1.203,0.677-2.218,1.58-3.045c0.903-0.827,1.981-1.241,3.234-1.241h19.254v9.627c0,2.658,0.94,4.927,2.82,6.807  s4.149,2.82,6.807,2.82c2.658,0,4.926-0.94,6.807-2.82s2.821-4.149,2.821-6.807v-9.627h28.882v9.627  c0,2.658,0.939,4.927,2.819,6.807c1.881,1.88,4.149,2.82,6.807,2.82s4.927-0.94,6.808-2.82c1.879-1.88,2.82-4.149,2.82-6.807v-9.627  h19.253c1.255,0,2.332,0.414,3.235,1.241C953.086,869.187,953.612,870.202,953.763,871.405z M924.881,857.492v19.254  c0,1.304-0.476,2.432-1.429,3.385s-2.08,1.429-3.385,1.429c-1.303,0-2.432-0.477-3.384-1.429c-0.953-0.953-1.43-2.081-1.43-3.385  v-19.254c0-5.315-1.881-9.853-5.641-13.613c-3.76-3.761-8.298-5.641-13.613-5.641s-9.853,1.88-13.613,5.641  c-3.761,3.76-5.641,8.298-5.641,13.613v19.254c0,1.304-0.476,2.432-1.429,3.385c-0.953,0.953-2.081,1.429-3.385,1.429  c-1.303,0-2.432-0.477-3.384-1.429c-0.953-0.953-1.429-2.081-1.429-3.385v-19.254c0-7.973,2.821-14.779,8.461-20.42  c5.641-5.641,12.448-8.461,20.42-8.461c7.973,0,14.779,2.82,20.42,8.461C922.062,842.712,924.881,849.519,924.881,857.492z"></path>
                    </svg></span> </span> </a></div><div class="astra-shop-summary-wrap">			<span class="ast-woo-product-category">
                    ' . wp_kses_post($cata_output) . '			</span>
                <a href="' . esc_url($product_link) . '" class="ast-loop-product__link"><h2 class="woocommerce-loop-product__title">' . esc_html($product_title) . '</h2></a>
            <div class="review-rating"><div class="star-rating"><span style="width:'.(esc_attr($rating) * 20).'%">Rated <strong class="rating">'.esc_html($rating).'</strong> out of 5</span></div></div>
    <span class="price"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>' . esc_html($product_price) . '</bdi></span></span>
<a href="?add-to-cart=' . esc_attr($product['ID']) . '" aria-describedby="woocommerce_loop_add_to_cart_link_describedby_' . esc_attr($product['ID']) . '" data-quantity="1" class="button product_type_simple add_to_cart_button ajax_add_to_cart" data-product_id="' . esc_attr($product['ID']) . '" data-product_sku="" aria-label="Add to cart: “' . esc_html($product_title) . '”" rel="nofollow" data-success_message="“' . esc_html($product_title) . '” has been added to your cart">Add to cart</a>	<span id="woocommerce_loop_add_to_cart_link_describedby_' . esc_attr($product['ID']) . '" class="screen-reader-text">
			</span>
</div></li>';
        }
    }
    // Function to generate pagination
    private function pagination($paged, $total_pages)
    {
        $big = 999999999; // an unlikely integer
        $paginationLinks = paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $total_pages,
            'prev_text' => __('« Prev', 'dynamic-ajax-product-filters-for-woocommerce'),
            'next_text' => __('Next »', 'dynamic-ajax-product-filters-for-woocommerce'),
            'type' => 'array', // This returns an array of pagination links
        ));

        if ($paginationLinks) {
            // Start building the pagination HTML
            $paginationHtml = '';
            foreach ($paginationLinks as $link) {
                // Wrap each link in an <a> tag
                $paginationHtml .= '<li>' . $link . '</li>';
            }
            return $paginationHtml; // Return the constructed HTML
        }
        return '';
    }
}
