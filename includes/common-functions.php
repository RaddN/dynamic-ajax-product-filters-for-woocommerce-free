<?php

if (!defined('ABSPATH')) {
    exit;
}

function dapfforwc_get_min_max_price($products, $products_id = []) {
    $loop = $products_id;
    $min_price = null;
    $max_price = null;
    foreach ($loop as $product_id) {
        foreach ($products as $product) {
            if ($product['ID'] == $product_id) {
                $price = $product['price'];

                if (is_null($min_price) || $price < $min_price) {
                    $min_price = $price;
                }

                if (is_null($max_price) || $price > $max_price) {
                    $max_price = $price;
                }
                break;
            }
        }
    }

    return array('min' => $min_price, 'max' => $max_price);
}