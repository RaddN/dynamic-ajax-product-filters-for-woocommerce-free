<?php

if (!defined('ABSPATH')) {
    exit;
}
add_action('admin_head', 'dapfforwc_add_review_popup');

function dapfforwc_add_review_popup() {
    $current_page = isset($_GET['page']) ? $_GET['page'] : '';

    if ($current_page === 'dapfforwc-admin') {
        ?>
        <div id="review-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; border:1px solid #ccc; padding:20px; z-index:1000;">
            <h2>We Value Your Feedback!</h2>
            <p>If you enjoy using <b>Dynamic AJAX Product Filters for WooCommerce</b>, please take a moment to leave us a review.</p>
            <a href="https://wordpress.org/support/plugin/dynamic-ajax-product-filters-for-woocommerce/reviews/" target="_blank" style="display:inline-block; padding:10px 15px; background:#0073aa; color:#fff; text-decoration:none; border-radius:5px;">Leave a Review</a>
            <button id="close-popup" style="margin-top:10px; background:#f00; color:#fff; border:none; padding:10px; cursor:pointer;">Remind Me Later</button>
            <button id="already-done" style="margin-top:10px; background:#ccc; color:#000; border:none; padding:10px; cursor:pointer;">Already Done</button>
        </div>

        <div id="overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0, 0, 0, 0.5); z-index:999;"></div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const popupClosedKey = 'reviewPopupClosed';
                const alreadyDoneKey = 'reviewAlreadyDone';
                const daysToMilliseconds = 3 * 24 * 60 * 60 * 1000; // 3 days in milliseconds

                function shouldShowPopup() {
                    const lastClosed = localStorage.getItem(popupClosedKey);
                    const alreadyDone = localStorage.getItem(alreadyDoneKey);
                    if (alreadyDone) {
                        return false; // Don't show if user already indicated they are done
                    }
                    if (lastClosed) {
                        const currentTime = Date.now();
                        return (currentTime - lastClosed) > daysToMilliseconds;
                    }
                    return true; // Show if never closed or marked as done
                }

                setTimeout(function() {
                    if (shouldShowPopup()) {
                        document.getElementById('overlay').style.display = 'block';
                        document.getElementById('review-popup').style.display = 'block';
                    }
                }, 5000); // Show after 5 seconds

                document.getElementById('close-popup').addEventListener('click', function() {
                    document.getElementById('overlay').style.display = 'none';
                    document.getElementById('review-popup').style.display = 'none';
                    localStorage.setItem(popupClosedKey, Date.now());
                });

                document.getElementById('already-done').addEventListener('click', function() {
                    document.getElementById('overlay').style.display = 'none';
                    document.getElementById('review-popup').style.display = 'none';
                    localStorage.setItem(alreadyDoneKey, true);
                });
            });
        </script>
        <?php
    } else {
        add_action('admin_notices', 'dapfforwc_show_admin_notice');
    }
}

function dapfforwc_show_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible" id="admin-review-notice">
        <p>If you enjoy using <b>Dynamic AJAX Product Filters for WooCommerce</b>, please take a moment to leave us a review.</p>
        <p><a href="https://wordpress.org/support/plugin/dynamic-ajax-product-filters-for-woocommerce/reviews/" target="_blank" class="button-primary">Leave a Review</a>
        <button id="close-notice" class="button-secondary">Remind Me Later</button>
        <button id="already-done-notice" class="button-secondary">Already Done</button>
        </p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alreadyDoneKey = 'reviewAlreadyDone';
            const popupClosedKey = 'reviewPopupClosed';
            const daysToMilliseconds = 3 * 24 * 60 * 60 * 1000; // 3 days in milliseconds

            function shouldShowNotice() {
                const lastClosed = localStorage.getItem(popupClosedKey);
                const alreadyDone = localStorage.getItem(alreadyDoneKey);
                if (alreadyDone) {
                    return false; // Don't show if user already indicated they are done
                }
                if (lastClosed) {
                    const currentTime = Date.now();
                    return (currentTime - lastClosed) > daysToMilliseconds;
                }
                return true; // Show if never closed or marked as done
            }

            if (!shouldShowNotice()) {
                document.getElementById('admin-review-notice').style.display = 'none';
            }

            document.getElementById('close-notice').addEventListener('click', function() {
                document.getElementById('admin-review-notice').style.display = 'none';
                localStorage.setItem(popupClosedKey, Date.now());
            });

            document.getElementById('already-done-notice').addEventListener('click', function() {
                document.getElementById('admin-review-notice').style.display = 'none';
                localStorage.setItem(alreadyDoneKey, true);
            });
        });
    </script>
    <?php
}