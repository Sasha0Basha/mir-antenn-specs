<?php
/**
 * Plugin Name: МИР АНТЕНН - Характеристики товаров
 * Plugin URI: https://mir-antenn.ru
 * Description: Добавляет красивую таблицу технических характеристик под ценой товара WooCommerce с поддержкой вариаций
 * Version: 2.0.1
 * Author: МИР АНТЕНН
 * Author URI: https://mir-antenn.ru
 * Text Domain: mir-antenn-specs
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Запрет прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Проверка наличия WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * ПОДКЛЮЧЕНИЕ ОБНОВЛЕНИЙ С GITHUB
 */
if (file_exists(__DIR__ . '/vendor/plugin-update-checker.php')) {
    require_once __DIR__ . '/vendor/plugin-update-checker.php';
    use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/sasha0basha/mir-antenn-specs/', // ЗАМЕНИТЕ ВАШ_ЛОГИН на ваш ник GitHub
        __FILE__,
        'mir-antenn-specs'
    );

    // Указываем ветку
    $myUpdateChecker->setBranch('main');
}

/**
 * ОСНОВНОЙ КЛАСС ПЛАГИНА
 */
class MirAntenn_Product_Specs {
    
    public function __construct() {
        // Добавляем метабоксы в админку
        add_action('add_meta_boxes', array($this, 'add_specs_metabox'));
        
        // Сохраняем данные простого товара
        add_action('save_post', array($this, 'save_specs_data'));
        
        // Добавляем поля для вариаций
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_specs'), 10, 3);
        
        // Сохраняем данные вариаций
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_specs'), 10, 2);
        
        // Выводим характеристики на фронте
        add_action('woocommerce_single_product_summary', array($this, 'display_specs_table'), 25);
        
        // Подключаем стили
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Подключаем скрипты админки
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX для обновления характеристик при смене вариации
        add_action('wp_ajax_get_variation_specs', array($this, 'ajax_get_variation_specs'));
        add_action('wp_ajax_nopriv_get_variation_specs', array($this, 'ajax_get_variation_specs'));
        
        // AJAX для импорта характеристик
        add_action('wp_ajax_import_specs', array($this, 'ajax_import_specs'));
    }
    
    public function add_specs_metabox() {
        add_meta_box(
            'mir_antenn_specs',
            '⚙️ Технические характеристики (Глобальные)',
            array($this, 'render_specs_metabox'),
            'product',
            'normal',
            'high'
        );
    }
    
