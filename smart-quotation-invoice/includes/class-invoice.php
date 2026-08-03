<?php
/**
 * Invoice Manager Class
 * 
 * Handles all invoice-related operations including CRUD, status management,
 * payment tracking, and conversion from quotations.
 *
 * @package SmartQuotationInvoice
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Invoice_Manager {
    
    /**
     * Table name for invoices
     *
     * @var string
     */
    private $table_invoices;
    
    /**
     * Table name for invoice items
     *
     * @var string
     */
    private $table_invoice_items;
    
    /**
     * Table name for payments
     *
     * @var string
     */
    private $table_payments;
    
    /**
     * Instance of database manager
     *
     * @var SQI_Database_Manager
     */
    private $db_manager;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        
        $this->db_manager = new SQI_Database_Manager();
        $this->table_invoices = $wpdb->prefix . 'sqi_invoices';
        $this->table_invoice_items = $wpdb->prefix . 'sqi_invoice_items';
        $this->table_payments = $wpdb->prefix . 'sqi_payments';
        
        // AJAX actions
        add_action('wp_ajax_sqi_save_invoice', array($this, 'ajax_save_invoice'));
        add_action('wp_ajax_sqi_delete_invoice', array($this, 'ajax_delete_invoice'));
        add_action('wp_ajax_sqi_duplicate_invoice', array($this, 'ajax_duplicate_invoice'));
        add_action('wp_ajax_sqi_update_invoice_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_sqi_record_payment', array($this, 'ajax_record_payment'));
        add_action('wp_ajax_sqi_get_invoice_data', array($this, 'ajax_get_invoice_data'));
        add_action('wp_ajax_sqi_search_invoices', array($this, 'ajax_search_invoices'));
        add_action('wp_ajax_sqi_get_invoice_number', array($this, 'ajax_get_invoice_number'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu for invoices
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sqi-dashboard',
            __('Invoices', 'smart-quotation-invoice'),
            __('Invoices', 'smart-quotation-invoice'),
            'sqi_manage_invoices',
            'sqi-invoices',
            array($this, 'render_invoices_page')
        );
        
        add_submenu_page(
            'sqi-dashboard',
            __('Add Invoice', 'smart-quotation-invoice'),
            __('Add Invoice', 'smart-quotation-invoice'),
            'sqi_manage_invoices',
            'sqi-invoice-new',
            array($this, 'render_invoice_form')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sqi-invoice') === false) {
            return;
        }
        
        wp_enqueue_style('sqi-admin-css', SQI_PLUGIN_URL . 'assets/css/admin.css', array(), SQI_VERSION);
        wp_enqueue_script('sqi-admin-js', SQI_PLUGIN_URL . 'assets/js/invoice.js', array('jquery', 'jquery-ui-autocomplete'), SQI_VERSION, true);
        
        wp_localize_script('sqi-admin-js', 'sqiInvoice', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sqi_invoice_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this invoice?', 'smart-quotation-invoice'),
                'saving' => __('Saving...', 'smart-quotation-invoice'),
                'saved' => __('Invoice saved successfully!', 'smart-quotation-invoice'),
                'error' => __('An error occurred. Please try again.', 'smart-quotation-invoice'),
                'addRow' => __('Add Row', 'smart-quotation-invoice'),
                'removeRow' => __('Remove', 'smart-quotation-invoice'),
            )
        ));
    }
    
    /**
     * Render invoices list page
     *
     * @since 1.0.0
     */
    public function render_invoices_page() {
        if (!current_user_can('sqi_manage_invoices')) {
            wp_die(__('You do not have permission to access this page.', 'smart-quotation-invoice'));
        }
        
        include SQI_PLUGIN_DIR . 'admin/views/invoices-list.php';
    }
    
    /**
     * Render invoice form page
     *
     * @since 1.0.0
     */
    public function render_invoice_form() {
        if (!current_user_can('sqi_manage_invoices')) {
            wp_die(__('You do not have permission to access this page.', 'smart-quotation-invoice'));
        }
        
        $invoice_id = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : 0;
        $invoice = null;
        
        if ($invoice_id) {
            $invoice = $this->get_invoice($invoice_id);
        }
        
        include SQI_PLUGIN_DIR . 'admin/views/invoice-form.php';
    }
    
    /**
     * Get invoice by ID
     *
     * @param int $invoice_id Invoice ID
     * @return array|null Invoice data or null if not found
     * @since 1.0.0
     */
    public function get_invoice($invoice_id) {
        global $wpdb;
        
        $invoice = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_invoices} WHERE id = %d", $invoice_id),
            ARRAY_A
        );
        
        if (!$invoice) {
            return null;
        }
        
        // Get invoice items
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table_invoice_items} WHERE invoice_id = %d ORDER BY sort_order ASC", $invoice_id),
            ARRAY_A
        );
        
        $invoice['items'] = $items;
        
        // Get payments
        $payments = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table_payments} WHERE invoice_id = %d ORDER BY payment_date DESC", $invoice_id),
            ARRAY_A
        );
        
        $invoice['payments'] = $payments;
        
        return $invoice;
    }
    
    /**
     * Save invoice
     *
     * @param array $data Invoice data
     * @return int|WP_Error Invoice ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function save_invoice($data) {
        global $wpdb;
        
        // Validate nonce
        if (!isset($data['nonce']) || !wp_verify_nonce($data['nonce'], 'sqi_invoice_nonce')) {
            return new WP_Error('invalid_nonce', __('Invalid security token.', 'smart-quotation-invoice'));
        }
        
        // Capability check
        if (!current_user_can('sqi_manage_invoices')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to create invoices.', 'smart-quotation-invoice'));
        }
        
        // Sanitize and validate data
        $sanitized = $this->sanitize_invoice_data($data);
        
        if (is_wp_error($sanitized)) {
            return $sanitized;
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert or update invoice
            if (empty($sanitized['id'])) {
                // New invoice
                $result = $wpdb->insert($this->table_invoices, $sanitized);
                
                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
                
                $invoice_id = $wpdb->insert_id;
            } else {
                // Update existing invoice
                $invoice_id = absint($sanitized['id']);
                unset($sanitized['id']);
                
                $result = $wpdb->update($this->table_invoices, $sanitized, array('id' => $invoice_id));
                
                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }
            
            // Save invoice items
            $this->save_invoice_items($invoice_id, $data['items']);
            
            // Update invoice totals
            $this->update_invoice_totals($invoice_id);
            
            $wpdb->query('COMMIT');
            
            do_action('sqi_invoice_saved', $invoice_id, $data);
            
            return $invoice_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('save_failed', $e->getMessage());
        }
    }
    
    /**
     * Sanitize invoice data
     *
     * @param array $data Raw invoice data
     * @return array|WP_Error Sanitized data or WP_Error on validation failure
     * @since 1.0.0
     */
    private function sanitize_invoice_data($data) {
        $sanitized = array();
        
        // Required fields
        $required_fields = array('customer_id', 'invoice_date', 'due_date');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'smart-quotation-invoice'), $field));
            }
        }
        
        // Sanitize fields
        $sanitized['invoice_number'] = sanitize_text_field($data['invoice_number']);
        $sanitized['customer_id'] = absint($data['customer_id']);
        $sanitized['quote_id'] = !empty($data['quote_id']) ? absint($data['quote_id']) : 0;
        $sanitized['invoice_date'] = sanitize_text_field($data['invoice_date']);
        $sanitized['due_date'] = sanitize_text_field($data['due_date']);
        $sanitized['status'] = in_array($data['status'], array('draft', 'pending', 'paid', 'partial', 'cancelled')) 
            ? $data['status'] : 'draft';
        $sanitized['reference'] = sanitize_text_field($data['reference']);
        $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        $sanitized['terms'] = sanitize_textarea_field($data['terms']);
        $sanitized['footer'] = sanitize_textarea_field($data['footer']);
        $sanitized['discount_type'] = in_array($data['discount_type'], array('percent', 'fixed')) 
            ? $data['discount_type'] : 'fixed';
        $sanitized['discount_value'] = floatval($data['discount_value']);
        $sanitized['round_off'] = floatval($data['round_off']);
        $sanitized['created_by'] = get_current_user_id();
        $sanitized['modified_by'] = get_current_user_id();
        
        if (empty($data['id'])) {
            $sanitized['created_at'] = current_time('mysql');
        }
        
        $sanitized['modified_at'] = current_time('mysql');
        
        return $sanitized;
    }
    
    /**
     * Save invoice items
     *
     * @param int $invoice_id Invoice ID
     * @param array $items Invoice items
     * @since 1.0.0
     */
    private function save_invoice_items($invoice_id, $items) {
        global $wpdb;
        
        // Delete existing items
        $wpdb->delete($this->table_invoice_items, array('invoice_id' => $invoice_id));
        
        if (empty($items) || !is_array($items)) {
            return;
        }
        
        foreach ($items as $index => $item) {
            $item_data = array(
                'invoice_id' => $invoice_id,
                'product_id' => !empty($item['product_id']) ? absint($item['product_id']) : 0,
                'product_name' => sanitize_text_field($item['product_name']),
                'description' => sanitize_textarea_field($item['description']),
                'hsn_sac' => sanitize_text_field($item['hsn_sac']),
                'unit' => sanitize_text_field($item['unit']),
                'quantity' => floatval($item['quantity']),
                'rate' => floatval($item['rate']),
                'discount_type' => in_array($item['discount_type'], array('percent', 'fixed')) 
                    ? $item['discount_type'] : 'fixed',
                'discount_value' => floatval($item['discount_value']),
                'tax_rate' => floatval($item['tax_rate']),
                'tax_amount' => floatval($item['tax_amount']),
                'total' => floatval($item['total']),
                'sort_order' => $index
            );
            
            $wpdb->insert($this->table_invoice_items, $item_data);
        }
    }
    
    /**
     * Update invoice totals
     *
     * @param int $invoice_id Invoice ID
     * @since 1.0.0
     */
    private function update_invoice_totals($invoice_id) {
        global $wpdb;
        
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table_invoice_items} WHERE invoice_id = %d", $invoice_id),
            ARRAY_A
        );
        
        $subtotal = 0;
        $total_tax = 0;
        $total_discount = 0;
        
        foreach ($items as $item) {
            $subtotal += floatval($item['total']);
            $total_tax += floatval($item['tax_amount']);
            
            if ($item['discount_type'] === 'fixed') {
                $total_discount += floatval($item['discount_value']);
            } else {
                $total_discount += (floatval($item['total']) * floatval($item['discount_value']) / 100);
            }
        }
        
        $invoice = $wpdb->get_row(
            $wpdb->prepare("SELECT discount_type, discount_value, round_off FROM {$this->table_invoices} WHERE id = %d", $invoice_id),
            ARRAY_A
        );
        
        // Apply invoice-level discount
        if ($invoice['discount_type'] === 'fixed') {
            $total_discount += floatval($invoice['discount_value']);
        } else {
            $total_discount += (($subtotal - $total_discount) * floatval($invoice['discount_value']) / 100);
        }
        
        $grand_total = $subtotal + $total_tax - $total_discount + floatval($invoice['round_off']);
        
        // Calculate paid amount
        $paid_result = $wpdb->get_row(
            $wpdb->prepare("SELECT SUM(amount) as total_paid FROM {$this->table_payments} WHERE invoice_id = %d AND status = 'completed'", $invoice_id),
            ARRAY_A
        );
        
        $paid_amount = floatval($paid_result['total_paid']);
        $balance = $grand_total - $paid_amount;
        
        // Update status based on payment
        $status = 'pending';
        if ($paid_amount >= $grand_total && $grand_total > 0) {
            $status = 'paid';
        } elseif ($paid_amount > 0) {
            $status = 'partial';
        }
        
        $wpdb->update(
            $this->table_invoices,
            array(
                'subtotal' => $subtotal,
                'total_tax' => $total_tax,
                'total_discount' => $total_discount,
                'grand_total' => $grand_total,
                'paid_amount' => $paid_amount,
                'balance' => $balance,
                'status' => $status
            ),
            array('id' => $invoice_id)
        );
    }
    
    /**
     * Convert quotation to invoice
     *
     * @param int $quote_id Quotation ID
     * @return int|WP_Error Invoice ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function convert_quote_to_invoice($quote_id) {
        global $wpdb;
        
        if (!current_user_can('sqi_manage_invoices')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to create invoices.', 'smart-quotation-invoice'));
        }
        
        // Get quotation
        $quote_manager = new SQI_Quotation_Manager();
        $quote = $quote_manager->get_quotation($quote_id);
        
        if (!$quote) {
            return new WP_Error('quote_not_found', __('Quotation not found.', 'smart-quotation-invoice'));
        }
        
        // Generate new invoice number
        $invoice_number = $this->generate_invoice_number();
        
        // Prepare invoice data
        $invoice_data = array(
            'invoice_number' => $invoice_number,
            'customer_id' => $quote['customer_id'],
            'quote_id' => $quote_id,
            'invoice_date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'status' => 'pending',
            'reference' => $quote['quote_number'],
            'notes' => $quote['notes'],
            'terms' => $quote['terms'],
            'footer' => $quote['footer'],
            'discount_type' => $quote['discount_type'],
            'discount_value' => $quote['discount_value'],
            'round_off' => $quote['round_off'],
            'nonce' => wp_create_nonce('sqi_invoice_nonce')
        );
        
        // Prepare items
        $items = array();
        foreach ($quote['items'] as $item) {
            $items[] = array(
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'description' => $item['description'],
                'hsn_sac' => $item['hsn_sac'],
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'discount_type' => $item['discount_type'],
                'discount_value' => $item['discount_value'],
                'tax_rate' => $item['tax_rate'],
                'tax_amount' => $item['tax_amount'],
                'total' => $item['total']
            );
        }
        
        $invoice_data['items'] = $items;
        
        // Save invoice
        $invoice_id = $this->save_invoice($invoice_data);
        
        if (!is_wp_error($invoice_id)) {
            // Update quotation status
            $wpdb->update(
                $wpdb->prefix . 'sqi_quotations',
                array('status' => 'converted'),
                array('id' => $quote_id)
            );
            
            do_action('sqi_quote_converted_to_invoice', $quote_id, $invoice_id);
        }
        
        return $invoice_id;
    }
    
    /**
     * Generate invoice number
     *
     * @return string Generated invoice number
     * @since 1.0.0
     */
    public function generate_invoice_number() {
        $settings = get_option('sqi_settings', array());
        
        $prefix = isset($settings['invoice_prefix']) ? $settings['invoice_prefix'] : 'INV';
        $format = isset($settings['invoice_number_format']) ? $settings['invoice_number_format'] : 'INV-YEAR-SEQUENCE';
        $reset_period = isset($settings['invoice_number_reset']) ? $settings['invoice_number_reset'] : 'yearly';
        
        // Get sequence based on reset period
        $sequence_key = 'invoice';
        if ($reset_period === 'monthly') {
            $sequence_key .= '_' . date('Ym');
        } elseif ($reset_period === 'yearly') {
            $sequence_key .= '_' . date('Y');
        }
        
        $sequence = $this->db_manager->get_next_sequence($sequence_key);
        $sequence_padded = str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        // Replace placeholders
        $invoice_number = str_replace('PREFIX', $prefix, $format);
        $invoice_number = str_replace('YEAR', date('Y'), $invoice_number);
        $invoice_number = str_replace('MONTH', date('m'), $invoice_number);
        $invoice_number = str_replace('SEQUENCE', $sequence_padded, $invoice_number);
        
        return $invoice_number;
    }
    
    /**
     * Record payment
     *
     * @param array $data Payment data
     * @return int|WP_Error Payment ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function record_payment($data) {
        global $wpdb;
        
        if (!current_user_can('sqi_manage_invoices')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to record payments.', 'smart-quotation-invoice'));
        }
        
        // Validate
        if (empty($data['invoice_id']) || empty($data['amount'])) {
            return new WP_Error('missing_data', __('Invoice ID and amount are required.', 'smart-quotation-invoice'));
        }
        
        $payment_data = array(
            'invoice_id' => absint($data['invoice_id']),
            'amount' => floatval($data['amount']),
            'payment_date' => !empty($data['payment_date']) ? sanitize_text_field($data['payment_date']) : current_time('Y-m-d'),
            'payment_method' => sanitize_text_field($data['payment_method']),
            'transaction_id' => sanitize_text_field($data['transaction_id']),
            'notes' => sanitize_textarea_field($data['notes']),
            'status' => 'completed',
            'recorded_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->table_payments, $payment_data);
        $payment_id = $wpdb->insert_id;
        
        // Update invoice totals
        $this->update_invoice_totals($data['invoice_id']);
        
        do_action('sqi_payment_recorded', $payment_id, $data);
        
        return $payment_id;
    }
    
    /**
     * Delete invoice
     *
     * @param int $invoice_id Invoice ID
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function delete_invoice($invoice_id) {
        global $wpdb;
        
        if (!current_user_can('sqi_delete_invoices')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to delete invoices.', 'smart-quotation-invoice'));
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete payments
            $wpdb->delete($this->table_payments, array('invoice_id' => $invoice_id));
            
            // Delete items
            $wpdb->delete($this->table_invoice_items, array('invoice_id' => $invoice_id));
            
            // Delete invoice
            $result = $wpdb->delete($this->table_invoices, array('id' => $invoice_id));
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            $wpdb->query('COMMIT');
            
            do_action('sqi_invoice_deleted', $invoice_id);
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('delete_failed', $e->getMessage());
        }
    }
    
    /**
     * Duplicate invoice
     *
     * @param int $invoice_id Invoice ID to duplicate
     * @return int|WP_Error New invoice ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public function duplicate_invoice($invoice_id) {
        $invoice = $this->get_invoice($invoice_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found.', 'smart-quotation-invoice'));
        }
        
        // Remove ID and set new values
        unset($invoice['id']);
        $invoice['invoice_number'] = $this->generate_invoice_number();
        $invoice['invoice_date'] = current_time('Y-m-d');
        $invoice['status'] = 'draft';
        $invoice['paid_amount'] = 0;
        $invoice['balance'] = $invoice['grand_total'];
        
        return $this->save_invoice($invoice);
    }
    
    /**
     * Get invoice statistics
     *
     * @param array $args Query arguments
     * @return array Invoice statistics
     * @since 1.0.0
     */
    public function get_statistics($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'customer_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare('invoice_date >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare('invoice_date <= %s', $args['date_to']);
        }
        
        if (!empty($args['customer_id'])) {
            $where[] = $wpdb->prepare('customer_id = %d', $args['customer_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_invoices} WHERE {$where_clause}");
        
        // Total amount
        $total_amount = $wpdb->get_var("SELECT SUM(grand_total) FROM {$this->table_invoices} WHERE {$where_clause}");
        
        // Paid amount
        $paid_amount = $wpdb->get_var("SELECT SUM(paid_amount) FROM {$this->table_invoices} WHERE {$where_clause}");
        
        // Balance amount
        $balance_amount = $wpdb->get_var("SELECT SUM(balance) FROM {$this->table_invoices} WHERE {$where_clause}");
        
        return array(
            'total_invoices' => intval($total),
            'total_amount' => floatval($total_amount),
            'paid_amount' => floatval($paid_amount),
            'balance_amount' => floatval($balance_amount)
        );
    }
    
    /**
     * AJAX: Save invoice
     *
     * @since 1.0.0
     */
    public function ajax_save_invoice() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $result = $this->save_invoice($_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'invoice_id' => $result,
            'message' => __('Invoice saved successfully!', 'smart-quotation-invoice')
        ));
    }
    
    /**
     * AJAX: Delete invoice
     *
     * @since 1.0.0
     */
    public function ajax_delete_invoice() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $invoice_id = isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0;
        
        if (!$invoice_id) {
            wp_send_json_error(array('message' => __('Invalid invoice ID.', 'smart-quotation-invoice')));
        }
        
        $result = $this->delete_invoice($invoice_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Invoice deleted successfully!', 'smart-quotation-invoice')));
    }
    
    /**
     * AJAX: Duplicate invoice
     *
     * @since 1.0.0
     */
    public function ajax_duplicate_invoice() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $invoice_id = isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0;
        
        if (!$invoice_id) {
            wp_send_json_error(array('message' => __('Invalid invoice ID.', 'smart-quotation-invoice')));
        }
        
        $result = $this->duplicate_invoice($invoice_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'invoice_id' => $result,
            'message' => __('Invoice duplicated successfully!', 'smart-quotation-invoice')
        ));
    }
    
    /**
     * AJAX: Update invoice status
     *
     * @since 1.0.0
     */
    public function ajax_update_status() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        global $wpdb;
        
        $invoice_id = isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$invoice_id || !in_array($status, array('draft', 'pending', 'paid', 'partial', 'cancelled'))) {
            wp_send_json_error(array('message' => __('Invalid data.', 'smart-quotation-invoice')));
        }
        
        $wpdb->update(
            $this->table_invoices,
            array('status' => $status),
            array('id' => $invoice_id)
        );
        
        wp_send_json_success(array('message' => __('Status updated successfully!', 'smart-quotation-invoice')));
    }
    
    /**
     * AJAX: Record payment
     *
     * @since 1.0.0
     */
    public function ajax_record_payment() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $result = $this->record_payment($_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'payment_id' => $result,
            'message' => __('Payment recorded successfully!', 'smart-quotation-invoice')
        ));
    }
    
    /**
     * AJAX: Get invoice data
     *
     * @since 1.0.0
     */
    public function ajax_get_invoice_data() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $invoice_id = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : 0;
        
        if (!$invoice_id) {
            wp_send_json_error(array('message' => __('Invalid invoice ID.', 'smart-quotation-invoice')));
        }
        
        $invoice = $this->get_invoice($invoice_id);
        
        if (!$invoice) {
            wp_send_json_error(array('message' => __('Invoice not found.', 'smart-quotation-invoice')));
        }
        
        wp_send_json_success($invoice);
    }
    
    /**
     * AJAX: Search invoices
     *
     * @since 1.0.0
     */
    public function ajax_search_invoices() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        global $wpdb;
        
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
        $per_page = 20;
        
        $where = array('1=1');
        
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(invoice_number LIKE %s OR customer_name LIKE %s)', $search_like, $search_like);
        }
        
        if (!empty($status)) {
            $where[] = $wpdb->prepare('status = %s', $status);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $offset = ($page - 1) * $per_page;
        
        $invoices = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, c.customer_name, c.company, c.email, c.phone
                 FROM {$this->table_invoices} i
                 LEFT JOIN {$wpdb->prefix}sqi_customers c ON i.customer_id = c.id
                 WHERE {$where_clause}
                 ORDER BY i.created_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_invoices} WHERE {$where_clause}");
        
        wp_send_json_success(array(
            'invoices' => $invoices,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }
    
    /**
     * AJAX: Get invoice number
     *
     * @since 1.0.0
     */
    public function ajax_get_invoice_number() {
        check_ajax_referer('sqi_invoice_nonce', 'nonce');
        
        $invoice_number = $this->generate_invoice_number();
        
        wp_send_json_success(array('invoice_number' => $invoice_number));
    }
}

// Initialize
new SQI_Invoice_Manager();
