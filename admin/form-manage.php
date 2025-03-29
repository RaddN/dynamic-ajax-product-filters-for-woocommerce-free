<?php
if (!defined('ABSPATH')) {
    exit;
}

function dapfforwc_render_checkbox($key)
{
    global $dapfforwc_options;
?>
    <label class="switch <?php echo esc_attr($key); ?>">
        <input type='checkbox' name='dapfforwc_options[<?php echo esc_attr($key); ?>]' <?php checked(isset($dapfforwc_options[$key]) && $dapfforwc_options[$key] === "on"); ?>>
        <span class="slider round"></span>
    </label>
    <?php
    if ($key === "use_filters_word_in_permalinks") {
        echo "<p>if you want to use permalinks filter in your front page & archive page turn it on.</p>";
    } elseif ($key === "show_loader") {
        echo "<p><a href='#' id='customize_loader'>customize</a> loading effect</p>";
    }
}

function dapfforwc_show_categories_render()
{
    dapfforwc_render_checkbox('show_categories');
}
function dapfforwc_show_attributes_render()
{
    dapfforwc_render_checkbox('show_attributes');
}
function dapfforwc_show_tags_render()
{
    dapfforwc_render_checkbox('show_tags');
}
function dapfforwc_show_price_range_render()
{
    dapfforwc_render_checkbox('show_price_range');
}
function dapfforwc_show_rating_render()
{
    dapfforwc_render_checkbox('show_rating');
}
function dapfforwc_show_search_render()
{
    dapfforwc_render_checkbox('show_search');
}
function dapfforwc_use_filters_word_in_permalinks_render()
{
    dapfforwc_render_checkbox('use_filters_word_in_permalinks');
}
function dapfforwc_update_filter_options_render()
{
    dapfforwc_render_checkbox('update_filter_options');
}
function dapfforwc_show_loader_render()
{
    dapfforwc_render_checkbox('show_loader');
}
function dapfforwc_use_custom_template_render()
{
    dapfforwc_render_checkbox('use_custom_template');
}




function dapfforwc_custom_template_code_render()
{
    global $dapfforwc_options;
    echo '    
    <div class="custom_template_code" >';
    ?>
    <!-- Placeholder List -->
    <div id="placeholder-list" style="margin-bottom: 10px;">
        <?php
        $placeholders = [
            '{{product_link}}' => 'Product Link',
            '{{product_title}}' => 'Product Title',
            '{{product_image}}' => 'Product Image',
            '{{product_price}}' => 'Product Price',
            '{{product_excerpt}}' => 'Product Excerpt',
            '{{product_category}}' => 'Product Category',
            '{{product_sku}}' => 'Product SKU',
            '{{product_stock}}' => 'Product Stock',
            '{{add_to_cart_url}}' => 'Add to Cart URL',
            '{{product_id}}' => 'Product ID'
        ];
        foreach ($placeholders as $placeholder => $label) {
            echo "<span class='placeholder' onclick=\"insertPlaceholder('" . esc_html($placeholder) . "')\">" . esc_html($placeholder) . "</span>";
        }
        ?>
    </div>
    <textarea style="display:none;" id="custom_template_input" name="dapfforwc_options[custom_template_code]" rows="10" cols="50" class="large-text"><?php if (isset($dapfforwc_options['custom_template_code'])) {
                                                                                                                                                            echo esc_textarea($dapfforwc_options['custom_template_code']);
                                                                                                                                                        } ?></textarea>
    <div id="code-editor"></div>
    <p class="description"><?php esc_html_e('Enter your custom template code here.', 'dynamic-ajax-product-filters-for-woocommerce'); ?></p>
    </div>


<?php
}

function dapfforwc_use_url_filter_render()
{
    global $dapfforwc_options;
?>
    <fieldset>
        <legend><?php esc_html_e('Select URL Filter Type', 'dynamic-ajax-product-filters-for-woocommerce'); ?></legend>
        <?php
        $types = [
            'query_string' => __('With Query String (e.g., ?filters)', 'dynamic-ajax-product-filters-for-woocommerce'),
            'pro_only' => __('With Permalinks (e.g., brand/size/color)', 'dynamic-ajax-product-filters-for-woocommerce'),
            'ajax' => __('With Ajax', 'dynamic-ajax-product-filters-for-woocommerce'),
        ];
        foreach ($types as $value => $label) {
            // Check if the current value is "pro_only"
            $disabled = $value === 'pro_only' ? 'disabled' : '';
            $label_class = $value === 'pro_only' ? 'pro-only' : '';

            echo '<label class="' . esc_attr($label_class) . '">
                    <input type="radio" name="dapfforwc_options[use_url_filter]" value="' . esc_attr($value) . '" ' . checked(isset($dapfforwc_options['use_url_filter'])?$dapfforwc_options['use_url_filter']:"", $value, false) . ' ' . esc_attr($disabled) . '> 
                    ' . esc_html($label) . '
                  </label><br>';
        }
        ?>
    </fieldset>
<?php
}
