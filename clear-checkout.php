<?php
/**
 * Plugin Name: Clear Checkout
 * Description: Clear Checkout For WooCommerce.
 * Version: 1.0
 * Author: V.Krykun
 */

function cch_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>Clear Checkout</strong> requires active WooCommerce!</p></div>';
        });
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'admin/admin-menu.php';
}
add_action('plugins_loaded', 'cch_check_woocommerce');

function cch_get_checkout_fields() {
    return [
        'billing' => [
            'label' => 'Billing information',
            'fields' => [
                'billing_first_name' => 'First name',
                'billing_last_name' => 'Last name',
                'billing_company' => 'Company',
                'billing_country' => 'Country',
                'billing_address_1' => 'Address 1',
                'billing_address_2' => 'Address 2',
                'billing_city' => 'City',
                'billing_state' => 'State',
                'billing_postcode' => 'Postal code',
                'billing_phone' => 'Phone number',
                'billing_email' => 'Email'
            ],
        ],
        'shipping' => [
            'label' => 'Payment information',
            'fields' => [
                'shipping_first_name' => 'First name',
                'shipping_last_name' => 'Last name',
                'shipping_company' => 'Company',
                'shipping_country' => 'Country',
                'shipping_address_1' => 'Address 1',
                'shipping_address_2' => 'Address 2',
                'shipping_city' => 'City',
                'shipping_state' => 'State',
                'shipping_postcode' => 'Postal code'
            ],
        ],
        'order' => [
            'label' => 'Additional information',
            'fields' => [
                'order_comments' => 'Notes to the order'
            ],
        ],
    ];
}

function cch_render_checkout_form() {
    $sections = cch_get_checkout_fields();
    ob_start();
    $cart = WC()->cart->get_cart();
    ?>
    <table class="shop_table cart">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($cart)): ?>
                <?php foreach ($cart as $cart_item_key => $cart_item): 
                    $_product = $cart_item['data'];
                    if (!$_product || !$_product->exists()) {
                        continue;
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($_product->get_name()); ?></td>
                        <td><?php echo esc_html($cart_item['quantity']); ?></td>
                        <td><?php echo wc_price($_product->get_price() * $cart_item['quantity']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="2"><strong>Total:</strong></td>
                    <td><?php echo WC()->cart->get_cart_total(); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="3">Your cart is empty.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <form action="<?php echo esc_url(wc_get_checkout_url()); ?>" method="post" class="cch-form">
        <input type="hidden" name="cch_nonce" value="<?php echo wp_create_nonce('cch_checkout'); ?>">

        <?php foreach ($sections as $section_key => $section): ?>
            <?php if (get_option("cch_enable_{$section_key}", 'yes') === 'yes'): ?>
                <fieldset>
                    <legend><?php echo esc_html($section['label']); ?></legend>
                    <?php foreach ($section['fields'] as $field_key => $field_label): ?>
                        <?php if (get_option("cch_enable_{$field_key}", 'yes') === 'yes'): ?>
                            <p>
                                <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_label); ?></label>
                                <input type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" required>
                            </p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit">Place an order</button>
    </form>
    <script>
        jQuery(document).on('submit', 'form', function(e) {
    e.preventDefault();
    var formData = jQuery(this).serialize();

    jQuery.post({
        url: '/wp-admin/admin-ajax.php',
        data: formData + '&action=cch_custom_checkout',
        success: function(response) {
            console.log(response);
            if (response.success) {
                alert(response.data.message);
                window.location.href = '/thank-you-page/';
            } else {
                alert(response.data.message);
            }
        }
    });
});

    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('clear_checkout_form', 'cch_render_checkout_form');

add_filter('woocommerce_checkout_fields', 'cch_modify_checkout_fields');

function cch_modify_checkout_fields($fields) {
    $sections = cch_get_checkout_fields();

    foreach ($sections as $section_key => $section) {
        foreach ($section['fields'] as $field_key => $field_label) {
            if (get_option("cch_enable_{$field_key}", 'yes') !== 'yes') {
                unset($fields[$section_key][$field_key]);
            }
        }
    }

    return $fields;
}

add_action('init', function() {
    add_action('wp_ajax_cch_custom_checkout', 'cch_process_checkout');
    add_action('wp_ajax_nopriv_cch_custom_checkout', 'cch_process_checkout');
});

function cch_process_checkout() {
    error_log('cch_process_checkout was called');

    if (!isset($_POST['cch_nonce']) || !wp_verify_nonce($_POST['cch_nonce'], 'cch_checkout')) {
        wp_send_json_error(['message' => 'Invalid request']);
    }

    $fields = cch_get_checkout_fields();

    $order_data = [
        'payment_method'       => $_POST['payment_method'] ?? '',
        'payment_method_title' => $_POST['payment_method'] ?? '',
        'set_paid'             => false,
        'billing'              => [],
        'shipping'             => [],
    ];
    
    $order = wc_create_order();

    foreach ($fields as $section => $data) {
        foreach ($data['fields'] as $key => $label) {
            $value = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
            if (strpos($key, 'billing_') === 0) {
                $order->set_billing_address([str_replace('billing_', '', $key) => $value]);
            } elseif (strpos($key, 'shipping_') === 0) {
                $order->set_shipping_address([str_replace('shipping_', '', $key) => $value]);
            } else {
                $order->update_meta_data($key, $value);
            }
        }
    }

    if (is_wp_error($order) || !$order) {
        error_log('Failed to create order: ' . print_r($order, true));
        wp_send_json_error(['message' => 'The order could not be created.']);
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $order->add_product($cart_item['data'], $cart_item['quantity']);
    }

    foreach ($order_data as $type => $values) {
        foreach ($values as $key => $value) {
            $order->set_address([$key => $value], $type);
        }
    }

    $order->calculate_totals();
    WC()->cart->empty_cart();

    wp_send_json_success(['message' => 'The order was successfully created!', 'order_id' => $order->get_id()]);
}
