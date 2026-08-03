<?php
/**
 * Settings Manager Class
 * Handles plugin settings storage and retrieval
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Settings {

    /**
     * Cache for settings
     */
    private static $cache = array();

    /**
     * Insert default settings
     */
    public static function insert_defaults() {
        $defaults = array(
            // Company Information
            'company_name' => get_bloginfo('name'),
            'company_logo' => '',
            'company_letterhead' => '',
            'company_address' => '',
            'company_gst' => '',
            'company_pan' => '',
            'company_cin' => '',
            'company_phone' => '',
            'company_email' => get_option('admin_email'),
            'company_website' => get_site_url(),
            
            // Bank Details
            'bank_name' => '',
            'bank_account_name' => '',
            'bank_account_number' => '',
            'bank_ifsc' => '',
            'bank_branch' => '',
            'bank_swift' => '',
            
            // QR Code & Signature
            'company_qr_code' => '',
            'authorized_signature' => '',
            'company_stamp' => '',
            
            // Defaults
            'default_currency' => '₹',
            'default_currency_position' => 'left',
            'default_tax_rate' => 18.00,
            'default_terms_conditions' => "Payment Terms:\n- Payment due within 30 days\n- Please make cheque/transfer in favour of '" . get_bloginfo('name') . "'\n- All disputes subject to local jurisdiction",
            'default_footer' => 'Thank you for your business!',
            
            // Numbering
            'quote_prefix' => 'QT',
            'invoice_prefix' => 'INV',
            'number_padding' => 4,
            'reset_period' => 'year',
            
            // PDF Settings
            'paper_size' => 'A4',
            'paper_orientation' => 'portrait',
            'show_watermark' => 1,
            
            // Email Settings
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'email_subject_quote' => 'Quotation #{quote_number}',
            'email_subject_invoice' => 'Invoice #{invoice_number}',
            'email_body_quote' => "Dear Customer,\n\nPlease find attached our quotation #{quote_number} dated {quote_date}.\n\nValid Until: {valid_until}\n\nTotal Amount: {total_amount}\n\nIf you have any questions, please don't hesitate to contact us.\n\nBest Regards,\n{company_name}",
            'email_body_invoice' => "Dear Customer,\n\nPlease find attached our invoice #{invoice_number} dated {invoice_date}.\n\nDue Date: {due_date}\nTotal Amount: {total_amount}\nAmount Due: {balance_amount}\n\nPlease arrange payment at your earliest convenience.\n\nThank you for your business!\n\nBest Regards,\n{company_name}",
        );

        global $wpdb;
        $table = $wpdb->prefix . 'sqi_settings';

        foreach ($defaults as $key => $value) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
                $key
            ));

            if (!$exists) {
                $wpdb->insert(
                    $table,
                    array(
                        'setting_key' => $key,
                        'setting_value' => maybe_serialize($value),
                        'setting_type' => is_numeric($value) ? 'number' : 'string',
                        'autoload' => 1,
                    ),
                    array('%s', '%s', '%s', '%d')
                );
            }
        }
    }

    /**
     * Get a setting value
     */
    public static function get($key, $default = '') {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sqi_settings';

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_key = %s",
            $key
        ));

        if ($value === null) {
            return $default;
        }

        $unserialized = maybe_unserialize($value);
        self::$cache[$key] = $unserialized;

        return $unserialized;
    }

    /**
     * Update a setting value
     */
    public static function update($key, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'sqi_settings';

        $type = is_numeric($value) ? 'number' : 'string';
        $serialized = maybe_serialize($value);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
            $key
        ));

        if ($exists) {
            $wpdb->update(
                $table,
                array(
                    'setting_value' => $serialized,
                    'setting_type' => $type,
                ),
                array('setting_key' => $key),
                array('%s', '%s', '%s')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'setting_key' => $key,
                    'setting_value' => $serialized,
                    'setting_type' => $type,
                    'autoload' => 1,
                ),
                array('%s', '%s', '%s', '%d')
            );
        }

        // Update cache
        self::$cache[$key] = $value;

        return true;
    }

    /**
     * Delete a setting
     */
    public static function delete($key) {
        global $wpdb;
        $table = $wpdb->prefix . 'sqi_settings';

        $wpdb->delete(
            $table,
            array('setting_key' => $key),
            array('%s')
        );

        unset(self::$cache[$key]);

        return true;
    }

    /**
     * Get all settings
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'sqi_settings';

        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM $table", ARRAY_A);
        $settings = array();

        foreach ($results as $row) {
            $settings[$row['setting_key']] = maybe_unserialize($row['setting_value']);
        }

        return $settings;
    }

    /**
     * Save multiple settings at once
     */
    public static function save_multiple($settings_array) {
        foreach ($settings_array as $key => $value) {
            self::update($key, $value);
        }

        return true;
    }

    /**
     * Clear settings cache
     */
    public static function clear_cache() {
        self::$cache = array();
    }

    /**
     * Get company information as array
     */
    public static function get_company_info() {
        return array(
            'name' => self::get('company_name'),
            'logo' => self::get('company_logo'),
            'letterhead' => self::get('company_letterhead'),
            'address' => self::get('company_address'),
            'gst' => self::get('company_gst'),
            'pan' => self::get('company_pan'),
            'cin' => self::get('company_cin'),
            'phone' => self::get('company_phone'),
            'email' => self::get('company_email'),
            'website' => self::get('company_website'),
            'bank_name' => self::get('bank_name'),
            'bank_account_name' => self::get('bank_account_name'),
            'bank_account_number' => self::get('bank_account_number'),
            'bank_ifsc' => self::get('bank_ifsc'),
            'bank_branch' => self::get('bank_branch'),
            'bank_swift' => self::get('bank_swift'),
            'qr_code' => self::get('company_qr_code'),
            'signature' => self::get('authorized_signature'),
            'stamp' => self::get('company_stamp'),
            'currency' => self::get('default_currency'),
            'currency_position' => self::get('default_currency_position'),
            'terms_conditions' => self::get('default_terms_conditions'),
            'footer' => self::get('default_footer'),
        );
    }

    /**
     * Format currency
     */
    public static function format_currency($amount) {
        $currency = self::get('default_currency', '₹');
        $position = self::get('default_currency_position', 'left');
        $formatted = number_format((float)$amount, 2);

        if ($position === 'left') {
            return $currency . ' ' . $formatted;
        } elseif ($position === 'right') {
            return $formatted . ' ' . $currency;
        } elseif ($position === 'left_space') {
            return $currency . $formatted;
        } else {
            return $formatted . $currency;
        }
    }
}
