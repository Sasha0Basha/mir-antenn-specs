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
    
    /**
     * Добавляем метабокс в админку товара
     */
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
    
    /**
     * Рендерим метабокс
     */
    public function render_specs_metabox($post) {
        wp_nonce_field('mir_antenn_specs_nonce', 'mir_antenn_specs_nonce_field');
        
        // Получаем сохраненные данные
        $specs = get_post_meta($post->ID, '_mir_antenn_specs', true);
        if (!is_array($specs)) {
            $specs = array();
        }
        
        ?>
        <div id="mir-antenn-specs-wrapper">
            <div class="specs-instructions">
                <p><strong>📋 Глобальные характеристики</strong></p>
                <p>Эти характеристики будут показаны для <strong>всех вариаций товара</strong> (если товар вариативный).</p>
                <p>Для вариативных товаров: добавьте здесь общие характеристики (материал, размеры), а уникальные характеристики каждой вариации (усиление, вес) добавьте в настройках конкретной вариации ниже.</p>
            </div>
            
            <div class="specs-tools">
                <button type="button" class="button" id="export-specs">
                    📤 Экспорт характеристик
                </button>
                <button type="button" class="button" id="import-specs">
                    📥 Импорт характеристик
                </button>
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
            
            <button type="button" class="button button-primary" id="add-spec-row">
                ➕ Добавить характеристику
            </button>
            
            <!-- Модальное окно для импорта -->
            <div id="import-modal" style="display:none;">
                <div class="import-modal-content">
                    <h3>📥 Импорт характеристик</h3>
                    <p>Вставьте JSON данные характеристик из другого товара:</p>
                    <textarea id="import-data" rows="10" style="width:100%; font-family: monospace; padding: 10px;"></textarea>
                    <div style="margin-top: 15px;">
                        <button type="button" class="button button-primary" id="do-import">Импортировать</button>
                        <button type="button" class="button" id="cancel-import">Отмена</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            #mir-antenn-specs-wrapper {
                padding: 15px;
            }
            .specs-instructions {
                background: #f0f9ff;
                border-left: 4px solid #0ea5e9;
                padding: 12px 15px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .specs-instructions p {
                margin: 5px 0;
                color: #0c4a6e;
            }
            .specs-instructions p:first-child {
                font-weight: 700;
                font-size: 15px;
            }
            .specs-tools {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
            }
            .specs-tools button {
                font-weight: 600;
            }
            .spec-row {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
                padding: 15px;
                background: #f9fafb;
                border-radius: 8px;
                border: 1px solid #e5e7eb;
            }
            .spec-row:hover {
                background: #f3f4f6;
            }
            .spec-field {
                flex: 1;
            }
            .spec-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #374151;
            }
            .spec-field input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
            }
            .spec-field input:focus {
                outline: none;
                border-color: #0ea5e9;
                box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            }
            .spec-actions {
                display: flex;
                align-items: flex-end;
                padding-bottom: 8px;
            }
            .remove-spec-row {
                background: #ef4444;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                transition: background 0.2s;
            }
            .remove-spec-row:hover {
                background: #dc2626;
            }
            #add-spec-row {
                margin-top: 10px;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 600;
            }
            
            /* Модальное окно */
            #import-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .import-modal-content {
                background: white;
                padding: 25px;
                border-radius: 8px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            }
            .import-modal-content h3 {
                margin: 0 0 15px 0;
                color: #0f172a;
            }
            
            /* Стили для вариаций */
            .variation-specs-wrapper {
                margin-top: 15px;
                padding: 15px;
                background: #fef3c7;
                border: 2px solid #fbbf24;
                border-radius: 8px;
            }
            .variation-specs-wrapper h4 {
                margin: 0 0 10px 0;
                color: #92400e;
                font-size: 14px;
            }
            .variation-spec-row {
                display: flex;
                gap: 10px;
                margin-bottom: 10px;
            }
            .variation-spec-row input {
                flex: 1;
                padding: 6px 10px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
            }
            .variation-spec-row button {
                padding: 6px 12px;
                background: #ef4444;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
            }
            .add-variation-spec {
                padding: 6px 12px;
                background: #0ea5e9;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                font-size: 12px;
            }
        </style>
        <?php
    }
    
    /**
     * Рендерим одну строку характеристики
     */
    private function render_spec_row($index, $spec) {
        $label = isset($spec['label']) ? esc_attr($spec['label']) : '';
        $value = isset($spec['value']) ? esc_attr($spec['value']) : '';
        ?>
        <div class="spec-row" data-index="<?php echo $index; ?>">
            <div class="spec-field">
                <label>Название характеристики</label>
                <input 
                    type="text" 
                    name="mir_antenn_specs[<?php echo $index; ?>][label]" 
                    value="<?php echo $label; ?>"
                    placeholder="Например: Материал"
                />
            </div>
            <div class="spec-field">
                <label>Значение</label>
                <input 
                    type="text" 
                    name="mir_antenn_specs[<?php echo $index; ?>][value]" 
                    value="<?php echo $value; ?>"
                    placeholder="Например: Алюминий/Сталь"
                />
            </div>
            <div class="spec-actions">
                <button type="button" class="remove-spec-row" title="Удалить">
                    🗑️
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Добавляем поля характеристик для каждой вариации
     */
    public function add_variation_specs($loop, $variation_data, $variation) {
        $variation_specs = get_post_meta($variation->ID, '_variation_specs', true);
        if (!is_array($variation_specs)) {
            $variation_specs = array();
        }
        
        ?>
        <div class="variation-specs-wrapper">
            <h4>⚙️ Дополнительные характеристики для этой вариации</h4>
            <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;">
                Эти характеристики будут добавлены к глобальным при выборе данной вариации
            </p>
            
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
            
            <button type="button" class="add-variation-spec" data-variation="<?php echo $variation->ID; ?>">
                ➕ Добавить характеристику для вариации
            </button>
        </div>
        <?php
    }
    
    /**
     * Рендерим строку характеристики для вариации
     */
    private function render_variation_spec_row($variation_id, $index, $spec) {
        $label = isset($spec['label']) ? esc_attr($spec['label']) : '';
        $value = isset($spec['value']) ? esc_attr($spec['value']) : '';
        ?>
        <div class="variation-spec-row">
            <input 
                type="text" 
                name="variation_specs[<?php echo $variation_id; ?>][<?php echo $index; ?>][label]" 
                value="<?php echo $label; ?>"
                placeholder="Название (например: Усиление)"
            />
            <input 
                type="text" 
                name="variation_specs[<?php echo $variation_id; ?>][<?php echo $index; ?>][value]" 
                value="<?php echo $value; ?>"
                placeholder="Значение (например: +21 дБ)"
            />
            <button type="button" class="remove-variation-spec">🗑️</button>
        </div>
        <?php
    }
    
    /**
     * Сохраняем данные глобальных характеристик
     */
    public function save_specs_data($post_id) {
        if (!isset($_POST['mir_antenn_specs_nonce_field'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['mir_antenn_specs_nonce_field'], 'mir_antenn_specs_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['mir_antenn_specs'])) {
            $specs = array();
            foreach ($_POST['mir_antenn_specs'] as $spec) {
                if (!empty($spec['label']) || !empty($spec['value'])) {
                    $specs[] = array(
                        'label' => sanitize_text_field($spec['label']),
                        'value' => sanitize_text_field($spec['value'])
                    );
                }
            }
            update_post_meta($post_id, '_mir_antenn_specs', $specs);
        } else {
            delete_post_meta($post_id, '_mir_antenn_specs');
        }
    }
    
    /**
     * Сохраняем характеристики вариаций
     */
    public function save_variation_specs($variation_id, $i) {
        if (isset($_POST['variation_specs'][$variation_id])) {
            $specs = array();
            foreach ($_POST['variation_specs'][$variation_id] as $spec) {
                if (!empty($spec['label']) || !empty($spec['value'])) {
                    $specs[] = array(
                        'label' => sanitize_text_field($spec['label']),
                        'value' => sanitize_text_field($spec['value'])
                    );
                }
            }
            update_post_meta($variation_id, '_variation_specs', $specs);
        }
    }
    
    /**
     * AJAX: получение характеристик вариации
     */
    public function ajax_get_variation_specs() {
        check_ajax_referer('mir_antenn_ajax', 'nonce');
        
        $variation_id = intval($_POST['variation_id']);
        $product_id = intval($_POST['product_id']);
        
        // Получаем глобальные характеристики
        $global_specs = get_post_meta($product_id, '_mir_antenn_specs', true);
        if (!is_array($global_specs)) {
            $global_specs = array();
        }
        
        // Получаем характеристики вариации
        $variation_specs = get_post_meta($variation_id, '_variation_specs', true);
        if (!is_array($variation_specs)) {
            $variation_specs = array();
        }
        
        // Объединяем
        $all_specs = array_merge($global_specs, $variation_specs);
        
        if (empty($all_specs)) {
            wp_send_json_error('No specs found');
        }
        
        // Генерируем HTML таблицы
        ob_start();
        ?>
        <table class="mir-antenn-specs-table">
            <tbody>
                <?php foreach ($all_specs as $spec): ?>
                    <?php if (!empty($spec['label']) || !empty($spec['value'])): ?>
                        <tr>
                            <td class="spec-label"><?php echo esc_html($spec['label']); ?></td>
                            <td class="spec-value"><?php echo wp_kses_post($spec['value']); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: импорт характеристик
     */
    public function ajax_import_specs() {
        check_ajax_referer('mir_antenn_ajax', 'nonce');
        
        $import_data = stripslashes($_POST['import_data']);
        $specs = json_decode($import_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Неверный формат JSON');
        }
        
        if (!is_array($specs)) {
            wp_send_json_error('Данные должны быть массивом');
        }
        
        wp_send_json_success(array('specs' => $specs));
    }
    
    /**
     * Выводим таблицу характеристик на фронте
     */
    public function display_specs_table() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Получаем глобальные характеристики
        $global_specs = get_post_meta($product_id, '_mir_antenn_specs', true);
        if (!is_array($global_specs)) {
            $global_specs = array();
        }
        
        // Для вариативных товаров показываем только глобальные сначала
        // Характеристики вариации будут подгружаться через AJAX
        if ($product->is_type('variable')) {
            $specs = $global_specs;
        } else {
            $specs = $global_specs;
        }
        
        if (empty($specs)) {
            return;
        }
        
        ?>
        <div class="mir-antenn-specs-table-wrapper" data-product-id="<?php echo $product_id; ?>">
            <table class="mir-antenn-specs-table">
                <tbody>
                    <?php foreach ($specs as $spec): ?>
                        <?php if (!empty($spec['label']) || !empty($spec['value'])): ?>
                            <tr>
                                <td class="spec-label">
                                    <?php echo esc_html($spec['label']); ?>
                                </td>
                                <td class="spec-value">
                                    <?php echo wp_kses_post($spec['value']); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Подключаем стили для фронта
     */
    public function enqueue_styles() {
        if (is_product()) {
            wp_register_style('mir-antenn-specs', false);
            wp_enqueue_style('mir-antenn-specs');
            wp_add_inline_style('mir-antenn-specs', $this->get_frontend_styles());
            
            // Подключаем скрипт для вариаций
            wp_enqueue_script('mir-antenn-variation-specs', false, array('jquery'), '2.0.0', true);
            wp_add_inline_script('mir-antenn-variation-specs', $this->get_frontend_scripts());
            
            // Передаем данные для AJAX
            wp_localize_script('mir-antenn-variation-specs', 'mirAntennAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mir_antenn_ajax')
            ));
        }
    }
    
    /**
     * JavaScript для фронта (обработка вариаций)
     */
    private function get_frontend_scripts() {
        return "
        jQuery(document).ready(function($) {
            var specsWrapper = $('.mir-antenn-specs-table-wrapper');
            if (!specsWrapper.length) return;
            
            var productId = specsWrapper.data('product-id');
            
            // Отслеживаем изменение вариации
            $('.variations_form').on('found_variation', function(event, variation) {
                // Загружаем характеристики для выбранной вариации
                $.ajax({
                    url: mirAntennAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_variation_specs',
                        nonce: mirAntennAjax.nonce,
                        variation_id: variation.variation_id,
                        product_id: productId
                    },
                    success: function(response) {
                        if (response.success) {
                            specsWrapper.html(response.data.html);
                        }
                    }
                });
            });
            
            // При сбросе вариации показываем глобальные характеристики
            $('.variations_form').on('reset_data', function() {
                location.reload(); // Или можно сделать AJAX запрос только глобальных
            });
        });
        ";
    }
    
    /**
     * CSS стили для фронта
     */
    private function get_frontend_styles() {
        return "
        .mir-antenn-specs-table-wrapper {
            margin: 1.5rem 0;
            padding: 0;
        }
        
        .mir-antenn-specs-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
        }
        
        .mir-antenn-specs-table tr {
            border-bottom: 1px solid #f1f5f9;
        }
        
        .mir-antenn-specs-table tr:last-child {
            border-bottom: none;
        }
        
        .mir-antenn-specs-table tr:hover {
            background: #f8fafc;
        }
        
        .mir-antenn-specs-table td {
            padding: 0.9rem 1.25rem;
            font-size: 0.95rem;
        }
        
        .mir-antenn-specs-table .spec-label {
            font-weight: 600;
            color: #475569;
            width: 45%;
            background: #fafafa;
        }
        
        .mir-antenn-specs-table .spec-value {
            color: #1e293b;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .mir-antenn-specs-table {
                border-radius: 8px;
            }
            
            .mir-antenn-specs-table td {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .mir-antenn-specs-table .spec-label {
                width: 40%;
            }
        }
        ";
    }
    
    /**
     * Подключаем скрипты для админки
     */
    public function enqueue_admin_scripts($hook) {
        global $post;
        
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            if ('product' === $post->post_type) {
                wp_add_inline_script('jquery', $this->get_admin_scripts());
                
                // Передаем данные для AJAX
                wp_localize_script('jquery', 'mirAntennAdminAjax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mir_antenn_ajax')
                ));
            }
        }
    }
    
    /**
     * JavaScript для админки
     */
    private function get_admin_scripts() {
        return "
        jQuery(document).ready(function($) {
            var specIndex = $('#specs-container .spec-row').length;
            
            // === ГЛОБАЛЬНЫЕ ХАРАКТЕРИСТИКИ ===
            
            // Добавление новой строки
            $('#add-spec-row').on('click', function() {
                var newRow = $('<div class=\"spec-row\" data-index=\"' + specIndex + '\">' +
                    '<div class=\"spec-field\">' +
                        '<label>Название характеристики</label>' +
                        '<input type=\"text\" name=\"mir_antenn_specs[' + specIndex + '][label]\" placeholder=\"Например: Материал\" />' +
                    '</div>' +
                    '<div class=\"spec-field\">' +
                        '<label>Значение</label>' +
                        '<input type=\"text\" name=\"mir_antenn_specs[' + specIndex + '][value]\" placeholder=\"Например: Алюминий/Сталь\" />' +
                    '</div>' +
                    '<div class=\"spec-actions\">' +
                        '<button type=\"button\" class=\"remove-spec-row\" title=\"Удалить\">🗑️</button>' +
                    '</div>' +
                '</div>');
                
                $('#specs-container').append(newRow);
                specIndex++;
            });
            
            // Удаление строки
            $(document).on('click', '.remove-spec-row', function() {
                if ($('#specs-container .spec-row').length > 1) {
                    $(this).closest('.spec-row').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Должна остаться хотя бы одна строка!');
                }
            });
            
            // === ЭКСПОРТ/ИМПОРТ ===
            
            // Экспорт
            $('#export-specs').on('click', function() {
                var specs = [];
                $('#specs-container .spec-row').each(function() {
                    var label = $(this).find('input[name*=\"[label]\"]').val();
                    var value = $(this).find('input[name*=\"[value]\"]').val();
                    if (label || value) {
                        specs.push({label: label, value: value});
                    }
                });
                
                var json = JSON.stringify(specs, null, 2);
                
                // Копируем в буфер обмена
                var temp = $('<textarea>');
                $('body').append(temp);
                temp.val(json).select();
                document.execCommand('copy');
                temp.remove();
                
                alert('✅ Характеристики скопированы в буфер обмена!\\n\\nТеперь можно вставить их в другой товар через \"Импорт характеристик\"');
            });
            
            // Импорт - открытие модального окна
            $('#import-specs').on('click', function() {
                $('#import-modal').fadeIn(200);
            });
            
            // Импорт - отмена
            $('#cancel-import').on('click', function() {
                $('#import-modal').fadeOut(200);
                $('#import-data').val('');
            });
            
            // Импорт - выполнение
            $('#do-import').on('click', function() {
                var importData = $('#import-data').val();
                
                if (!importData) {
                    alert('Вставьте данные для импорта!');
                    return;
                }
                
                $.ajax({
                    url: mirAntennAdminAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'import_specs',
                        nonce: mirAntennAdminAjax.nonce,
                        import_data: importData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Очищаем текущие характеристики
                            $('#specs-container').empty();
                            
                            // Добавляем импортированные
                            var specs = response.data.specs;
                            specIndex = 0;
                            
                            $.each(specs, function(index, spec) {
                                var newRow = $('<div class=\"spec-row\" data-index=\"' + specIndex + '\">' +
                                    '<div class=\"spec-field\">' +
                                        '<label>Название характеристики</label>' +
                                        '<input type=\"text\" name=\"mir_antenn_specs[' + specIndex + '][label]\" value=\"' + spec.label + '\" />' +
                                    '</div>' +
                                    '<div class=\"spec-field\">' +
                                        '<label>Значение</label>' +
                                        '<input type=\"text\" name=\"mir_antenn_specs[' + specIndex + '][value]\" value=\"' + spec.value + '\" />' +
                                    '</div>' +
                                    '<div class=\"spec-actions\">' +
                                        '<button type=\"button\" class=\"remove-spec-row\" title=\"Удалить\">🗑️</button>' +
                                    '</div>' +
                                '</div>');
                                
                                $('#specs-container').append(newRow);
                                specIndex++;
                            });
                            
                            $('#import-modal').fadeOut(200);
                            $('#import-data').val('');
                            alert('✅ Характеристики успешно импортированы!\\n\\nНе забудьте сохранить товар.');
                        } else {
                            alert('❌ Ошибка: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('❌ Ошибка при импорте. Проверьте формат данных.');
                    }
                });
            });
            
            // === ХАРАКТЕРИСТИКИ ВАРИАЦИЙ ===
            
            var variationSpecIndexes = {};
            
            // Добавление характеристики для вариации
            $(document).on('click', '.add-variation-spec', function() {
                var variationId = $(this).data('variation');
                var container = $('.variation-specs-container[data-variation=\"' + variationId + '\"]');
                
                if (!variationSpecIndexes[variationId]) {
                    variationSpecIndexes[variationId] = container.find('.variation-spec-row').length;
                }
                
                var index = variationSpecIndexes[variationId];
                
                var newRow = $('<div class=\"variation-spec-row\">' +
                    '<input type=\"text\" name=\"variation_specs[' + variationId + '][' + index + '][label]\" placeholder=\"Название (например: Усиление)\" />' +
                    '<input type=\"text\" name=\"variation_specs[' + variationId + '][' + index + '][value]\" placeholder=\"Значение (например: +21 дБ)\" />' +
                    '<button type=\"button\" class=\"remove-variation-spec\">🗑️</button>' +
                '</div>');
                
                container.append(newRow);
                variationSpecIndexes[variationId]++;
            });
            
            // Удаление характеристики вариации
            $(document).on('click', '.remove-variation-spec', function() {
                $(this).closest('.variation-spec-row').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        });
        ";
    }
}

// Инициализируем плагин
new MirAntenn_Product_Specs();
