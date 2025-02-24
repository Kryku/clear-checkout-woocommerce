<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'cch_create_admin_menu');

function cch_create_admin_menu() {
    add_menu_page(
        'Clear Checkout Plugin',
        'Clear Checkout',
        'manage_options',
        'cch-settings',
        'cch_settings_page',
        'dashicons-cart',
        56
    );
}

function cch_settings_page() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    ?>
    <div class="wrap">
        <h1>Clear Checkout</h1>

        <nav class="nav-tab-wrapper">
            <a href="?page=cch-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
            <!--<a href="?page=cch-settings&tab=design" class="nav-tab <?php //echo $active_tab == 'design' ? 'nav-tab-active' : ''; ?>">Design</a>-->
        </nav>

        <form method="post" action="options.php">
            <?php
            if ($active_tab == 'general') {
                settings_fields('cch_general_options');
                do_settings_sections('cch_general');
            }/* elseif ($active_tab == 'design') {
                settings_fields('cch_design_options');
                do_settings_sections('cch_design');
            }*/
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cch_register_settings');

function cch_register_settings() {
    $sections = cch_get_checkout_fields();
    
    foreach ($sections as $section_key => $section) {
        register_setting('cch_general_options', "cch_enable_{$section_key}");
        add_settings_section("cch_{$section_key}_section", $section['label'], null, 'cch_general');

        add_settings_field(
            "cch_enable_{$section_key}",
            "Show {$section['label']}?",
            function () use ($section_key) {
                $checked = get_option("cch_enable_{$section_key}", 'yes') === 'yes' ? 'checked' : '';
                echo "<input type='checkbox' name='cch_enable_{$section_key}' value='yes' $checked> Show";
            },
            'cch_general',
            "cch_{$section_key}_section"
        );

        foreach ($section['fields'] as $field_key => $field_label) {
            register_setting('cch_general_options', "cch_enable_{$field_key}");
            
            add_settings_field(
                "cch_enable_{$field_key}",
                $field_label,
                function () use ($field_key) {
                    $checked = get_option("cch_enable_{$field_key}", 'yes') === 'yes' ? 'checked' : '';
                    echo "<input type='checkbox' name='cch_enable_{$field_key}' value='yes' $checked> Show";
                },
                'cch_general',
                "cch_{$section_key}_section"
            );
        }
    }
}

function cch_use_default_class_callback() {
    $checked = get_option('cch_use_default_class', 'yes') === 'yes' ? 'checked' : '';
    echo "<input type='checkbox' name='cch_use_default_class' value='yes' $checked> Use standard class '.single_add_to_cart_button'";
}
?>