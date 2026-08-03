<?php
/**
 * Email Manager Class
 * 
 * Handles sending quotations and invoices via email with PDF attachments,
 * custom templates, and email logging.
 *
 * @package SmartQuotationInvoice
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Email_Manager {
    
    /**
     * Table name for email logs
     *
     * @var string
     */
    private $table_email_logs;
    
    /**
     * Settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        
        $this->table_email_logs = $wpdb->prefix . 'sqi_email_logs';
        $this->settings = get_option('sqi_settings', array());
        
        // AJAX actions
        add_action('wp_ajax_sqi_send_email', array($this, 'ajax_send_email'));
        add_action('wp_ajax_sqi_resend_email', array($this, 'ajax_resend_email'));
        add_action('wp_ajax_sqi_get_email_templates', array($this, 'ajax_get_email_templates'));
        
        // Filters for wp_mail
        add_filter('wp_mail_from', array($this, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'filter_mail_content_type'));
    }
    
    /**
     * Send quotation email
     *
     * @param int $quote_id Quotation ID
     * @param array $email_data Email data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function send_quotation_email($quote_id, $email_data = array()) {
        $quote_manager = new SQI_Quotation_Manager();
        $quote = $quote_manager->get_quotation($quote_id);
        
        if (!$quote) {
            return new WP_Error('quote_not_found', __('Quotation not found.', 'smart-quotation-invoice'));
        }
        
        $customer_manager = new SQI_Customer_Manager();
        $customer = $customer_manager->get_customer($quote['customer_id']);
        
        // Prepare email data
        $defaults = array(
            'to' => $customer['email'],
            'cc' => '',
            'bcc' => '',
            'subject' => sprintf(__('Quotation #%s from %s', 'smart-quotation-invoice'), $quote['quote_number'], $this->settings['company_name']),
            'message' => $this->get_quotation_email_template($quote, $customer),
            'attachments' => array()
        );
        
        $email_data = wp_parse_args($email_data, $defaults);
        
        // Generate PDF attachment
        $pdf_generator = new SQI_PDF_Generator();
        $pdf_path = $pdf_generator->generate_quote_pdf($quote_id, 'save');
        
        if ($pdf_path && !is_wp_error($pdf_path)) {
            $email_data['attachments'][] = $pdf_path;
        }
        
        // Send email
        $result = $this->send_email($email_data, 'quotation', $quote_id);
        
        // Clean up PDF
        if ($pdf_path && file_exists($pdf_path)) {
            // Optionally delete after sending
            // unlink($pdf_path);
        }
        
        return $result;
    }
    
    /**
     * Send invoice email
     *
     * @param int $invoice_id Invoice ID
     * @param array $email_data Email data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function send_invoice_email($invoice_id, $email_data = array()) {
        $invoice_manager = new SQI_Invoice_Manager();
        $invoice = $invoice_manager->get_invoice($invoice_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found.', 'smart-quotation-invoice'));
        }
        
        $customer_manager = new SQI_Customer_Manager();
        $customer = $customer_manager->get_customer($invoice['customer_id']);
        
        // Prepare email data
        $defaults = array(
            'to' => $customer['email'],
            'cc' => '',
            'bcc' => '',
            'subject' => sprintf(__('Invoice #%s from %s', 'smart-quotation-invoice'), $invoice['invoice_number'], $this->settings['company_name']),
            'message' => $this->get_invoice_email_template($invoice, $customer),
            'attachments' => array()
        );
        
        $email_data = wp_parse_args($email_data, $defaults);
        
        // Generate PDF attachment
        $pdf_generator = new SQI_PDF_Generator();
        $pdf_path = $pdf_generator->generate_invoice_pdf($invoice_id, 'save');
        
        if ($pdf_path && !is_wp_error($pdf_path)) {
            $email_data['attachments'][] = $pdf_path;
        }
        
        // Send email
        $result = $this->send_email($email_data, 'invoice', $invoice_id);
        
        // Clean up PDF
        if ($pdf_path && file_exists($pdf_path)) {
            // Optionally delete after sending
            // unlink($pdf_path);
        }
        
        return $result;
    }
    
    /**
     * Send payment reminder email
     *
     * @param int $invoice_id Invoice ID
     * @param array $email_data Email data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function send_payment_reminder($invoice_id, $email_data = array()) {
        $invoice_manager = new SQI_Invoice_Manager();
        $invoice = $invoice_manager->get_invoice($invoice_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found.', 'smart-quotation-invoice'));
        }
        
        $customer_manager = new SQI_Customer_Manager();
        $customer = $customer_manager->get_customer($invoice['customer_id']);
        
        // Prepare email data
        $defaults = array(
            'to' => $customer['email'],
            'cc' => '',
            'bcc' => '',
            'subject' => sprintf(__('Payment Reminder: Invoice #%s', 'smart-quotation-invoice'), $invoice['invoice_number']),
            'message' => $this->get_payment_reminder_template($invoice, $customer),
            'attachments' => array()
        );
        
        $email_data = wp_parse_args($email_data, $defaults);
        
        // Generate PDF attachment
        $pdf_generator = new SQI_PDF_Generator();
        $pdf_path = $pdf_generator->generate_invoice_pdf($invoice_id, 'save');
        
        if ($pdf_path && !is_wp_error($pdf_path)) {
            $email_data['attachments'][] = $pdf_path;
        }
        
        return $this->send_email($email_data, 'payment_reminder', $invoice_id);
    }
    
    /**
     * Send thank you email
     *
     * @param int $invoice_id Invoice ID
     * @param array $email_data Email data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public function send_thank_you_email($invoice_id, $email_data = array()) {
        $invoice_manager = new SQI_Invoice_Manager();
        $invoice = $invoice_manager->get_invoice($invoice_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found.', 'smart-quotation-invoice'));
        }
        
        $customer_manager = new SQI_Customer_Manager();
        $customer = $customer_manager->get_customer($invoice['customer_id']);
        
        // Prepare email data
        $defaults = array(
            'to' => $customer['email'],
            'cc' => '',
            'bcc' => '',
            'subject' => sprintf(__('Thank You for Your Payment - Invoice #%s', 'smart-quotation-invoice'), $invoice['invoice_number']),
            'message' => $this->get_thank_you_template($invoice, $customer),
            'attachments' => array()
        );
        
        $email_data = wp_parse_args($email_data, $defaults);
        
        return $this->send_email($email_data, 'thank_you', $invoice_id);
    }
    
    /**
     * Send email
     *
     * @param array $email_data Email data
     * @param string $type Email type
     * @param int $document_id Document ID
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    private function send_email($email_data, $type, $document_id) {
        global $wpdb;
        
        // Validate recipients
        if (empty($email_data['to'])) {
            return new WP_Error('no_recipient', __('No recipient specified.', 'smart-quotation-invoice'));
        }
        
        // Prepare headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (!empty($email_data['cc'])) {
            $headers[] = 'CC: ' . sanitize_text_field($email_data['cc']);
        }
        
        if (!empty($email_data['bcc'])) {
            $headers[] = 'BCC: ' . sanitize_text_field($email_data['bcc']);
        }
        
        // Add reply-to if configured
        if (!empty($this->settings['email'])) {
            $headers[] = 'Reply-To: ' . sanitize_email($this->settings['email']);
        }
        
        // Set content type filter temporarily
        add_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        // Send email
        $result = wp_mail(
            sanitize_email($email_data['to']),
            sanitize_text_field($email_data['subject']),
            wp_unslash($email_data['message']),
            $headers,
            $email_data['attachments']
        );
        
        // Remove filter
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });
        
        // Log email
        $this->log_email(array(
            'type' => $type,
            'document_id' => $document_id,
            'document_type' => ($type === 'quotation') ? 'quotation' : 'invoice',
            'to' => $email_data['to'],
            'cc' => $email_data['cc'],
            'bcc' => $email_data['bcc'],
            'subject' => $email_data['subject'],
            'message' => $email_data['message'],
            'status' => $result ? 'sent' : 'failed',
            'sent_by' => get_current_user_id()
        ));
        
        if (!$result) {
            global $phpmailer;
            $error_message = isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : __('Unknown error occurred.', 'smart-quotation-invoice');
            return new WP_Error('send_failed', $error_message);
        }
        
        do_action('sqi_email_sent', $type, $document_id, $email_data);
        
        return true;
    }
    
    /**
     * Log email
     *
     * @param array $data Email log data
     * @return int|false Log ID on success, false on failure
     * @since 1.0.0
     */
    private function log_email($data) {
        global $wpdb;
        
        $log_data = array(
            'type' => sanitize_text_field($data['type']),
            'document_id' => absint($data['document_id']),
            'document_type' => sanitize_text_field($data['document_type']),
            'recipient' => sanitize_email($data['to']),
            'cc' => sanitize_text_field($data['cc']),
            'bcc' => sanitize_text_field($data['bcc']),
            'subject' => sanitize_text_field($data['subject']),
            'message' => wp_kses_post($data['message']),
            'status' => sanitize_text_field($data['status']),
            'sent_by' => absint($data['sent_by']),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->table_email_logs, $log_data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get quotation email template
     *
     * @param array $quote Quotation data
     * @param array $customer Customer data
     * @return string Email HTML content
     * @since 1.0.0
     */
    private function get_quotation_email_template($quote, $customer) {
        $template = isset($this->settings['quotation_email_template']) 
            ? $this->settings['quotation_email_template'] 
            : $this->get_default_quotation_template();
        
        // Replace placeholders
        $template = str_replace('{{customer_name}}', $customer['customer_name'], $template);
        $template = str_replace('{{company_name}}', $this->settings['company_name'], $template);
        $template = str_replace('{{quote_number}}', $quote['quote_number'], $template);
        $template = str_replace('{{quote_date}}', date_i18n(get_option('date_format'), strtotime($quote['quote_date'])), $template);
        $template = str_replace('{{valid_until}}', !empty($quote['valid_until']) ? date_i18n(get_option('date_format'), strtotime($quote['valid_until'])) : '', $template);
        $template = str_replace('{{grand_total}}', $this->format_currency($quote['grand_total']), $template);
        $template = str_replace('{{notes}}', nl2br($quote['notes']), $template);
        
        return $template;
    }
    
    /**
     * Get invoice email template
     *
     * @param array $invoice Invoice data
     * @param array $customer Customer data
     * @return string Email HTML content
     * @since 1.0.0
     */
    private function get_invoice_email_template($invoice, $customer) {
        $template = isset($this->settings['invoice_email_template']) 
            ? $this->settings['invoice_email_template'] 
            : $this->get_default_invoice_template();
        
        // Replace placeholders
        $template = str_replace('{{customer_name}}', $customer['customer_name'], $template);
        $template = str_replace('{{company_name}}', $this->settings['company_name'], $template);
        $template = str_replace('{{invoice_number}}', $invoice['invoice_number'], $template);
        $template = str_replace('{{invoice_date}}', date_i18n(get_option('date_format'), strtotime($invoice['invoice_date'])), $template);
        $template = str_replace('{{due_date}}', date_i18n(get_option('date_format'), strtotime($invoice['due_date'])), $template);
        $template = str_replace('{{grand_total}}', $this->format_currency($invoice['grand_total']), $template);
        $template = str_replace('{{balance_due}}', $this->format_currency($invoice['balance']), $template);
        $template = str_replace('{{notes}}', nl2br($invoice['notes']), $template);
        
        return $template;
    }
    
    /**
     * Get payment reminder template
     *
     * @param array $invoice Invoice data
     * @param array $customer Customer data
     * @return string Email HTML content
     * @since 1.0.0
     */
    private function get_payment_reminder_template($invoice, $customer) {
        $template = $this->get_default_payment_reminder_template();
        
        // Replace placeholders
        $template = str_replace('{{customer_name}}', $customer['customer_name'], $template);
        $template = str_replace('{{company_name}}', $this->settings['company_name'], $template);
        $template = str_replace('{{invoice_number}}', $invoice['invoice_number'], $template);
        $template = str_replace('{{invoice_date}}', date_i18n(get_option('date_format'), strtotime($invoice['invoice_date'])), $template);
        $template = str_replace('{{due_date}}', date_i18n(get_option('date_format'), strtotime($invoice['due_date'])), $template);
        $template = str_replace('{{balance_due}}', $this->format_currency($invoice['balance']), $template);
        
        return $template;
    }
    
    /**
     * Get thank you template
     *
     * @param array $invoice Invoice data
     * @param array $customer Customer data
     * @return string Email HTML content
     * @since 1.0.0
     */
    private function get_thank_you_template($invoice, $customer) {
        $template = $this->get_default_thank_you_template();
        
        // Replace placeholders
        $template = str_replace('{{customer_name}}', $customer['customer_name'], $template);
        $template = str_replace('{{company_name}}', $this->settings['company_name'], $template);
        $template = str_replace('{{invoice_number}}', $invoice['invoice_number'], $template);
        $template = str_replace('{{paid_amount}}', $this->format_currency($invoice['paid_amount']), $template);
        
        return $template;
    }
    
    /**
     * Get default quotation email template
     *
     * @return string Default template HTML
     * @since 1.0.0
     */
    private function get_default_quotation_template() {
        return '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <p>Dear {{customer_name}},</p>
            
            <p>Thank you for your interest in our products/services. Please find attached the quotation as requested.</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Quotation Number:</strong></td>
                    <td style="padding: 8px;">{{quote_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Date:</strong></td>
                    <td style="padding: 8px;">{{quote_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Valid Until:</strong></td>
                    <td style="padding: 8px;">{{valid_until}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Total Amount:</strong></td>
                    <td style="padding: 8px;"><strong>{{grand_total}}</strong></td>
                </tr>
            </table>
            
            <p>If you have any questions or would like to proceed with this quotation, please don\'t hesitate to contact us.</p>
            
            <p>Best regards,<br/>
            {{company_name}}</p>
        </div>
        ';
    }
    
    /**
     * Get default invoice email template
     *
     * @return string Default template HTML
     * @since 1.0.0
     */
    private function get_default_invoice_template() {
        return '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <p>Dear {{customer_name}},</p>
            
            <p>Please find attached the invoice for your recent purchase.</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Invoice Number:</strong></td>
                    <td style="padding: 8px;">{{invoice_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Invoice Date:</strong></td>
                    <td style="padding: 8px;">{{invoice_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Due Date:</strong></td>
                    <td style="padding: 8px;">{{due_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Total Amount:</strong></td>
                    <td style="padding: 8px;"><strong>{{grand_total}}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Balance Due:</strong></td>
                    <td style="padding: 8px; color: #e74c3c;"><strong>{{balance_due}}</strong></td>
                </tr>
            </table>
            
            <p>Please arrange for payment by the due date. If you have already made the payment, please disregard this notice.</p>
            
            <p>Thank you for your business!</p>
            
            <p>Best regards,<br/>
            {{company_name}}</p>
        </div>
        ';
    }
    
    /**
     * Get default payment reminder template
     *
     * @return string Default template HTML
     * @since 1.0.0
     */
    private function get_default_payment_reminder_template() {
        return '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <p>Dear {{customer_name}},</p>
            
            <p>This is a friendly reminder that payment for the following invoice is due:</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Invoice Number:</strong></td>
                    <td style="padding: 8px;">{{invoice_number}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Invoice Date:</strong></td>
                    <td style="padding: 8px;">{{invoice_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Due Date:</strong></td>
                    <td style="padding: 8px;">{{due_date}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5;"><strong>Balance Due:</strong></td>
                    <td style="padding: 8px; color: #e74c3c;"><strong>{{balance_due}}</strong></td>
                </tr>
            </table>
            
            <p>Please arrange for payment at your earliest convenience. If you have already made the payment, please disregard this notice.</p>
            
            <p>If you have any questions regarding this invoice, please feel free to contact us.</p>
            
            <p>Best regards,<br/>
            {{company_name}}</p>
        </div>
        ';
    }
    
    /**
     * Get default thank you template
     *
     * @return string Default template HTML
     * @since 1.0.0
     */
    private function get_default_thank_you_template() {
        return '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <p>Dear {{customer_name}},</p>
            
            <p>Thank you for your payment of <strong>{{paid_amount}}</strong> for Invoice #{{invoice_number}}.</p>
            
            <p>We appreciate your business and look forward to serving you again in the future.</p>
            
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            
            <p>Best regards,<br/>
            {{company_name}}</p>
        </div>
        ';
    }
    
    /**
     * Format currency
     *
     * @param float $amount Amount to format
     * @return string Formatted currency
     * @since 1.0.0
     */
    private function format_currency($amount) {
        $currency = isset($this->settings['currency']) ? $this->settings['currency'] : '$';
        $currency_position = isset($this->settings['currency_position']) ? $this->settings['currency_position'] : 'left';
        
        $formatted = number_format(floatval($amount), 2);
        
        if ($currency_position === 'left') {
            return $currency . $formatted;
        } else {
            return $formatted . $currency;
        }
    }
    
    /**
     * Filter mail from address
     *
     * @param string $from_email From email address
     * @return string Filtered email address
     * @since 1.0.0
     */
    public function filter_mail_from($from_email) {
        if (!empty($this->settings['email'])) {
            return sanitize_email($this->settings['email']);
        }
        return $from_email;
    }
    
    /**
     * Filter mail from name
     *
     * @param string $from_name From name
     * @return string Filtered name
     * @since 1.0.0
     */
    public function filter_mail_from_name($from_name) {
        if (!empty($this->settings['company_name'])) {
            return sanitize_text_field($this->settings['company_name']);
        }
        return $from_name;
    }
    
    /**
     * Filter mail content type
     *
     * @param string $content_type Content type
     * @return string Content type
     * @since 1.0.0
     */
    public function filter_mail_content_type($content_type) {
        return 'text/html';
    }
    
    /**
     * Get email logs
     *
     * @param array $args Query arguments
     * @return array Email logs
     * @since 1.0.0
     */
    public function get_email_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'document_id' => 0,
            'document_type' => '',
            'type' => '',
            'status' => '',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['document_id'])) {
            $where[] = $wpdb->prepare('document_id = %d', $args['document_id']);
        }
        
        if (!empty($args['document_type'])) {
            $where[] = $wpdb->prepare('document_type = %s', $args['document_type']);
        }
        
        if (!empty($args['type'])) {
            $where[] = $wpdb->prepare('type = %s', $args['type']);
        }
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_email_logs}
                 WHERE {$where_clause}
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            ),
            ARRAY_A
        );
        
        return $logs;
    }
    
    /**
     * AJAX: Send email
     *
     * @since 1.0.0
     */
    public function ajax_send_email() {
        check_ajax_referer('sqi_email_nonce', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $document_id = isset($_POST['document_id']) ? absint($_POST['document_id']) : 0;
        $to = isset($_POST['to']) ? sanitize_email($_POST['to']) : '';
        $cc = isset($_POST['cc']) ? sanitize_text_field($_POST['cc']) : '';
        $bcc = isset($_POST['bcc']) ? sanitize_text_field($_POST['bcc']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
        
        if (!$document_id || !$to) {
            wp_send_json_error(array('message' => __('Invalid request.', 'smart-quotation-invoice')));
        }
        
        $email_data = array(
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'message' => $message
        );
        
        if ($type === 'quotation') {
            $result = $this->send_quotation_email($document_id, $email_data);
        } elseif ($type === 'invoice') {
            $result = $this->send_invoice_email($document_id, $email_data);
        } elseif ($type === 'payment_reminder') {
            $result = $this->send_payment_reminder($document_id, $email_data);
        } elseif ($type === 'thank_you') {
            $result = $this->send_thank_you_email($document_id, $email_data);
        } else {
            wp_send_json_error(array('message' => __('Invalid email type.', 'smart-quotation-invoice')));
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Email sent successfully!', 'smart-quotation-invoice')));
    }
    
    /**
     * AJAX: Resend email
     *
     * @since 1.0.0
     */
    public function ajax_resend_email() {
        check_ajax_referer('sqi_email_nonce', 'nonce');
        
        $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
        
        if (!$log_id) {
            wp_send_json_error(array('message' => __('Invalid log ID.', 'smart-quotation-invoice')));
        }
        
        global $wpdb;
        
        $log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_email_logs} WHERE id = %d", $log_id),
            ARRAY_A
        );
        
        if (!$log) {
            wp_send_json_error(array('message' => __('Email log not found.', 'smart-quotation-invoice')));
        }
        
        $email_data = array(
            'to' => $log['recipient'],
            'cc' => $log['cc'],
            'bcc' => $log['bcc'],
            'subject' => $log['subject'],
            'message' => $log['message']
        );
        
        $result = $this->send_email($email_data, $log['type'], $log['document_id']);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Email resent successfully!', 'smart-quotation-invoice')));
    }
    
    /**
     * AJAX: Get email templates
     *
     * @since 1.0.0
     */
    public function ajax_get_email_templates() {
        check_ajax_referer('sqi_email_nonce', 'nonce');
        
        $templates = array(
            'quotation' => $this->get_default_quotation_template(),
            'invoice' => $this->get_default_invoice_template(),
            'payment_reminder' => $this->get_default_payment_reminder_template(),
            'thank_you' => $this->get_default_thank_you_template()
        );
        
        wp_send_json_success(array('templates' => $templates));
    }
}

// Initialize
new SQI_Email_Manager();
