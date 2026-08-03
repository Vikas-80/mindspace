<?php
/**
 * Reports Manager Class
 * 
 * Generates various reports including sales, quotations, revenue,
 * customer-wise, GST-wise, product-wise, and pending payments.
 *
 * @package SmartQuotationInvoice
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Reports_Manager {
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX actions
        add_action('wp_ajax_sqi_get_report_data', array($this, 'ajax_get_report_data'));
        add_action('wp_ajax_sqi_export_report', array($this, 'ajax_export_report'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu for reports
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sqi-dashboard',
            __('Reports', 'smart-quotation-invoice'),
            __('Reports', 'smart-quotation-invoice'),
            'sqi_view_reports',
            'sqi-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'smart-quotation-invoice_page_sqi-reports') {
            return;
        }
        
        wp_enqueue_style('sqi-admin-css', SQI_PLUGIN_URL . 'assets/css/admin.css', array(), SQI_VERSION);
        wp_enqueue_script('sqi-reports-js', SQI_PLUGIN_URL . 'assets/js/reports.js', array('jquery'), SQI_VERSION, true);
        
        wp_localize_script('sqi-reports-js', 'sqiReports', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sqi_reports_nonce'),
            'strings' => array(
                'exporting' => __('Exporting...', 'smart-quotation-invoice'),
                'exportSuccess' => __('Report exported successfully!', 'smart-quotation-invoice'),
                'exportError' => __('Export failed. Please try again.', 'smart-quotation-invoice')
            )
        ));
    }
    
    /**
     * Render reports page
     *
     * @since 1.0.0
     */
    public function render_reports_page() {
        if (!current_user_can('sqi_view_reports')) {
            wp_die(__('You do not have permission to access this page.', 'smart-quotation-invoice'));
        }
        
        include SQI_PLUGIN_DIR . 'admin/views/reports.php';
    }
    
    /**
     * Get sales report data
     *
     * @param array $args Query arguments
     * @return array Sales report data
     * @since 1.0.0
     */
    public function get_sales_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'group_by' => 'day' // day, month, year
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        
        // Determine date format based on group_by
        switch ($args['group_by']) {
            case 'month':
                $date_format = '%Y-%m';
                $display_format = 'F Y';
                break;
            case 'year':
                $date_format = '%Y';
                $display_format = 'Y';
                break;
            case 'day':
            default:
                $date_format = '%Y-%m-%d';
                $display_format = get_option('date_format');
                break;
        }
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE_FORMAT(invoice_date, %s) as period,
                    COUNT(*) as invoice_count,
                    SUM(grand_total) as total_amount,
                    SUM(paid_amount) as paid_amount,
                    SUM(balance) as balance_amount
                 FROM {$table_invoices}
                 WHERE status != 'cancelled'
                 AND invoice_date >= %s
                 AND invoice_date <= %s
                 GROUP BY period
                 ORDER BY period ASC",
                $date_format,
                $args['date_from'],
                $args['date_to']
            ),
            ARRAY_A
        );
        
        // Format results
        $formatted_results = array();
        foreach ($results as $row) {
            $formatted_results[] = array(
                'period' => date_i18n($display_format, strtotime($row['period'] . '-01')),
                'period_raw' => $row['period'],
                'invoice_count' => intval($row['invoice_count']),
                'total_amount' => floatval($row['total_amount']),
                'paid_amount' => floatval($row['paid_amount']),
                'balance_amount' => floatval($row['balance_amount'])
            );
        }
        
        return $formatted_results;
    }
    
    /**
     * Get quotation report data
     *
     * @param array $args Query arguments
     * @return array Quotation report data
     * @since 1.0.0
     */
    public function get_quotation_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'status' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_quotations = $wpdb->prefix . 'sqi_quotations';
        
        $where = array('1=1');
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare('quote_date >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare('quote_date <= %s', $args['date_to']);
        }
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results(
            "SELECT 
                q.*,
                c.customer_name,
                c.company
             FROM {$table_quotations} q
             LEFT JOIN {$wpdb->prefix}sqi_customers c ON q.customer_id = c.id
             WHERE {$where_clause}
             ORDER BY q.created_at DESC",
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Get monthly revenue report
     *
     * @param array $args Query arguments
     * @return array Monthly revenue data
     * @since 1.0.0
     */
    public function get_monthly_revenue($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'year' => date('Y')
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    MONTH(invoice_date) as month,
                    MONTHNAME(invoice_date) as month_name,
                    COUNT(*) as invoice_count,
                    SUM(grand_total) as total_revenue,
                    SUM(paid_amount) as collected_amount,
                    SUM(balance) as pending_amount
                 FROM {$table_invoices}
                 WHERE YEAR(invoice_date) = %d
                 AND status != 'cancelled'
                 GROUP BY MONTH(invoice_date)
                 ORDER BY month ASC",
                $args['year']
            ),
            ARRAY_A
        );
        
        // Ensure all 12 months are present
        $all_months = array();
        for ($i = 1; $i <= 12; $i++) {
            $all_months[$i] = array(
                'month' => $i,
                'month_name' => date_i18n('F', mktime(0, 0, 0, $i, 1)),
                'invoice_count' => 0,
                'total_revenue' => 0,
                'collected_amount' => 0,
                'pending_amount' => 0
            );
        }
        
        foreach ($results as $row) {
            $all_months[$row['month']] = array(
                'month' => $row['month'],
                'month_name' => $row['month_name'],
                'invoice_count' => intval($row['invoice_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'collected_amount' => floatval($row['collected_amount']),
                'pending_amount' => floatval($row['pending_amount'])
            );
        }
        
        return array_values($all_months);
    }
    
    /**
     * Get customer-wise report
     *
     * @param array $args Query arguments
     * @return array Customer-wise report data
     * @since 1.0.0
     */
    public function get_customer_wise_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'customer_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        $table_customers = $wpdb->prefix . 'sqi_customers';
        
        $where = array('1=1');
        
        if (!empty($args['date_from'])) {
            $where[] = $wpdb->prepare('i.invoice_date >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where[] = $wpdb->prepare('i.invoice_date <= %s', $args['date_to']);
        }
        
        if (!empty($args['customer_id'])) {
            $where[] = $wpdb->prepare('i.customer_id = %d', $args['customer_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results(
            "SELECT 
                c.id as customer_id,
                c.customer_name,
                c.company,
                c.email,
                c.phone,
                COUNT(i.id) as total_invoices,
                SUM(i.grand_total) as total_amount,
                SUM(i.paid_amount) as total_paid,
                SUM(i.balance) as total_due
             FROM {$table_customers} c
             LEFT JOIN {$table_invoices} i ON c.id = i.customer_id AND i.status != 'cancelled'
             WHERE {$where_clause}
             GROUP BY c.id
             HAVING total_invoices > 0
             ORDER BY total_amount DESC",
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Get GST-wise report
     *
     * @param array $args Query arguments
     * @return array GST-wise report data
     * @since 1.0.0
     */
    public function get_gst_wise_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoice_items = $wpdb->prefix . 'sqi_invoice_items';
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    ii.tax_rate,
                    COUNT(*) as item_count,
                    SUM(ii.total) as taxable_amount,
                    SUM(ii.tax_amount) as tax_amount
                 FROM {$table_invoice_items} ii
                 INNER JOIN {$table_invoices} i ON ii.invoice_id = i.id
                 WHERE i.invoice_date >= %s
                 AND i.invoice_date <= %s
                 AND i.status != 'cancelled'
                 GROUP BY ii.tax_rate
                 ORDER BY ii.tax_rate ASC",
                $args['date_from'],
                $args['date_to']
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Get product-wise report
     *
     * @param array $args Query arguments
     * @return array Product-wise report data
     * @since 1.0.0
     */
    public function get_product_wise_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
            'product_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoice_items = $wpdb->prefix . 'sqi_invoice_items';
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        $table_products = $wpdb->prefix . 'sqi_products';
        
        $where = array(
            'i.invoice_date >= %s',
            'i.invoice_date <= %s',
            "i.status != 'cancelled'"
        );
        
        $params = array($args['date_from'], $args['date_to']);
        
        if (!empty($args['product_id'])) {
            $where[] = 'ii.product_id = %d';
            $params[] = $args['product_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    p.id as product_id,
                    p.product_name,
                    p.sku,
                    p.hsn_sac,
                    COUNT(ii.id) as times_sold,
                    SUM(ii.quantity) as total_quantity,
                    SUM(ii.total) as total_amount,
                    AVG(ii.rate) as avg_rate
                 FROM {$table_invoice_items} ii
                 INNER JOIN {$table_invoices} i ON ii.invoice_id = i.id
                 INNER JOIN {$table_products} p ON ii.product_id = p.id
                 WHERE {$where_clause}
                 GROUP BY p.id
                 ORDER BY total_amount DESC",
                $params
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Get pending payments report
     *
     * @param array $args Query arguments
     * @return array Pending payments report data
     * @since 1.0.0
     */
    public function get_pending_payments_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'overdue_only' => false,
            'customer_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        
        $where = array("i.balance > 0", "i.status != 'cancelled'");
        
        if ($args['overdue_only']) {
            $where[] = $wpdb->prepare('i.due_date < %s', current_time('Y-m-d'));
        }
        
        if (!empty($args['customer_id'])) {
            $where[] = $wpdb->prepare('i.customer_id = %d', $args['customer_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results(
            "SELECT 
                i.*,
                c.customer_name,
                c.company,
                c.email,
                c.phone,
                DATEDIFF(i.due_date, CURDATE()) as days_until_due
             FROM {$table_invoices} i
             LEFT JOIN {$wpdb->prefix}sqi_customers c ON i.customer_id = c.id
             WHERE {$where_clause}
             ORDER BY i.due_date ASC",
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Get dashboard statistics
     *
     * @return array Dashboard statistics
     * @since 1.0.0
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $table_quotations = $wpdb->prefix . 'sqi_quotations';
        $table_invoices = $wpdb->prefix . 'sqi_invoices';
        
        // Quotation stats
        $total_quotations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_quotations}");
        $pending_quotations = $wpdb->get_var("SELECT COUNT(*) FROM {$table_quotations} WHERE status = 'pending'");
        
        // Invoice stats
        $total_invoices = $wpdb->get_var("SELECT COUNT(*) FROM {$table_invoices}");
        $paid_invoices = $wpdb->get_var("SELECT COUNT(*) FROM {$table_invoices} WHERE status = 'paid'");
        $pending_invoices = $wpdb->get_var("SELECT COUNT(*) FROM {$table_invoices} WHERE status = 'pending'");
        $partial_invoices = $wpdb->get_var("SELECT COUNT(*) FROM {$table_invoices} WHERE status = 'partial'");
        
        // Revenue stats
        $monthly_revenue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(grand_total) FROM {$table_invoices} 
                 WHERE status != 'cancelled' 
                 AND MONTH(invoice_date) = MONTH(CURDATE())
                 AND YEAR(invoice_date) = YEAR(CURDATE())"
            )
        );
        
        $total_revenue = $wpdb->get_var(
            "SELECT SUM(paid_amount) FROM {$table_invoices} WHERE status != 'cancelled'"
        );
        
        // Recent activity
        $recent_invoices = $wpdb->get_results(
            "SELECT i.*, c.customer_name 
             FROM {$table_invoices} i
             LEFT JOIN {$wpdb->prefix}sqi_customers c ON i.customer_id = c.id
             ORDER BY i.created_at DESC
             LIMIT 5",
            ARRAY_A
        );
        
        return array(
            'quotations' => array(
                'total' => intval($total_quotations),
                'pending' => intval($pending_quotations)
            ),
            'invoices' => array(
                'total' => intval($total_invoices),
                'paid' => intval($paid_invoices),
                'pending' => intval($pending_invoices),
                'partial' => intval($partial_invoices)
            ),
            'revenue' => array(
                'monthly' => floatval($monthly_revenue),
                'total' => floatval($total_revenue)
            ),
            'recent_activity' => $recent_invoices
        );
    }
    
    /**
     * Export report to CSV
     *
     * @param array $data Data to export
     * @param string $filename Filename for download
     * @since 1.0.0
     */
    public function export_csv($data, $filename = 'report.csv') {
        if (empty($data)) {
            return;
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Output headers
        fputcsv($output, array_keys($data[0]));
        
        // Output data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export report to Excel (XML format)
     *
     * @param array $data Data to export
     * @param string $filename Filename for download
     * @since 1.0.0
     */
    public function export_excel($data, $filename = 'report.xls') {
        if (empty($data)) {
            return;
        }
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        echo '<table border="1">';
        
        // Headers
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr>';
        
        // Data
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . esc_html($cell) . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table></body></html>';
        exit;
    }
    
    /**
     * AJAX: Get report data
     *
     * @since 1.0.0
     */
    public function ajax_get_report_data() {
        check_ajax_referer('sqi_reports_nonce', 'nonce');
        
        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
        $args = isset($_POST['args']) ? $_POST['args'] : array();
        
        // Sanitize args
        $args = array_map('sanitize_text_field', $args);
        
        switch ($report_type) {
            case 'sales':
                $data = $this->get_sales_report($args);
                break;
            case 'quotations':
                $data = $this->get_quotation_report($args);
                break;
            case 'monthly_revenue':
                $data = $this->get_monthly_revenue($args);
                break;
            case 'customer_wise':
                $data = $this->get_customer_wise_report($args);
                break;
            case 'gst_wise':
                $data = $this->get_gst_wise_report($args);
                break;
            case 'product_wise':
                $data = $this->get_product_wise_report($args);
                break;
            case 'pending_payments':
                $data = $this->get_pending_payments_report($args);
                break;
            case 'dashboard':
                $data = $this->get_dashboard_stats();
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid report type.', 'smart-quotation-invoice')));
        }
        
        wp_send_json_success(array('data' => $data));
    }
    
    /**
     * AJAX: Export report
     *
     * @since 1.0.0
     */
    public function ajax_export_report() {
        check_ajax_referer('sqi_reports_nonce', 'nonce');
        
        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $args = isset($_POST['args']) ? $_POST['args'] : array();
        
        // Get report data
        switch ($report_type) {
            case 'sales':
                $data = $this->get_sales_report($args);
                $filename = 'sales_report.' . $format;
                break;
            case 'quotations':
                $data = $this->get_quotation_report($args);
                $filename = 'quotation_report.' . $format;
                break;
            case 'monthly_revenue':
                $data = $this->get_monthly_revenue($args);
                $filename = 'monthly_revenue_report.' . $format;
                break;
            case 'customer_wise':
                $data = $this->get_customer_wise_report($args);
                $filename = 'customer_wise_report.' . $format;
                break;
            case 'gst_wise':
                $data = $this->get_gst_wise_report($args);
                $filename = 'gst_wise_report.' . $format;
                break;
            case 'product_wise':
                $data = $this->get_product_wise_report($args);
                $filename = 'product_wise_report.' . $format;
                break;
            case 'pending_payments':
                $data = $this->get_pending_payments_report($args);
                $filename = 'pending_payments_report.' . $format;
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid report type.', 'smart-quotation-invoice')));
        }
        
        if ($format === 'excel') {
            $this->export_excel($data, $filename);
        } else {
            $this->export_csv($data, $filename);
        }
    }
}

// Initialize
new SQI_Reports_Manager();