    public function render_specs_metabox($post) {
        wp_nonce_field('mir_antenn_specs_nonce', 'mir_antenn_specs_nonce_field');
        $specs = get_post_meta($post->ID, '_mir_antenn_specs', true);
        if (!is_array($specs)) { $specs = array(); }
        ?>
        <div id="mir-antenn-specs-wrapper">
            <div class="specs-instructions">
                <p><strong>📋 Глобальные характеристики</strong></p>
                <p>Эти характеристики будут показаны для <strong>всех вариаций товара</strong>.</p>
            </div>
            <div class="specs-tools">
                <button type="button" class="button" id="export-specs">📤 Экспорт характеристик</button>
                <button type="button" class="button" id="import-specs">📥 Импорт характеристик</button>
            </div>
            <div id="specs-container">
                <?php
                if (empty($specs)) {
                    $this->render_spec_row(0, array('label' => '', 'value' => ''));
                } else {
                    foreach ($specs as $index => $spec) {
                        $this->render_spec_row($index, $spec);
                    }
                }
                ?>
            </div>
            <button type="button" class="button button-primary" id="add-spec-row">➕ Добавить характеристику</button>
            
            <div id="import-modal" style="display:none;">
                <div class="import-modal-content">
                    <h3>📥 Импорт характеристик</h3>
                    <textarea id="import-data" rows="10" style="width:100%; font-family: monospace;"></textarea>
                    <div style="margin-top: 15px;">
                        <button type="button" class="button button-primary" id="do-import">Импортировать</button>
                        <button type="button" class="button" id="cancel-import">Отмена</button>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .spec-row { display: flex; gap: 15px; margin-bottom: 10px; background: #f9fafb; padding: 10px; border-radius: 5px; }
            .spec-field { flex: 1; }
            .spec-field label { display: block; font-weight: bold; margin-bottom: 3px; }
            .spec-field input { width: 100%; }
            #import-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center; }
            .import-modal-content { background: white; padding: 20px; border-radius: 8px; width: 500px; }
            .variation-specs-wrapper { background: #fef3c7; padding: 10px; border: 1px solid #fbbf24; margin-top: 10px; }
        </style>
        <?php
    }

    private function render_spec_row($index, $spec) {
        $label = isset($spec['label']) ? esc_attr($spec['label']) : '';
        $value = isset($spec['value']) ? esc_attr($spec['value']) : '';
        ?>
        <div class="spec-row">
            <div class="spec-field">
                <label>Название</label>
                <input type="text" name="mir_antenn_specs[<?php echo $index; ?>][label]" value="<?php echo $label; ?>" />
            </div>
            <div class="spec-field">
                <label>Значение</label>
                <input type="text" name="mir_antenn_specs[<?php echo $index; ?>][value]" value="<?php echo $value; ?>" />
            </div>
            <button type="button" class="remove-spec-row">🗑️</button>
        </div>
        <?php
    }

    public function add_variation_specs($loop, $variation_data, $variation) {
        $variation_specs = get_post_meta($variation->ID, '_variation_specs', true);
        if (!is_array($variation_specs)) { $variation_specs = array(); }
        ?>
        <div class="variation-specs-wrapper">
            <h4>⚙️ Характеристики вариации</h4>
            <div class="variation-specs-container" data-variation="<?php echo $variation->ID; ?>">
                <?php
                if (empty($variation_specs)) {
                    $this->render_variation_spec_row($variation->ID, 0, array('label' => '', 'value' => ''));
                } else {
                    foreach ($variation_specs as $index => $spec) {
                        $this->render_variation_spec_row($variation->ID, $index, $spec);
                    }
                }
                ?>
            </div>
            <button type="button" class="add-variation-spec" data-variation="<?php echo $variation->ID; ?>">➕ Добавить</button>
        </div>
        <?php
    }

    private function render_variation_spec_row($variation_id, $index, $spec) {
        $label = isset($spec['label']) ? esc_attr($spec['label']) : '';
        $value = isset($spec['value']) ? esc_attr($spec['value']) : '';
        ?>
        <div class="variation-spec-row" style="display:flex; gap:5px; margin-bottom:5px;">
            <input type="text" name="variation_specs[<?php echo $variation_id; ?>][<?php echo $index; ?>][label]" value="<?php echo $label; ?>" placeholder="Название" />
            <input type="text" name="variation_specs[<?php echo $variation_id; ?>][<?php echo $index; ?>][value]" value="<?php echo $value; ?>" placeholder="Значение" />
            <button type="button" class="remove-variation-spec">🗑️</button>
        </div>
        <?php
    }

    public function save_specs_data($post_id) {
        if (!isset($_POST['mir_antenn_specs_nonce_field']) || !wp_verify_nonce($_POST['mir_antenn_specs_nonce_field'], 'mir_antenn_specs_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['mir_antenn_specs'])) {
            $specs = array();
            foreach ($_POST['mir_antenn_specs'] as $spec) {
                if (!empty($spec['label'])) {
                    $specs[] = array('label' => sanitize_text_field($spec['label']), 'value' => sanitize_text_field($spec['value']));
                }
            }
            update_post_meta($post_id, '_mir_antenn_specs', $specs);
        }
    }

    public function save_variation_specs($variation_id, $i) {
        if (isset($_POST['variation_specs'][$variation_id])) {
            $specs = array();
            foreach ($_POST['variation_specs'][$variation_id] as $spec) {
                if (!empty($spec['label'])) {
                    $specs[] = array('label' => sanitize_text_field($spec['label']), 'value' => sanitize_text_field($spec['value']));
                }
            }
            update_post_meta($variation_id, '_variation_specs', $specs);
        }
    }

    public function display_specs_table() {
        global $product;
        if (!$product) return;
        $specs = get_post_meta($product->get_id(), '_mir_antenn_specs', true);
        if (empty($specs)) return;
        ?>
        <div class="mir-antenn-specs-table-wrapper" data-product-id="<?php echo $product->get_id(); ?>">
            <table class="mir-antenn-specs-table">
                <tbody>
                    <?php foreach ($specs as $spec): ?>
                        <tr>
                            <td class="spec-label"><?php echo esc_html($spec['label']); ?></td>
                            <td class="spec-value"><?php echo esc_html($spec['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function enqueue_styles() {
        if (is_product()) {
            wp_enqueue_style('mir-antenn-specs-style', false);
            wp_add_inline_style('mir-antenn-specs-style', ".mir-antenn-specs-table { width: 100%; border-collapse: collapse; margin-top: 20px; } .mir-antenn-specs-table td { padding: 10px; border-bottom: 1px solid #eee; } .spec-label { font-weight: bold; color: #555; }");
        }
    }

    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script('mir-antenn-admin', false, array('jquery'));
            wp_localize_script('mir-antenn-admin', 'mirAntennAdminAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mir_antenn_ajax')
            ));
        }
    }

    public function ajax_get_variation_specs() {
        check_ajax_referer('mir_antenn_ajax', 'nonce');
        $v_id = intval($_POST['variation_id']);
        $p_id = intval($_POST['product_id']);
        $g_specs = get_post_meta($p_id, '_mir_antenn_specs', true) ?: array();
        $v_specs = get_post_meta($v_id, '_variation_specs', true) ?: array();
        $all = array_merge($g_specs, $v_specs);
        
        ob_start();
        foreach ($all as $s) {
            echo "<tr><td class='spec-label'>{$s['label']}</td><td>{$s['value']}</td></tr>";
        }
        $html = ob_get_clean();
        wp_send_json_success(array('html' => "<table>$html</table>"));
    }

    public function ajax_import_specs() {
        check_ajax_referer('mir_antenn_ajax', 'nonce');
        $data = json_decode(stripslashes($_POST['import_data']), true);
        if ($data) wp_send_json_success(array('specs' => $data));
        else wp_send_json_error('Ошибка JSON');
    }
}

new MirAntenn_Product_Specs();
