<?php
/**
 * Product Manager Class
 * Handles product/service CRUD operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Product {

    /**
     * Get products table name
     */
    private static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'sqi_products';
    }

    /**
     * Get a product by ID
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        return $product;
    }

    /**
     * Get all products with pagination
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'product_name',
            'order' => 'ASC',
            'search' => '',
            'active_only' => false,
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $params = array();

        if ($args['active_only']) {
            $where .= ' AND is_active = 1';
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (product_name LIKE %s OR description LIKE %s OR sku LIKE %s OR hsn_sac LIKE %s)";
            $params = array($search, $search, $search, $search);
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_results($prepared);
    }

    /**
     * Get total count of products
     */
    public static function get_count($search = '', $active_only = false) {
        global $wpdb;
        $table = self::get_table();

        $where = '';
        $params = array();

        if ($active_only) {
            $where .= 'WHERE is_active = 1';
        }

        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $search_where = "(product_name LIKE %s OR description LIKE %s OR sku LIKE %s)";
            $params = array($search, $search, $search);
            
            if ($where) {
                $where .= ' AND ' . $search_where;
            } else {
                $where = 'WHERE ' . $search_where;
            }
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table $where",
            $params
        ));

        return (int)$count;
    }

    /**
     * Create a new product
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $product_data = array(
            'product_name' => sanitize_text_field($data['product_name']),
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'hsn_sac' => isset($data['hsn_sac']) ? strtoupper(sanitize_text_field($data['hsn_sac'])) : '',
            'unit' => isset($data['unit']) ? sanitize_text_field($data['unit']) : 'Nos',
            'price' => floatval(isset($data['price']) ? $data['price'] : 0),
            'gst_rate' => floatval(isset($data['gst_rate']) ? $data['gst_rate'] : 0),
            'sku' => isset($data['sku']) ? strtoupper(sanitize_text_field($data['sku'])) : '',
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        );

        $format = array('%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d');

        $result = $wpdb->insert($table, $product_data, $format);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update an existing product
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $product_data = array(
            'product_name' => sanitize_text_field($data['product_name']),
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'hsn_sac' => isset($data['hsn_sac']) ? strtoupper(sanitize_text_field($data['hsn_sac'])) : '',
            'unit' => isset($data['unit']) ? sanitize_text_field($data['unit']) : 'Nos',
            'price' => floatval(isset($data['price']) ? $data['price'] : 0),
            'gst_rate' => floatval(isset($data['gst_rate']) ? $data['gst_rate'] : 0),
            'sku' => isset($data['sku']) ? strtoupper(sanitize_text_field($data['sku'])) : '',
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        );

        $format = array('%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d');

        $result = $wpdb->update(
            $table,
            $product_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Save product (create or update)
     */
    public static function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            return self::update($data['id'], $data);
        } else {
            return self::create($data);
        }
    }

    /**
     * Delete a product
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        // Check if product is used in quotations or invoices
        $has_quotations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_quotation_items WHERE product_id = %d",
            $id
        ));

        $has_invoices = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_invoice_items WHERE product_id = %d",
            $id
        ));

        if ($has_quotations > 0 || $has_invoices > 0) {
            // Instead of deleting, mark as inactive
            $wpdb->update(
                $table,
                array('is_active' => 0),
                array('id' => $id),
                array('%d'),
                array('%d')
            );
            return true;
        }

        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Search products for autocomplete
     */
    public static function ajax_search() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_sqi_products')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;

        if (empty($term)) {
            $products = self::get_all(array('limit' => $limit, 'active_only' => true));
        } else {
            $products = self::get_all(array(
                'search' => $term,
                'limit' => $limit,
                'active_only' => true,
            ));
        }

        $results = array();
        foreach ($products as $product) {
            $results[] = array(
                'id' => $product->id,
                'label' => $product->product_name . ' - ' . SQI_Settings::format_currency($product->price),
                'value' => $product->product_name,
                'data' => $product,
            );
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX save handler
     */
    public static function ajax_save() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_sqi_products')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (empty($data['product_name'])) {
            wp_send_json_error(array('message' => __('Product name is required', 'smart-quotation-invoice')));
        }

        $product_id = self::save($data);

        if ($product_id) {
            $product = self::get($product_id);
            wp_send_json_success(array(
                'id' => $product_id,
                'product' => $product,
                'message' => __('Product saved successfully', 'smart-quotation-invoice'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save product', 'smart-quotation-invoice')));
        }
    }

    /**
     * Import products from CSV
     */
    public static function import_csv($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('CSV file not found', 'smart-quotation-invoice'));
        }

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('cannot_open', __('Cannot open CSV file', 'smart-quotation-invoice'));
        }

        $header = fgetcsv($handle);
        $imported = 0;
        $errors = array();
        $row_number = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Map CSV columns to data
            $data = array_combine($header, $row);
            
            if (empty($data['product_name'])) {
                $errors[] = sprintf(__('Row %d: Product name is required', 'smart-quotation-invoice'), $row_number);
                continue;
            }

            $product_data = array(
                'product_name' => $data['product_name'],
                'description' => isset($data['description']) ? $data['description'] : '',
                'hsn_sac' => isset($data['hsn_sac']) ? $data['hsn_sac'] : '',
                'unit' => isset($data['unit']) ? $data['unit'] : 'Nos',
                'price' => isset($data['price']) ? floatval($data['price']) : 0,
                'gst_rate' => isset($data['gst_rate']) ? floatval($data['gst_rate']) : 0,
                'sku' => isset($data['sku']) ? $data['sku'] : '',
                'is_active' => 1,
            );

            $result = self::save($product_data);
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = sprintf(__('Row %d: Failed to import', 'smart-quotation-invoice'), $row_number);
            }
        }

        fclose($handle);

        return array(
            'imported' => $imported,
            'errors' => $errors,
        );
    }

    /**
     * Export products to CSV
     */
    public static function export_csv() {
        $products = self::get_all(array('limit' => 10000));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, array(
            'Product Name',
            'Description',
            'HSN/SAC',
            'Unit',
            'Price',
            'GST Rate',
            'SKU',
        ));

        // Data rows
        foreach ($products as $product) {
            fputcsv($output, array(
                $product->product_name,
                $product->description,
                $product->hsn_sac,
                $product->unit,
                $product->price,
                $product->gst_rate,
                $product->sku,
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Get product usage statistics
     */
    public static function get_usage_stats($product_id) {
        global $wpdb;

        $used_in_quotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT q.id) 
             FROM {$wpdb->prefix}sqi_quotation_items qi
             JOIN {$wpdb->prefix}sqi_quotations q ON qi.quotation_id = q.id
             WHERE qi.product_id = %d",
            $product_id
        ));

        $used_in_invoices = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT i.id) 
             FROM {$wpdb->prefix}sqi_invoice_items ii
             JOIN {$wpdb->prefix}sqi_invoices i ON ii.invoice_id = i.id
             WHERE ii.product_id = %d",
            $product_id
        ));

        return array(
            'used_in_quotations' => (int)$used_in_quotes,
            'used_in_invoices' => (int)$used_in_invoices,
        );
    }
}
