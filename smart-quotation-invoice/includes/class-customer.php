<?php
/**
 * Customer Manager Class
 * Handles customer CRUD operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Customer {

    /**
     * Get customers table name
     */
    private static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'sqi_customers';
    }

    /**
     * Get a customer by ID
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        return $customer;
    }

    /**
     * Get all customers with pagination
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $params = array();

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (customer_name LIKE %s OR company LIKE %s OR email LIKE %s OR phone LIKE %s OR gst_number LIKE %s)";
            $params = array($search, $search, $search, $search, $search);
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_results($prepared);
    }

    /**
     * Get total count of customers
     */
    public static function get_count($search = '') {
        global $wpdb;
        $table = self::get_table();

        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where = "WHERE customer_name LIKE %s OR company LIKE %s OR email LIKE %s OR phone LIKE %s";
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table $where",
                $search, $search, $search, $search
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        }

        return (int)$count;
    }

    /**
     * Create a new customer
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        $customer_data = array(
            'customer_name' => sanitize_text_field($data['customer_name']),
            'company' => isset($data['company']) ? sanitize_text_field($data['company']) : '',
            'gst_number' => isset($data['gst_number']) ? strtoupper(sanitize_text_field($data['gst_number'])) : '',
            'pan_number' => isset($data['pan_number']) ? strtoupper(sanitize_text_field($data['pan_number'])) : '',
            'contact_person' => isset($data['contact_person']) ? sanitize_text_field($data['contact_person']) : '',
            'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'mobile' => isset($data['mobile']) ? sanitize_text_field($data['mobile']) : '',
            'billing_address' => isset($data['billing_address']) ? wp_kses_post($data['billing_address']) : '',
            'shipping_address' => isset($data['shipping_address']) ? wp_kses_post($data['shipping_address']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
            'state' => isset($data['state']) ? sanitize_text_field($data['state']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : 'India',
            'pin_code' => isset($data['pin_code']) ? sanitize_text_field($data['pin_code']) : '',
            'notes' => isset($data['notes']) ? wp_kses_post($data['notes']) : '',
        );

        $format = array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        );

        $result = $wpdb->insert($table, $customer_data, $format);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update an existing customer
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $customer_data = array(
            'customer_name' => sanitize_text_field($data['customer_name']),
            'company' => isset($data['company']) ? sanitize_text_field($data['company']) : '',
            'gst_number' => isset($data['gst_number']) ? strtoupper(sanitize_text_field($data['gst_number'])) : '',
            'pan_number' => isset($data['pan_number']) ? strtoupper(sanitize_text_field($data['pan_number'])) : '',
            'contact_person' => isset($data['contact_person']) ? sanitize_text_field($data['contact_person']) : '',
            'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'mobile' => isset($data['mobile']) ? sanitize_text_field($data['mobile']) : '',
            'billing_address' => isset($data['billing_address']) ? wp_kses_post($data['billing_address']) : '',
            'shipping_address' => isset($data['shipping_address']) ? wp_kses_post($data['shipping_address']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
            'state' => isset($data['state']) ? sanitize_text_field($data['state']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : 'India',
            'pin_code' => isset($data['pin_code']) ? sanitize_text_field($data['pin_code']) : '',
            'notes' => isset($data['notes']) ? wp_kses_post($data['notes']) : '',
        );

        $format = array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        );

        $result = $wpdb->update(
            $table,
            $customer_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Save customer (create or update)
     */
    public static function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            return self::update($data['id'], $data);
        } else {
            return self::create($data);
        }
    }

    /**
     * Delete a customer
     */
    public static function delete($id) {
        global $wpdb;
        $table = self::get_table();

        // Check if customer has quotations or invoices
        $has_quotations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_quotations WHERE customer_id = %d",
            $id
        ));

        $has_invoices = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_invoices WHERE customer_id = %d",
            $id
        ));

        if ($has_quotations > 0 || $has_invoices > 0) {
            return new WP_Error('cannot_delete', __('Cannot delete customer with existing quotations or invoices', 'smart-quotation-invoice'));
        }

        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Search customers for autocomplete
     */
    public static function ajax_search() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_sqi_customers')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;

        if (empty($term)) {
            $customers = self::get_all(array('limit' => $limit));
        } else {
            $customers = self::get_all(array(
                'search' => $term,
                'limit' => $limit,
            ));
        }

        $results = array();
        foreach ($customers as $customer) {
            $results[] = array(
                'id' => $customer->id,
                'label' => $customer->customer_name . ($customer->company ? ' (' . $customer->company . ')' : ''),
                'value' => $customer->customer_name,
                'data' => $customer,
            );
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX save handler
     */
    public static function ajax_save() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_sqi_customers')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (empty($data['customer_name'])) {
            wp_send_json_error(array('message' => __('Customer name is required', 'smart-quotation-invoice')));
        }

        $customer_id = self::save($data);

        if ($customer_id) {
            $customer = self::get($customer_id);
            wp_send_json_success(array(
                'id' => $customer_id,
                'customer' => $customer,
                'message' => __('Customer saved successfully', 'smart-quotation-invoice'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save customer', 'smart-quotation-invoice')));
        }
    }

    /**
     * Get customer statistics
     */
    public static function get_stats($customer_id) {
        global $wpdb;

        $total_quotations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_quotations WHERE customer_id = %d",
            $customer_id
        ));

        $total_invoices = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sqi_invoices WHERE customer_id = %d",
            $customer_id
        ));

        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_amount) FROM {$wpdb->prefix}sqi_invoices WHERE customer_id = %d AND payment_status = 'paid'",
            $customer_id
        ));

        return array(
            'total_quotations' => (int)$total_quotations,
            'total_invoices' => (int)$total_invoices,
            'total_revenue' => (float)$total_revenue,
        );
    }

    /**
     * Get customer history (quotations and invoices)
     */
    public static function get_history($customer_id, $limit = 20) {
        global $wpdb;

        $quotations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, quote_number, quote_date, total_amount, status, created_at 
             FROM {$wpdb->prefix}sqi_quotations 
             WHERE customer_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $customer_id,
            $limit
        ));

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_number, invoice_date, total_amount, payment_status, created_at 
             FROM {$wpdb->prefix}sqi_invoices 
             WHERE customer_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $customer_id,
            $limit
        ));

        return array(
            'quotations' => $quotations,
            'invoices' => $invoices,
        );
    }
}
