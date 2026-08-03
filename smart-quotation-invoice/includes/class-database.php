<?php
/**
 * Database Manager Class
 * Handles database table creation and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class SQI_Database {

    /**
     * Create all plugin tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Customers table
        $sql_customers = "CREATE TABLE {$wpdb->prefix}sqi_customers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            company varchar(255) DEFAULT '',
            gst_number varchar(50) DEFAULT '',
            pan_number varchar(50) DEFAULT '',
            contact_person varchar(255) DEFAULT '',
            email varchar(100) DEFAULT '',
            phone varchar(20) DEFAULT '',
            mobile varchar(20) DEFAULT '',
            billing_address text DEFAULT '',
            shipping_address text DEFAULT '',
            city varchar(100) DEFAULT '',
            state varchar(100) DEFAULT '',
            country varchar(100) DEFAULT 'India',
            pin_code varchar(10) DEFAULT '',
            notes text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_name (customer_name),
            KEY email (email),
            KEY phone (phone),
            KEY gst_number (gst_number),
            KEY company (company)
        ) $charset_collate;";

        // Products table
        $sql_products = "CREATE TABLE {$wpdb->prefix}sqi_products (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_name varchar(255) NOT NULL,
            description text DEFAULT '',
            hsn_sac varchar(50) DEFAULT '',
            unit varchar(50) DEFAULT 'Nos',
            price decimal(15,2) NOT NULL DEFAULT 0.00,
            gst_rate decimal(5,2) DEFAULT 0.00,
            sku varchar(100) DEFAULT '',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_name (product_name),
            KEY sku (sku),
            KEY hsn_sac (hsn_sac),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Quotations table
        $sql_quotations = "CREATE TABLE {$wpdb->prefix}sqi_quotations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_number varchar(50) NOT NULL,
            quote_date date NOT NULL,
            valid_until date DEFAULT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_company varchar(255) DEFAULT '',
            customer_email varchar(100) DEFAULT '',
            customer_phone varchar(20) DEFAULT '',
            billing_address text DEFAULT '',
            shipping_address text DEFAULT '',
            salesperson varchar(255) DEFAULT '',
            subtotal decimal(15,2) DEFAULT 0.00,
            discount_type varchar(10) DEFAULT 'percent',
            discount_value decimal(15,2) DEFAULT 0.00,
            tax_amount decimal(15,2) DEFAULT 0.00,
            round_off decimal(10,2) DEFAULT 0.00,
            total_amount decimal(15,2) DEFAULT 0.00,
            notes text DEFAULT '',
            terms_conditions text DEFAULT '',
            status varchar(20) DEFAULT 'draft',
            converted_to_invoice bigint(20) UNSIGNED DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quote_number (quote_number),
            KEY customer_id (customer_id),
            KEY quote_date (quote_date),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Quotation Items table
        $sql_quote_items = "CREATE TABLE {$wpdb->prefix}sqi_quotation_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quotation_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            item_name varchar(255) NOT NULL,
            description text DEFAULT '',
            hsn_sac varchar(50) DEFAULT '',
            quantity decimal(15,3) NOT NULL DEFAULT 1.000,
            unit varchar(50) DEFAULT 'Nos',
            rate decimal(15,2) NOT NULL DEFAULT 0.00,
            discount_type varchar(10) DEFAULT 'percent',
            discount_value decimal(15,2) DEFAULT 0.00,
            gst_rate decimal(5,2) DEFAULT 0.00,
            tax_amount decimal(15,2) DEFAULT 0.00,
            total_amount decimal(15,2) DEFAULT 0.00,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quotation_id (quotation_id),
            KEY product_id (product_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Invoices table
        $sql_invoices = "CREATE TABLE {$wpdb->prefix}sqi_invoices (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            invoice_date date NOT NULL,
            due_date date DEFAULT NULL,
            reference_quote_id bigint(20) UNSIGNED DEFAULT NULL,
            reference_quote_number varchar(50) DEFAULT '',
            customer_id bigint(20) UNSIGNED NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_company varchar(255) DEFAULT '',
            customer_email varchar(100) DEFAULT '',
            customer_phone varchar(20) DEFAULT '',
            customer_gst varchar(50) DEFAULT '',
            billing_address text DEFAULT '',
            shipping_address text DEFAULT '',
            subtotal decimal(15,2) DEFAULT 0.00,
            discount_type varchar(10) DEFAULT 'percent',
            discount_value decimal(15,2) DEFAULT 0.00,
            tax_amount decimal(15,2) DEFAULT 0.00,
            round_off decimal(10,2) DEFAULT 0.00,
            total_amount decimal(15,2) DEFAULT 0.00,
            paid_amount decimal(15,2) DEFAULT 0.00,
            balance_amount decimal(15,2) DEFAULT 0.00,
            notes text DEFAULT '',
            terms_conditions text DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            payment_status varchar(20) DEFAULT 'unpaid',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_number (invoice_number),
            KEY customer_id (customer_id),
            KEY invoice_date (invoice_date),
            KEY due_date (due_date),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY reference_quote_id (reference_quote_id),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Invoice Items table
        $sql_invoice_items = "CREATE TABLE {$wpdb->prefix}sqi_invoice_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            item_name varchar(255) NOT NULL,
            description text DEFAULT '',
            hsn_sac varchar(50) DEFAULT '',
            quantity decimal(15,3) NOT NULL DEFAULT 1.000,
            unit varchar(50) DEFAULT 'Nos',
            rate decimal(15,2) NOT NULL DEFAULT 0.00,
            discount_type varchar(10) DEFAULT 'percent',
            discount_value decimal(15,2) DEFAULT 0.00,
            gst_rate decimal(5,2) DEFAULT 0.00,
            tax_amount decimal(15,2) DEFAULT 0.00,
            total_amount decimal(15,2) DEFAULT 0.00,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY product_id (product_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Payments table
        $sql_payments = "CREATE TABLE {$wpdb->prefix}sqi_payments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) UNSIGNED NOT NULL,
            payment_date date NOT NULL,
            payment_method varchar(50) DEFAULT 'cash',
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            transaction_id varchar(100) DEFAULT '',
            reference_number varchar(100) DEFAULT '',
            notes text DEFAULT '',
            received_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY payment_date (payment_date),
            KEY payment_method (payment_method),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";

        // Email Logs table
        $sql_email_logs = "CREATE TABLE {$wpdb->prefix}sqi_email_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            recipient_email varchar(255) NOT NULL,
            cc_emails text DEFAULT '',
            bcc_emails text DEFAULT '',
            subject varchar(255) NOT NULL,
            message text NOT NULL,
            attachment_path varchar(500) DEFAULT '',
            status varchar(20) DEFAULT 'sent',
            error_message text DEFAULT '',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY entity_type (entity_type, entity_id),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";

        // Settings table
        $sql_settings = "CREATE TABLE {$wpdb->prefix}sqi_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL UNIQUE,
            setting_value longtext DEFAULT '',
            setting_type varchar(20) DEFAULT 'string',
            autoload tinyint(1) DEFAULT 1,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY setting_key (setting_key),
            KEY autoload (autoload)
        ) $charset_collate;";

        // Numbering Sequences table
        $sql_sequences = "CREATE TABLE {$wpdb->prefix}sqi_sequences (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sequence_type varchar(20) NOT NULL UNIQUE,
            prefix varchar(20) DEFAULT '',
            year_format varchar(10) DEFAULT 'Y',
            separator varchar(5) DEFAULT '-',
            next_number bigint(20) UNSIGNED NOT NULL DEFAULT 1,
            padding_length int(11) DEFAULT 4,
            reset_period varchar(20) DEFAULT 'year',
            last_reset_date date DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sequence_type (sequence_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_customers);
        dbDelta($sql_products);
        dbDelta($sql_quotations);
        dbDelta($sql_quote_items);
        dbDelta($sql_invoices);
        dbDelta($sql_invoice_items);
        dbDelta($sql_payments);
        dbDelta($sql_email_logs);
        dbDelta($sql_settings);
        dbDelta($sql_sequences);
    }

    /**
     * Insert default data
     */
    public static function insert_default_data() {
        global $wpdb;

        // Insert default numbering sequences
        $sequences = array(
            array(
                'sequence_type' => 'quotation',
                'prefix' => 'QT',
                'year_format' => 'Y',
                'separator' => '-',
                'next_number' => 1,
                'padding_length' => 4,
                'reset_period' => 'year',
            ),
            array(
                'sequence_type' => 'invoice',
                'prefix' => 'INV',
                'year_format' => 'Y',
                'separator' => '-',
                'next_number' => 1,
                'padding_length' => 4,
                'reset_period' => 'year',
            ),
        );

        foreach ($sequences as $sequence) {
            $wpdb->insert(
                $wpdb->prefix . 'sqi_sequences',
                $sequence,
                array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
            );
        }
    }

    /**
     * Get next sequence number
     */
    public static function get_next_number($type = 'invoice') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sqi_sequences';
        $sequence = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE sequence_type = %s",
            $type
        ));

        if (!$sequence) {
            return false;
        }

        // Check if reset is needed
        $should_reset = false;
        $current_year = date($sequence->year_format);
        $last_reset_year = $sequence->last_reset_date ? date($sequence->year_format, strtotime($sequence->last_reset_date)) : '';

        switch ($sequence->reset_period) {
            case 'year':
                if ($current_year !== $last_reset_year) {
                    $should_reset = true;
                }
                break;
            case 'month':
                $current_month = date('Y-m');
                $last_reset_month = $sequence->last_reset_date ? date('Y-m', strtotime($sequence->last_reset_date)) : '';
                if ($current_month !== $last_reset_month) {
                    $should_reset = true;
                }
                break;
            case 'never':
            default:
                $should_reset = false;
                break;
        }

        if ($should_reset) {
            $wpdb->update(
                $table,
                array(
                    'next_number' => 1,
                    'last_reset_date' => current_time('mysql'),
                ),
                array('sequence_type' => $type),
                array('%d', '%s'),
                array('%s')
            );
            $sequence->next_number = 1;
        }

        // Generate the number
        $formatted_number = $sequence->prefix;
        
        if ($sequence->year_format) {
            $formatted_number .= $sequence->separator . date($sequence->year_format);
        }
        
        $formatted_number .= $sequence->separator . str_pad($sequence->next_number, $sequence->padding_length, '0', STR_PAD_LEFT);

        // Increment for next time
        $wpdb->update(
            $table,
            array('next_number' => $sequence->next_number + 1),
            array('sequence_type' => $type),
            array('%d'),
            array('%s')
        );

        return $formatted_number;
    }

    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            'sqi_customers',
            'sqi_products',
            'sqi_quotations',
            'sqi_quotation_items',
            'sqi_invoices',
            'sqi_invoice_items',
            'sqi_payments',
            'sqi_email_logs',
            'sqi_settings',
            'sqi_sequences',
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }
}
