<?php
/**
 * Plugin Name: Smart Quotation & Invoice Manager
 * Plugin URI: https://example.com/smart-quotation-invoice
 * Description: A professional WordPress plugin for creating and managing quotations, invoices, customers, products, and generating PDFs with email support.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-quotation-invoice
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SQI_VERSION', '1.0.0');
define('SQI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SQI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SQI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
final class Smart_Quotation_Invoice {

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('SQI_Uninstall', 'uninstall'));

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_sqi_', array($this, 'handle_ajax_requests'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core Classes
        require_once SQI_PLUGIN_DIR . 'includes/class-database.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-settings.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-customer.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-product.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-quotation.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-invoice.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-email.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-reports.php';
        require_once SQI_PLUGIN_DIR . 'includes/class-uninstall.php';

        // Admin Classes
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-dashboard.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-settings.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-customers.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-products.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-quotations.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-invoices.php';
        require_once SQI_PLUGIN_DIR . 'admin/class-admin-reports.php';
    }

    /**
     * Activation hook
     */
    public function activate() {
        SQI_Database::create_tables();
        SQI_Database::insert_default_data();
        SQI_Settings::insert_defaults();
        
        // Add custom capabilities
        $this->add_custom_capabilities();
        
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Add custom capabilities
     */
    private function add_custom_capabilities() {
        $roles = array(
            'administrator' => array(
                'manage_sqi_settings',
                'view_sqi_dashboard',
                'create_sqi_quotation',
                'edit_sqi_quotation',
                'delete_sqi_quotation',
                'create_sqi_invoice',
                'edit_sqi_invoice',
                'delete_sqi_invoice',
                'view_sqi_reports',
                'manage_sqi_customers',
                'manage_sqi_products',
            ),
            'editor' => array(
                'view_sqi_dashboard',
                'create_sqi_quotation',
                'edit_sqi_quotation',
                'create_sqi_invoice',
                'edit_sqi_invoice',
                'view_sqi_reports',
                'manage_sqi_customers',
                'manage_sqi_products',
            ),
            'shop_manager' => array(
                'view_sqi_dashboard',
                'create_sqi_quotation',
                'edit_sqi_quotation',
                'create_sqi_invoice',
                'edit_sqi_invoice',
                'view_sqi_reports',
                'manage_sqi_customers',
                'manage_sqi_products',
            ),
        );

        foreach ($roles as $role_name => $caps) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('smart-quotation-invoice', false, dirname(SQI_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Initialize admin components
        new SQI_Admin_Dashboard();
        new SQI_Admin_Settings();
        new SQI_Admin_Customers();
        new SQI_Admin_Products();
        new SQI_Admin_Quotations();
        new SQI_Admin_Invoices();
        new SQI_Admin_Reports();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $icon = 'dashicons-media-spreadsheet';
        
        // Main menu
        add_menu_page(
            __('Smart Q&I', 'smart-quotation-invoice'),
            __('Smart Q&I', 'smart-quotation-invoice'),
            'view_sqi_dashboard',
            'sqi-dashboard',
            array($this, 'render_dashboard'),
            $icon,
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Dashboard', 'smart-quotation-invoice'),
            __('Dashboard', 'smart-quotation-invoice'),
            'view_sqi_dashboard',
            'sqi-dashboard',
            array($this, 'render_dashboard')
        );

        // Quotations submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Quotations', 'smart-quotation-invoice'),
            __('Quotations', 'smart-quotation-invoice'),
            'create_sqi_quotation',
            'sqi-quotations',
            array($this, 'render_quotations')
        );

        // Invoices submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Invoices', 'smart-quotation-invoice'),
            __('Invoices', 'smart-quotation-invoice'),
            'create_sqi_invoice',
            'sqi-invoices',
            array($this, 'render_invoices')
        );

        // Customers submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Customers', 'smart-quotation-invoice'),
            __('Customers', 'smart-quotation-invoice'),
            'manage_sqi_customers',
            'sqi-customers',
            array($this, 'render_customers')
        );

        // Products submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Products', 'smart-quotation-invoice'),
            __('Products', 'smart-quotation-invoice'),
            'manage_sqi_products',
            'sqi-products',
            array($this, 'render_products')
        );

        // Reports submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Reports', 'smart-quotation-invoice'),
            __('Reports', 'smart-quotation-invoice'),
            'view_sqi_reports',
            'sqi-reports',
            array($this, 'render_reports')
        );

        // Settings submenu
        add_submenu_page(
            'sqi-dashboard',
            __('Settings', 'smart-quotation-invoice'),
            __('Settings', 'smart-quotation-invoice'),
            'manage_sqi_settings',
            'sqi-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'sqi-') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'sqi-admin-css',
            SQI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SQI_VERSION
        );

        // jQuery
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-datepicker');

        // JS
        wp_enqueue_script(
            'sqi-admin-js',
            SQI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-autocomplete', 'jquery-ui-sortable', 'jquery-ui-datepicker'),
            SQI_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sqi-admin-js', 'sqi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sqi_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'smart-quotation-invoice'),
                'loading' => __('Loading...', 'smart-quotation-invoice'),
                'error' => __('An error occurred. Please try again.', 'smart-quotation-invoice'),
                'save_success' => __('Saved successfully!', 'smart-quotation-invoice'),
                'save_error' => __('Failed to save. Please try again.', 'smart-quotation-invoice'),
            )
        ));
    }

    /**
     * Handle AJAX requests
     */
    public function handle_ajax_requests() {
        check_ajax_referer('sqi_ajax_nonce', 'nonce');

        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
        $action = str_replace('sqi_', '', $action);

        switch ($action) {
            case 'search_customers':
                SQI_Customer::ajax_search();
                break;
            case 'search_products':
                SQI_Product::ajax_search();
                break;
            case 'save_customer':
                SQI_Customer::ajax_save();
                break;
            case 'save_product':
                SQI_Product::ajax_save();
                break;
            case 'save_quotation':
                SQI_Quotation::ajax_save();
                break;
            case 'save_invoice':
                SQI_Invoice::ajax_save();
                break;
            case 'convert_quote_to_invoice':
                SQI_Quotation::ajax_convert_to_invoice();
                break;
            case 'delete_item':
                $this->handle_delete_request();
                break;
            case 'send_email':
                SQI_Email::ajax_send();
                break;
            case 'record_payment':
                SQI_Invoice::ajax_record_payment();
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid action', 'smart-quotation-invoice')));
        }
    }

    /**
     * Handle delete requests
     */
    private function handle_delete_request() {
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid ID', 'smart-quotation-invoice')));
        }

        switch ($type) {
            case 'customer':
                if (current_user_can('manage_sqi_customers')) {
                    SQI_Customer::delete($id);
                    wp_send_json_success(array('message' => __('Customer deleted successfully', 'smart-quotation-invoice')));
                }
                break;
            case 'product':
                if (current_user_can('manage_sqi_products')) {
                    SQI_Product::delete($id);
                    wp_send_json_success(array('message' => __('Product deleted successfully', 'smart-quotation-invoice')));
                }
                break;
            case 'quotation':
                if (current_user_can('delete_sqi_quotation')) {
                    SQI_Quotation::delete($id);
                    wp_send_json_success(array('message' => __('Quotation deleted successfully', 'smart-quotation-invoice')));
                }
                break;
            case 'invoice':
                if (current_user_can('delete_sqi_invoice')) {
                    SQI_Invoice::delete($id);
                    wp_send_json_success(array('message' => __('Invoice deleted successfully', 'smart-quotation-invoice')));
                }
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid type', 'smart-quotation-invoice')));
        }
    }

    /**
     * Render dashboard
     */
    public function render_dashboard() {
        include SQI_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render quotations page
     */
    public function render_quotations() {
        include SQI_PLUGIN_DIR . 'admin/views/quotations.php';
    }

    /**
     * Render invoices page
     */
    public function render_invoices() {
        include SQI_PLUGIN_DIR . 'admin/views/invoices.php';
    }

    /**
     * Render customers page
     */
    public function render_customers() {
        include SQI_PLUGIN_DIR . 'admin/views/customers.php';
    }

    /**
     * Render products page
     */
    public function render_products() {
        include SQI_PLUGIN_DIR . 'admin/views/products.php';
    }

    /**
     * Render reports page
     */
    public function render_reports() {
        include SQI_PLUGIN_DIR . 'admin/views/reports.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        include SQI_PLUGIN_DIR . 'admin/views/settings.php';
    }
}

/**
 * Initialize the plugin
 */
function sqi_init() {
    return Smart_Quotation_Invoice::get_instance();
}

// Start the plugin
sqi_init();
