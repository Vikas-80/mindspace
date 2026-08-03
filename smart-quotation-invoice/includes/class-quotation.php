<?php
/**
 * Quotation Manager Class
 * Handles quotation CRUD operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Quotation {

    /**
     * Get quotations table name
     */
    private static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'sqi_quotations';
    }

    /**
     * Get quotation items table name
     */
    private static function get_items_table() {
        global $wpdb;
        return $wpdb->prefix . 'sqi_quotation_items';
    }

    /**
     * Get a quotation by ID
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();

        $quotation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($quotation) {
            $quotation->items = self::get_items($id);
        }

        return $quotation;
    }

    /**
     * Get a quotation by number
     */
    public static function get_by_number($quote_number) {
        global $wpdb;
        $table = self::get_table();

        $quotation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE quote_number = %s",
            $quote_number
        ));

        if ($quotation) {
            $quotation->items = self::get_items($quotation->id);
        }

        return $quotation;
    }

    /**
     * Get all quotations with pagination
     */
    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => '',
            'search' => '',
            'customer_id' => 0,
            'date_from' => '',
            'date_to' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $params = array();

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (quote_number LIKE %s OR customer_name LIKE %s OR customer_company LIKE %s)";
            $params = array_merge($params, array($search, $search, $search));
        }

        if (!empty($args['customer_id'])) {
            $where .= ' AND customer_id = %d';
            $params[] = $args['customer_id'];
        }

        if (!empty($args['date_from'])) {
            $where .= ' AND quote_date >= %s';
            $params[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where .= ' AND quote_date <= %s';
            $params[] = $args['date_to'];
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $prepared = $wpdb->prepare($sql, $params);
        $quotations = $wpdb->get_results($prepared);

        // Add items count to each quotation
        foreach ($quotations as $quotation) {
            $quotation->items_count = self::get_items_count($quotation->id);
        }

        return $quotations;
    }

    /**
     * Get total count of quotations
     */
    public static function get_count($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $where = '1=1';
        $params = array();

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (quote_number LIKE %s OR customer_name LIKE %s)";
            $params = array_merge($params, array($search, $search));
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        
        if (!empty($params)) {
            $prepared = $wpdb->prepare($sql, $params);
            $count = $wpdb->get_var($prepared);
        } else {
            $count = $wpdb->get_var($sql);
        }

        return (int)$count;
    }

    /**
     * Get quotation items
     */
    public static function get_items($quotation_id) {
        global $wpdb;
        $table = self::get_items_table();

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE quotation_id = %d ORDER BY sort_order ASC, id ASC",
            $quotation_id
        ));

        return $items;
    }

    /**
     * Get items count for a quotation
     */
    public static function get_items_count($quotation_id) {
        global $wpdb;
        $table = self::get_items_table();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE quotation_id = %d",
            $quotation_id
        ));
    }

    /**
     * Create a new quotation
     */
    public static function create($data) {
        global $wpdb;
        $table = self::get_table();

        // Generate quote number if not provided
        if (empty($data['quote_number'])) {
            $data['quote_number'] = SQI_Database::get_next_number('quotation');
        }

        $quotation_data = array(
            'quote_number' => sanitize_text_field($data['quote_number']),
            'quote_date' => sanitize_text_field($data['quote_date']),
            'valid_until' => isset($data['valid_until']) ? sanitize_text_field($data['valid_until']) : '',
            'customer_id' => absint($data['customer_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_company' => isset($data['customer_company']) ? sanitize_text_field($data['customer_company']) : '',
            'customer_email' => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '',
            'customer_phone' => isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : '',
            'billing_address' => isset($data['billing_address']) ? wp_kses_post($data['billing_address']) : '',
            'shipping_address' => isset($data['shipping_address']) ? wp_kses_post($data['shipping_address']) : '',
            'salesperson' => isset($data['salesperson']) ? sanitize_text_field($data['salesperson']) : '',
            'subtotal' => floatval(isset($data['subtotal']) ? $data['subtotal'] : 0),
            'discount_type' => isset($data['discount_type']) ? sanitize_text_field($data['discount_type']) : 'percent',
            'discount_value' => floatval(isset($data['discount_value']) ? $data['discount_value'] : 0),
            'tax_amount' => floatval(isset($data['tax_amount']) ? $data['tax_amount'] : 0),
            'round_off' => floatval(isset($data['round_off']) ? $data['round_off'] : 0),
            'total_amount' => floatval(isset($data['total_amount']) ? $data['total_amount'] : 0),
            'notes' => isset($data['notes']) ? wp_kses_post($data['notes']) : '',
            'terms_conditions' => isset($data['terms_conditions']) ? wp_kses_post($data['terms_conditions']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'draft',
            'created_by' => get_current_user_id(),
        );

        $format = array(
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%f', '%s', '%f', '%f', '%f', '%f',
            '%s', '%s', '%s', '%d'
        );

        $result = $wpdb->insert($table, $quotation_data, $format);

        if (!$result) {
            return false;
        }

        $quotation_id = $wpdb->insert_id;

        // Save items
        if (isset($data['items']) && is_array($data['items'])) {
            self::save_items($quotation_id, $data['items']);
        }

        return $quotation_id;
    }

    /**
     * Update an existing quotation
     */
    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table();

        $quotation_data = array(
            'quote_date' => sanitize_text_field($data['quote_date']),
            'valid_until' => isset($data['valid_until']) ? sanitize_text_field($data['valid_until']) : '',
            'customer_id' => absint($data['customer_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_company' => isset($data['customer_company']) ? sanitize_text_field($data['customer_company']) : '',
            'customer_email' => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '',
            'customer_phone' => isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : '',
            'billing_address' => isset($data['billing_address']) ? wp_kses_post($data['billing_address']) : '',
            'shipping_address' => isset($data['shipping_address']) ? wp_kses_post($data['shipping_address']) : '',
            'salesperson' => isset($data['salesperson']) ? sanitize_text_field($data['salesperson']) : '',
            'subtotal' => floatval(isset($data['subtotal']) ? $data['subtotal'] : 0),
            'discount_type' => isset($data['discount_type']) ? sanitize_text_field($data['discount_type']) : 'percent',
            'discount_value' => floatval(isset($data['discount_value']) ? $data['discount_value'] : 0),
            'tax_amount' => floatval(isset($data['tax_amount']) ? $data['tax_amount'] : 0),
            'round_off' => floatval(isset($data['round_off']) ? $data['round_off'] : 0),
            'total_amount' => floatval(isset($data['total_amount']) ? $data['total_amount'] : 0),
            'notes' => isset($data['notes']) ? wp_kses_post($data['notes']) : '',
            'terms_conditions' => isset($data['terms_conditions']) ? wp_kses_post($data['terms_conditions']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'draft',
        );

        $format = array(
            '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%f', '%s', '%f', '%f', '%f', '%f',
            '%s', '%s', '%s'
        );

        $result = $wpdb->update(
            $table,
            $quotation_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        if ($result === false) {
            return false;
        }

        // Update items
        if (isset($data['items']) && is_array($data['items'])) {
            self::save_items($id, $data['items']);
        }

        return true;
    }

    /**
     * Save quotation items
     */
    public static function save_items($quotation_id, $items) {
        global $wpdb;
        $table = self::get_items_table();

        // Delete existing items
        $wpdb->delete(
            $table,
            array('quotation_id' => $quotation_id),
            array('%d')
        );

        // Insert new items
        foreach ($items as $index => $item) {
            $item_data = array(
                'quotation_id' => $quotation_id,
                'product_id' => isset($item['product_id']) ? absint($item['product_id']) : null,
                'item_name' => sanitize_text_field($item['item_name']),
                'description' => isset($item['description']) ? wp_kses_post($item['description']) : '',
                'hsn_sac' => isset($item['hsn_sac']) ? sanitize_text_field($item['hsn_sac']) : '',
                'quantity' => floatval(isset($item['quantity']) ? $item['quantity'] : 1),
                'unit' => isset($item['unit']) ? sanitize_text_field($item['unit']) : 'Nos',
                'rate' => floatval(isset($item['rate']) ? $item['rate'] : 0),
                'discount_type' => isset($item['discount_type']) ? sanitize_text_field($item['discount_type']) : 'percent',
                'discount_value' => floatval(isset($item['discount_value']) ? $item['discount_value'] : 0),
                'gst_rate' => floatval(isset($item['gst_rate']) ? $item['gst_rate'] : 0),
                'tax_amount' => floatval(isset($item['tax_amount']) ? $item['tax_amount'] : 0),
                'total_amount' => floatval(isset($item['total_amount']) ? $item['total_amount'] : 0),
                'sort_order' => $index,
            );

            $wpdb->insert(
                $table,
                $item_data,
                array('%d', '%d', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%f', '%f', '%f', '%d')
            );
        }
    }

    /**
     * Save quotation (create or update)
     */
    public static function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            return self::update($data['id'], $data);
        } else {
            return self::create($data);
        }
    }

    /**
     * Delete a quotation
     */
    public static function delete($id) {
        global $wpdb;

        // Delete items first
        $wpdb->delete(
            self::get_items_table(),
            array('quotation_id' => $id),
            array('%d')
        );

        // Delete quotation
        $result = $wpdb->delete(
            self::get_table(),
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Duplicate a quotation
     */
    public static function duplicate($id) {
        $quotation = self::get($id);

        if (!$quotation) {
            return false;
        }

        $new_data = array(
            'quote_number' => SQI_Database::get_next_number('quotation'),
            'quote_date' => current_time('Y-m-d'),
            'valid_until' => $quotation->valid_until,
            'customer_id' => $quotation->customer_id,
            'customer_name' => $quotation->customer_name,
            'customer_company' => $quotation->customer_company,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'billing_address' => $quotation->billing_address,
            'shipping_address' => $quotation->shipping_address,
            'salesperson' => $quotation->salesperson,
            'subtotal' => $quotation->subtotal,
            'discount_type' => $quotation->discount_type,
            'discount_value' => $quotation->discount_value,
            'tax_amount' => $quotation->tax_amount,
            'round_off' => $quotation->round_off,
            'total_amount' => $quotation->total_amount,
            'notes' => $quotation->notes,
            'terms_conditions' => $quotation->terms_conditions,
            'status' => 'draft',
            'items' => $quotation->items,
        );

        return self::create($new_data);
    }

    /**
     * Convert quotation to invoice
     */
    public static function convert_to_invoice($quotation_id) {
        $quotation = self::get($quotation_id);

        if (!$quotation) {
            return new WP_Error('not_found', __('Quotation not found', 'smart-quotation-invoice'));
        }

        if ($quotation->converted_to_invoice) {
            return new WP_Error('already_converted', __('This quotation is already converted to an invoice', 'smart-quotation-invoice'));
        }

        $invoice_data = array(
            'invoice_date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'reference_quote_id' => $quotation_id,
            'reference_quote_number' => $quotation->quote_number,
            'customer_id' => $quotation->customer_id,
            'customer_name' => $quotation->customer_name,
            'customer_company' => $quotation->customer_company,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'billing_address' => $quotation->billing_address,
            'shipping_address' => $quotation->shipping_address,
            'subtotal' => $quotation->subtotal,
            'discount_type' => $quotation->discount_type,
            'discount_value' => $quotation->discount_value,
            'tax_amount' => $quotation->tax_amount,
            'round_off' => $quotation->round_off,
            'total_amount' => $quotation->total_amount,
            'notes' => $quotation->notes,
            'terms_conditions' => $quotation->terms_conditions,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'items' => $quotation->items,
        );

        $invoice_id = SQI_Invoice::create($invoice_data);

        if ($invoice_id) {
            // Update quotation to mark as converted
            global $wpdb;
            $wpdb->update(
                self::get_table(),
                array('converted_to_invoice' => $invoice_id, 'status' => 'converted'),
                array('id' => $quotation_id),
                array('%d', '%s'),
                array('%d')
            );

            return $invoice_id;
        }

        return false;
    }

    /**
     * AJAX save handler
     */
    public static function ajax_save() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('create_sqi_quotation') && !current_user_can('edit_sqi_quotation')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (empty($data['customer_id'])) {
            wp_send_json_error(array('message' => __('Customer is required', 'smart-quotation-invoice')));
        }

        $quotation_id = self::save($data);

        if ($quotation_id) {
            wp_send_json_success(array(
                'id' => $quotation_id,
                'message' => __('Quotation saved successfully', 'smart-quotation-invoice'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save quotation', 'smart-quotation-invoice')));
        }
    }

    /**
     * AJAX convert to invoice handler
     */
    public static function ajax_convert_to_invoice() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        if (!current_user_can('create_sqi_invoice')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smart-quotation-invoice')));
        }

        $quotation_id = isset($_POST['quotation_id']) ? absint($_POST['quotation_id']) : 0;

        if (!$quotation_id) {
            wp_send_json_error(array('message' => __('Invalid quotation ID', 'smart-quotation-invoice')));
        }

        $invoice_id = self::convert_to_invoice($quotation_id);

        if ($invoice_id && !is_wp_error($invoice_id)) {
            wp_send_json_success(array(
                'invoice_id' => $invoice_id,
                'message' => __('Quotation converted to invoice successfully', 'smart-quotation-invoice'),
            ));
        } else {
            wp_send_json_error(array('message' => $invoice_id->get_error_message()));
        }
    }

    /**
     * Calculate quotation totals
     */
    public static function calculate_totals($items, $discount_type = 'percent', $discount_value = 0) {
        $subtotal = 0;
        $total_tax = 0;

        foreach ($items as &$item) {
            // Calculate line total
            $line_total = $item['quantity'] * $item['rate'];

            // Apply item discount
            if (!empty($item['discount_value'])) {
                if ($item['discount_type'] === 'percent') {
                    $line_total -= ($line_total * $item['discount_value'] / 100);
                } else {
                    $line_total -= $item['discount_value'];
                }
            }

            // Calculate tax
            $tax_amount = $line_total * ($item['gst_rate'] / 100);
            $item['tax_amount'] = round($tax_amount, 2);

            // Final line total with tax
            $item['total_amount'] = round($line_total + $tax_amount, 2);

            $subtotal += $line_total;
            $total_tax += $tax_amount;
        }

        // Apply overall discount
        $after_discount = $subtotal;
        if ($discount_value > 0) {
            if ($discount_type === 'percent') {
                $after_discount -= ($subtotal * $discount_value / 100);
            } else {
                $after_discount -= $discount_value;
            }
        }

        // Calculate grand total
        $grand_total = $after_discount + $total_tax;
        $round_off = round($grand_total) - $grand_total;
        $final_total = round($grand_total);

        return array(
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($total_tax, 2),
            'discount_applied' => round($subtotal - $after_discount, 2),
            'round_off' => round($round_off, 2),
            'total_amount' => $final_total,
            'items' => $items,
        );
    }
}
