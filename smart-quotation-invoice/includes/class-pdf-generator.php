<?php
/**
 * PDF Generator Class
 * 
 * Generates professional PDF documents for quotations and invoices
 * using TCPDF library with customizable templates.
 *
 * @package SmartQuotationInvoice
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_PDF_Generator {
    
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
        $this->settings = get_option('sqi_settings', array());
        
        // AJAX actions
        add_action('wp_ajax_sqi_generate_pdf', array($this, 'ajax_generate_pdf'));
        add_action('wp_ajax_sqi_preview_pdf', array($this, 'ajax_preview_pdf'));
        
        // Admin actions
        add_action('admin_post_sqi_download_pdf', array($this, 'handle_pdf_download'));
    }
    
    /**
     * Generate PDF for invoice
     *
     * @param int $invoice_id Invoice ID
     * @param string $output Output mode: 'download', 'save', 'inline'
     * @return string|bool File path or true on success, false on failure
     * @since 1.0.0
     */
    public function generate_invoice_pdf($invoice_id, $output = 'download') {
        $invoice_manager = new SQI_Invoice_Manager();
        $invoice = $invoice_manager->get_invoice($invoice_id);
        
        if (!$invoice) {
            return false;
        }
        
        return $this->generate_pdf($invoice, 'invoice', $output);
    }
    
    /**
     * Generate PDF for quotation
     *
     * @param int $quote_id Quotation ID
     * @param string $output Output mode: 'download', 'save', 'inline'
     * @return string|bool File path or true on success, false on failure
     * @since 1.0.0
     */
    public function generate_quote_pdf($quote_id, $output = 'download') {
        $quote_manager = new SQI_Quotation_Manager();
        $quote = $quote_manager->get_quotation($quote_id);
        
        if (!$quote) {
            return false;
        }
        
        return $this->generate_pdf($quote, 'quotation', $output);
    }
    
    /**
     * Generate PDF document
     *
     * @param array $data Document data (invoice or quotation)
     * @param string $type Document type: 'invoice' or 'quotation'
     * @param string $output Output mode
     * @return string|bool File path or true on success, false on failure
     * @since 1.0.0
     */
    private function generate_pdf($data, $type, $output = 'download') {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Try to include TCPDF
            $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            } else {
                // Fallback: Use DOMPDF or mPDF if available
                return $this->generate_pdf_fallback($data, $type, $output);
            }
        }
        
        // Get paper size and orientation
        $paper_size = isset($this->settings['paper_size']) ? $this->settings['paper_size'] : 'A4';
        $orientation = isset($this->settings['paper_orientation']) ? $this->settings['paper_orientation'] : 'P';
        
        // Create new PDF document
        $pdf = new TCPDF($orientation, PDF_UNIT, $paper_size, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Smart Quotation & Invoice Manager');
        $pdf->SetAuthor($this->settings['company_name']);
        $pdf->SetTitle(sprintf('%s #%s', ucfirst($type), $data[($type === 'invoice' ? 'invoice_number' : 'quote_number')]));
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        
        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Add a page
        $pdf->AddPage();
        
        // Generate HTML content
        $html = $this->generate_pdf_html($data, $type);
        
        // Output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Generate filename
        $filename = sprintf('%s_%s_%s.pdf', 
            $type,
            $data[($type === 'invoice' ? 'invoice_number' : 'quote_number')],
            date('YmdHis')
        );
        
        // Output based on mode
        switch ($output) {
            case 'save':
                $upload_dir = wp_upload_dir();
                $pdf_dir = $upload_dir['basedir'] . '/sqi-pdfs/';
                
                if (!file_exists($pdf_dir)) {
                    wp_mkdir_p($pdf_dir);
                }
                
                $file_path = $pdf_dir . $filename;
                $pdf->Output($file_path, 'F');
                return $file_path;
                
            case 'inline':
                $pdf->Output($filename, 'I');
                break;
                
            case 'download':
            default:
                $pdf->Output($filename, 'D');
                break;
        }
        
        return true;
    }
    
    /**
     * Generate PDF HTML content
     *
     * @param array $data Document data
     * @param string $type Document type
     * @return string HTML content
     * @since 1.0.0
     */
    private function generate_pdf_html($data, $type) {
        $customer_manager = new SQI_Customer_Manager();
        $customer = $customer_manager->get_customer($data['customer_id']);
        
        // Start buffering
        ob_start();
        
        ?>
        <style>
            .pdf-container {
                font-family: helvetica, arial, sans-serif;
                font-size: 10px;
                line-height: 1.4;
                color: #333;
            }
            
            .header-section {
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
            }
            
            .company-logo {
                text-align: center;
                margin-bottom: 10px;
            }
            
            .company-logo img {
                max-width: 200px;
                max-height: 80px;
            }
            
            .company-details {
                text-align: center;
                font-size: 9px;
                color: #666;
            }
            
            .document-header {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            
            .document-title {
                display: table-cell;
                width: 50%;
                font-size: 18px;
                font-weight: bold;
                color: #2c3e50;
                text-transform: uppercase;
            }
            
            .document-meta {
                display: table-cell;
                width: 50%;
                text-align: right;
                font-size: 9px;
            }
            
            .meta-row {
                margin-bottom: 3px;
            }
            
            .meta-label {
                font-weight: bold;
                color: #666;
            }
            
            .parties-section {
                display: table;
                width: 100%;
                margin-bottom: 20px;
                background-color: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
            }
            
            .party-box {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding: 5px;
            }
            
            .party-title {
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 8px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
            }
            
            .party-info {
                font-size: 9px;
                line-height: 1.5;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .items-table th {
                background-color: #2c3e50;
                color: white;
                padding: 8px 5px;
                text-align: left;
                font-size: 9px;
                font-weight: bold;
            }
            
            .items-table td {
                padding: 6px 5px;
                border-bottom: 1px solid #ddd;
                font-size: 9px;
            }
            
            .items-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .totals-section {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }
            
            .totals-table {
                display: table-cell;
                width: 60%;
            }
            
            .tax-breakdown {
                display: table-cell;
                width: 40%;
                vertical-align: top;
                padding-left: 20px;
            }
            
            .total-row {
                display: table;
                width: 100%;
                padding: 4px 0;
            }
            
            .total-label {
                display: table-cell;
                width: 60%;
                font-weight: bold;
                color: #666;
            }
            
            .total-value {
                display: table-cell;
                width: 40%;
                text-align: right;
            }
            
            .grand-total {
                background-color: #2c3e50;
                color: white;
                padding: 8px;
                font-size: 11px;
                font-weight: bold;
            }
            
            .notes-section {
                margin-top: 20px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 4px;
                font-size: 9px;
            }
            
            .section-title {
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 8px;
            }
            
            .footer-section {
                margin-top: 30px;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            
            .signature-section {
                display: table;
                width: 100%;
                margin-top: 30px;
            }
            
            .signature-box {
                display: table-cell;
                width: 50%;
                text-align: center;
                vertical-align: bottom;
            }
            
            .signature-image {
                max-width: 150px;
                max-height: 60px;
                margin-bottom: 5px;
            }
            
            .stamp-image {
                max-width: 100px;
                max-height: 100px;
            }
            
            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80px;
                color: rgba(200, 200, 200, 0.3);
                font-weight: bold;
                z-index: -1;
            }
            
            .qr-code {
                text-align: right;
                margin-top: 10px;
            }
        </style>
        
        <div class="pdf-container">
            <?php if ($data['status'] === 'draft'): ?>
            <div class="watermark">DRAFT</div>
            <?php elseif ($data['status'] === 'paid'): ?>
            <div class="watermark">PAID</div>
            <?php elseif ($data['status'] === 'cancelled'): ?>
            <div class="watermark">CANCELLED</div>
            <?php endif; ?>
            
            <!-- Header Section -->
            <div class="header-section">
                <?php if (!empty($this->settings['logo'])): ?>
                <div class="company-logo">
                    <img src="<?php echo esc_url($this->settings['logo']); ?>" alt="Company Logo" />
                </div>
                <?php endif; ?>
                
                <div class="company-details">
                    <strong><?php echo esc_html($this->settings['company_name']); ?></strong><br/>
                    <?php echo nl2br(esc_html($this->settings['address'])); ?><br/>
                    <?php if (!empty($this->settings['phone'])): ?>Phone: <?php echo esc_html($this->settings['phone']); ?><br/><?php endif; ?>
                    <?php if (!empty($this->settings['email'])): ?>Email: <?php echo esc_html($this->settings['email']); ?><br/><?php endif; ?>
                    <?php if (!empty($this->settings['website'])): ?>Website: <?php echo esc_html($this->settings['website']); ?><br/><?php endif; ?>
                    <?php if (!empty($this->settings['gst_number'])): ?>GST: <?php echo esc_html($this->settings['gst_number']); ?><br/><?php endif; ?>
                    <?php if (!empty($this->settings['pan_number'])): ?>PAN: <?php echo esc_html($this->settings['pan_number']); ?><br/><?php endif; ?>
                </div>
            </div>
            
            <!-- Document Header -->
            <div class="document-header">
                <div class="document-title">
                    <?php echo esc_html($type === 'invoice' ? 'INVOICE' : 'QUOTATION'); ?>
                </div>
                <div class="document-meta">
                    <div class="meta-row">
                        <span class="meta-label"><?php echo esc_html($type === 'invoice' ? 'Invoice' : 'Quote'); ?> #:</span>
                        <?php echo esc_html($data[($type === 'invoice' ? 'invoice_number' : 'quote_number')]); ?>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Date:</span>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($data[($type === 'invoice' ? 'invoice_date' : 'quote_date')]))); ?>
                    </div>
                    <?php if ($type === 'quotation' && !empty($data['valid_until'])): ?>
                    <div class="meta-row">
                        <span class="meta-label">Valid Until:</span>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($data['valid_until']))); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($type === 'invoice' && !empty($data['due_date'])): ?>
                    <div class="meta-row">
                        <span class="meta-label">Due Date:</span>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($data['due_date']))); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($type === 'invoice' && !empty($data['reference'])): ?>
                    <div class="meta-row">
                        <span class="meta-label">Reference:</span>
                        <?php echo esc_html($data['reference']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Parties Section -->
            <div class="parties-section">
                <div class="party-box">
                    <div class="party-title">Bill To:</div>
                    <div class="party-info">
                        <strong><?php echo esc_html($customer['customer_name']); ?></strong><br/>
                        <?php if (!empty($customer['company'])): ?><?php echo esc_html($customer['company']); ?><br/><?php endif; ?>
                        <?php echo nl2br(esc_html($customer['billing_address'])); ?><br/>
                        <?php if (!empty($customer['city'])): ?><?php echo esc_html($customer['city']); ?>, <?php endif; ?>
                        <?php if (!empty($customer['state'])): ?><?php echo esc_html($customer['state']); ?> <?php endif; ?>
                        <?php if (!empty($customer['pin_code'])): ?>-<?php echo esc_html($customer['pin_code']); ?><?php endif; ?><br/>
                        <?php if (!empty($customer['country'])): ?><?php echo esc_html($customer['country']); ?><br/><?php endif; ?>
                        <?php if (!empty($customer['email'])): ?>Email: <?php echo esc_html($customer['email']); ?><br/><?php endif; ?>
                        <?php if (!empty($customer['phone'])): ?>Phone: <?php echo esc_html($customer['phone']); ?><br/><?php endif; ?>
                        <?php if (!empty($customer['gst_number'])): ?>GST: <?php echo esc_html($customer['gst_number']); ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($type === 'invoice' && !empty($customer['shipping_address']) && $customer['shipping_address'] !== $customer['billing_address']): ?>
                <div class="party-box">
                    <div class="party-title">Ship To:</div>
                    <div class="party-info">
                        <strong><?php echo esc_html($customer['customer_name']); ?></strong><br/>
                        <?php echo nl2br(esc_html($customer['shipping_address'])); ?><br/>
                        <?php if (!empty($customer['city'])): ?><?php echo esc_html($customer['city']); ?>, <?php endif; ?>
                        <?php if (!empty($customer['state'])): ?><?php echo esc_html($customer['state']); ?> <?php endif; ?>
                        <?php if (!empty($customer['pin_code'])): ?>-<?php echo esc_html($customer['pin_code']); ?><?php endif; ?><br/>
                        <?php if (!empty($customer['country'])): ?><?php echo esc_html($customer['country']); ?><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 35%;">Item</th>
                        <th style="width: 10%;" class="text-center">Qty</th>
                        <th style="width: 10%;" class="text-center">Unit</th>
                        <th style="width: 12%;" class="text-right">Rate</th>
                        <th style="width: 10%;" class="text-right">Discount</th>
                        <th style="width: 8%;" class="text-right">Tax %</th>
                        <th style="width: 10%;" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_index = 1;
                    foreach ($data['items'] as $item): 
                    ?>
                    <tr>
                        <td><?php echo $item_index++; ?></td>
                        <td>
                            <strong><?php echo esc_html($item['product_name']); ?></strong><br/>
                            <?php if (!empty($item['description'])): ?>
                            <small><?php echo esc_html($item['description']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($item['hsn_sac'])): ?>
                            <br/><small>HSN/SAC: <?php echo esc_html($item['hsn_sac']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo esc_html(number_format($item['quantity'], 2)); ?></td>
                        <td class="text-center"><?php echo esc_html($item['unit']); ?></td>
                        <td class="text-right"><?php echo esc_html($this->format_currency($item['rate'])); ?></td>
                        <td class="text-right">
                            <?php if ($item['discount_value'] > 0): ?>
                            <?php echo esc_html($item['discount_value']); ?>
                            <?php echo ($item['discount_type'] === 'percent') ? '%' : ''; ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo esc_html(number_format($item['tax_rate'], 2)); ?>%</td>
                        <td class="text-right"><?php echo esc_html($this->format_currency($item['total'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Totals Section -->
            <div class="totals-section">
                <div class="totals-table">
                    <?php if (!empty($data['notes'])): ?>
                    <div class="notes-section">
                        <div class="section-title">Notes:</div>
                        <?php echo nl2br(esc_html($data['notes'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($data['terms'])): ?>
                    <div class="notes-section" style="margin-top: 10px;">
                        <div class="section-title">Terms & Conditions:</div>
                        <?php echo nl2br(esc_html($data['terms'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="tax-breakdown">
                    <div class="total-row">
                        <div class="total-label">Subtotal:</div>
                        <div class="total-value"><?php echo esc_html($this->format_currency($data['subtotal'])); ?></div>
                    </div>
                    
                    <?php if ($data['total_discount'] > 0): ?>
                    <div class="total-row">
                        <div class="total-label">Discount:</div>
                        <div class="total-value">-<?php echo esc_html($this->format_currency($data['total_discount'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($data['total_tax'] > 0): ?>
                    <div class="total-row">
                        <div class="total-label">Tax:</div>
                        <div class="total-value"><?php echo esc_html($this->format_currency($data['total_tax'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($data['round_off'] != 0): ?>
                    <div class="total-row">
                        <div class="total-label">Round Off:</div>
                        <div class="total-value"><?php echo esc_html($this->format_currency($data['round_off'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <div class="total-label">Total:</div>
                        <div class="total-value"><?php echo esc_html($this->format_currency($data['grand_total'])); ?></div>
                    </div>
                    
                    <?php if ($type === 'invoice'): ?>
                    <?php if (!empty($data['paid_amount']) && $data['paid_amount'] > 0): ?>
                    <div class="total-row">
                        <div class="total-label">Paid:</div>
                        <div class="total-value"><?php echo esc_html($this->format_currency($data['paid_amount'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($data['balance']) && $data['balance'] > 0): ?>
                    <div class="total-row">
                        <div class="total-label" style="color: #e74c3c;">Balance Due:</div>
                        <div class="total-value" style="color: #e74c3c;"><?php echo esc_html($this->format_currency($data['balance'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bank Details -->
            <?php if (!empty($this->settings['bank_details'])): ?>
            <div class="notes-section" style="margin-top: 20px;">
                <div class="section-title">Bank Details:</div>
                <?php echo nl2br(esc_html($this->settings['bank_details'])); ?>
            </div>
            <?php endif; ?>
            
            <!-- QR Code -->
            <?php if (!empty($this->settings['qr_code'])): ?>
            <div class="qr-code">
                <img src="<?php echo esc_url($this->settings['qr_code']); ?>" alt="QR Code" style="max-width: 100px;" />
            </div>
            <?php endif; ?>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-box">
                    <?php if (!empty($this->settings['authorized_signature'])): ?>
                    <img src="<?php echo esc_url($this->settings['authorized_signature']); ?>" alt="Signature" class="signature-image" />
                    <?php endif; ?>
                    <div>Authorized Signature</div>
                </div>
                <div class="signature-box">
                    <?php if (!empty($this->settings['stamp_image'])): ?>
                    <img src="<?php echo esc_url($this->settings['stamp_image']); ?>" alt="Stamp" class="stamp-image" />
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <?php if (!empty($this->settings['default_footer'])): ?>
            <div class="footer-section">
                <?php echo nl2br(esc_html($this->settings['default_footer'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
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
     * Fallback PDF generation using DOMPDF
     *
     * @param array $data Document data
     * @param string $type Document type
     * @param string $output Output mode
     * @return string|bool File path or true on success, false on failure
     * @since 1.0.0
     */
    private function generate_pdf_fallback($data, $type, $output) {
        // This is a fallback method when TCPDF is not available
        // In production, you should install TCPDF via composer or upload it manually
        
        return new WP_Error('pdf_library_missing', __('PDF library (TCPDF) is not installed. Please install TCPDF to generate PDFs.', 'smart-quotation-invoice'));
    }
    
    /**
     * Handle PDF download from admin
     *
     * @since 1.0.0
     */
    public function handle_pdf_download() {
        if (!current_user_can('sqi_manage_invoices') && !current_user_can('sqi_manage_quotations')) {
            wp_die(__('You do not have permission to download this document.', 'smart-quotation-invoice'));
        }
        
        check_admin_referer('sqi_pdf_download');
        
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if (!$id || !in_array($type, array('invoice', 'quotation'))) {
            wp_die(__('Invalid request.', 'smart-quotation-invoice'));
        }
        
        if ($type === 'invoice') {
            $this->generate_invoice_pdf($id, 'download');
        } else {
            $this->generate_quote_pdf($id, 'download');
        }
        
        exit;
    }
    
    /**
     * AJAX: Generate PDF
     *
     * @since 1.0.0
     */
    public function ajax_generate_pdf() {
        check_ajax_referer('sqi_pdf_nonce', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if (!$id || !in_array($type, array('invoice', 'quotation'))) {
            wp_send_json_error(array('message' => __('Invalid request.', 'smart-quotation-invoice')));
        }
        
        // Generate PDF URL
        $download_url = wp_nonce_url(
            admin_url('admin-post.php?action=sqi_download_pdf&type=' . $type . '&id=' . $id),
            'sqi_pdf_download'
        );
        
        wp_send_json_success(array(
            'download_url' => $download_url,
            'message' => __('PDF generated successfully!', 'smart-quotation-invoice')
        ));
    }
    
    /**
     * AJAX: Preview PDF
     *
     * @since 1.0.0
     */
    public function ajax_preview_pdf() {
        check_ajax_referer('sqi_pdf_nonce', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if (!$id || !in_array($type, array('invoice', 'quotation'))) {
            wp_send_json_error(array('message' => __('Invalid request.', 'smart-quotation-invoice')));
        }
        
        // Get data for preview
        if ($type === 'invoice') {
            $invoice_manager = new SQI_Invoice_Manager();
            $data = $invoice_manager->get_invoice($id);
        } else {
            $quote_manager = new SQI_Quotation_Manager();
            $data = $quote_manager->get_quotation($id);
        }
        
        if (!$data) {
            wp_send_json_error(array('message' => __('Document not found.', 'smart-quotation-invoice')));
        }
        
        // Generate HTML preview
        $html = $this->generate_pdf_html($data, $type);
        
        wp_send_json_success(array(
            'html' => $html,
            'type' => $type
        ));
    }
}

// Initialize
new SQI_PDF_Generator();
