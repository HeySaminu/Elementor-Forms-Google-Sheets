<?php
/**
 * Plugin Name: Elementor Forms Google Sheets Integration
 * Description: Add Google Sheets as an Action After Submit in Elementor Forms
 * Plugin URI: https://github.com/HeySaminu/elementor-forms-google-sheets
 * Version: 3.1.0
 * Author: Saminu Eedris
 * Author URI: https://saminu.me
 * Text Domain: elementor-forms-sheets
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: elementor-pro
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!defined('EFGS_VERSION')) {
    define('EFGS_VERSION', '3.1.0');
}

if (!defined('EFGS_PLUGIN_DIR')) {
    define('EFGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('EFGS_PLUGIN_URL')) {
    define('EFGS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Main Plugin Class
 */
if (!class_exists('Elementor_Forms_Google_Sheets')) {
final class Elementor_Forms_Google_Sheets {
    
    private static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'on_activation'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * On plugin activation
     */
    public function on_activation() {
        // Set default options
        add_option('efgs_default_webhook_url', '');
        add_option('efgs_enable_logging', 0);
        add_option('efgs_logs', []);
    }
    
    public function init() {
        // Check if Elementor is installed and active
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'admin_notice_missing_elementor'));
            return;
        }
        
        // Check if Elementor Pro is installed and active
        if (!defined('ELEMENTOR_PRO_VERSION')) {
            add_action('admin_notices', array($this, 'admin_notice_missing_elementor_pro'));
            return;
        }
        
        // Wait for Elementor Pro to fully initialize before loading our action
        add_action('elementor_pro/init', array($this, 'load_action'));
        
        // Load admin settings
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
        }
    }
    
    /**
     * Write a debug log entry only when WP debug logging is enabled
     */
    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('EFGS: ' . $message);
        }
    }

    /**
     * Load the action after Elementor Pro is fully initialized
     */
    public function load_action() {
        $this->debug_log('load_action() called');
        
        // Check if Action_Base class exists
        if (!class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base')) {
            $this->debug_log('ERROR - Action_Base class not found');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Elementor Forms Google Sheets:</strong> Elementor Pro Forms module not loaded. Please ensure Elementor Pro is active.</p></div>';
            });
            return;
        }
        
        // Load the action class with correct path
        $action_file = EFGS_PLUGIN_DIR . 'includes/action-google-sheets.php';
        $this->debug_log('Looking for action file at: ' . $action_file);
        
        if (file_exists($action_file)) {
            require_once $action_file;
            
            // Check if our class was loaded
            if (class_exists('Elementor_Forms_Google_Sheets_Action')) {
                $registered = $this->register_action_with_forms_module();

                if (!$registered) {
                    add_action('elementor_pro/forms/actions/register', array($this, 'register_action'), 10);
                    $this->debug_log('Action registration hook added');
                }
            } else {
                $this->debug_log('ERROR - Action class not found after require');
            }
        } else {
            $this->debug_log('ERROR - Action file not found at: ' . $action_file);
            add_action('admin_notices', function() use ($action_file) {
                echo '<div class="notice notice-error"><p><strong>Elementor Forms Google Sheets:</strong> Action file not found at: ' . esc_html($action_file) . '</p></div>';
            });
        }
    }
    
    /**
     * Register our custom form action
     */
    public function register_action($form_actions_registrar) {
        try {
            $action = new \Elementor_Forms_Google_Sheets_Action();
            $form_actions_registrar->register($action);
            $this->debug_log('Action registered successfully');
        } catch (Exception $e) {
            $this->debug_log('ERROR registering action: ' . $e->getMessage());
        }
    }

    /**
     * Register directly with the Forms module when available.
     */
    private function register_action_with_forms_module() {
        if (!class_exists('\ElementorPro\Plugin')) {
            return false;
        }

        try {
            $forms_module = \ElementorPro\Plugin::instance()->modules_manager->get_modules('forms');

            if (!$forms_module || !method_exists($forms_module, 'add_form_action')) {
                return false;
            }

            $forms_module->add_form_action('google_sheets', new \Elementor_Forms_Google_Sheets_Action());
            $this->debug_log('Action registered directly with forms module');

            return true;
        } catch (\Throwable $e) {
            $this->debug_log('ERROR registering directly with forms module: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Admin notice for missing Elementor
     */
    public function admin_notice_missing_elementor() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'elementor-forms-sheets'),
            '<strong>' . esc_html__('Elementor Forms Google Sheets', 'elementor-forms-sheets') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'elementor-forms-sheets') . '</strong>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
    
    /**
     * Admin notice for missing Elementor Pro
     */
    public function admin_notice_missing_elementor_pro() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'elementor-forms-sheets'),
            '<strong>' . esc_html__('Elementor Forms Google Sheets', 'elementor-forms-sheets') . '</strong>',
            '<strong>' . esc_html__('Elementor Pro', 'elementor-forms-sheets') . '</strong>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
    
    /**
     * Add admin menu for global settings
     */
    public function add_admin_menu() {
        add_options_page(
            __('Elementor Forms Google Sheets', 'elementor-forms-sheets'),
            __('Elementor Forms Sheets', 'elementor-forms-sheets'),
            'manage_options',
            'elementor-forms-sheets',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('efgs_settings', 'efgs_default_webhook_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_webhook_url'),
            'default' => '',
        ));
        register_setting('efgs_settings', 'efgs_enable_logging', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_logging_setting'),
            'default' => 0,
        ));
    }

    /**
     * Sanitize webhook URL setting
     */
    public function sanitize_webhook_url($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $sanitized = esc_url_raw($value, array('https'));

        if (empty($sanitized) || !wp_http_validate_url($sanitized)) {
            add_settings_error(
                'efgs_default_webhook_url',
                'efgs_invalid_webhook_url',
                __('Please enter a valid HTTPS webhook URL.', 'elementor-forms-sheets')
            );
            return get_option('efgs_default_webhook_url', '');
        }

        return $sanitized;
    }

    /**
     * Sanitize logging setting
     */
    public function sanitize_logging_setting($value) {
        return empty($value) ? 0 : 1;
    }

    /**
     * Read recent plugin debug lines without loading the entire debug log.
     */
    private function get_recent_debug_lines($log_file, $limit = 20, $max_bytes = 65536) {
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return [];
        }

        $handle = fopen($log_file, 'rb');
        if ($handle === false) {
            return [];
        }

        $file_size = filesize($log_file);
        $offset = max(0, $file_size - $max_bytes);

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false || $contents === '') {
            return [];
        }

        if ($offset > 0) {
            $newline_pos = strpos($contents, "\n");
            if ($newline_pos !== false) {
                $contents = substr($contents, $newline_pos + 1);
            }
        }

        $matched_lines = [];
        foreach (explode("\n", $contents) as $line) {
            if (strpos($line, 'EFGS') !== false) {
                $matched_lines[] = $line;
            }
        }

        return array_slice($matched_lines, -1 * absint($limit));
    }

    /**
     * Return stored submission logs.
     */
    private function get_submission_logs($limit = 20) {
        $logs = get_option('efgs_logs', []);

        if (!is_array($logs)) {
            return [];
        }

        return array_slice($logs, 0, absint($limit));
    }

    /**
     * Copy workspace files into the live plugin directory.
     */
    private function sync_workspace_to_plugin() {
        $source = trailingslashit(WP_CONTENT_DIR . '/efgs-workspace/source');
        $destination = trailingslashit(EFGS_PLUGIN_DIR);

        if (!is_dir($source)) {
            return new WP_Error('efgs_missing_workspace', __('Workspace source folder was not found.', 'elementor-forms-sheets'));
        }

        return $this->copy_directory($source, $destination);
    }

    /**
     * Recursively copy one directory into another.
     */
    private function copy_directory($source, $destination) {
        $source = trailingslashit($source);
        $destination = trailingslashit($destination);

        if (!is_dir($source)) {
            return new WP_Error('efgs_invalid_source', __('Invalid source directory.', 'elementor-forms-sheets'));
        }

        if (!file_exists($destination) && !wp_mkdir_p($destination)) {
            return new WP_Error('efgs_create_destination_failed', __('Unable to create destination directory.', 'elementor-forms-sheets'));
        }

        $entries = scandir($source);
        if ($entries === false) {
            return new WP_Error('efgs_scan_failed', __('Unable to read workspace directory.', 'elementor-forms-sheets'));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $source_path = $source . $entry;
            $destination_path = $destination . $entry;

            if (is_dir($source_path)) {
                $result = $this->copy_directory($source_path, $destination_path);
                if (is_wp_error($result)) {
                    return $result;
                }

                continue;
            }

            if (!copy($source_path, $destination_path)) {
                return new WP_Error(
                    'efgs_copy_failed',
                    sprintf(
                        __('Unable to copy %s into the live plugin directory.', 'elementor-forms-sheets'),
                        $entry
                    )
                );
            }
        }

        return true;
    }

    /**
     * Handle admin tool actions.
     */
    private function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['regenerate_elementor'])) {
            if (
                !isset($_POST['efgs_regenerate_nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['efgs_regenerate_nonce'])), 'efgs_regenerate_elementor')
            ) {
                wp_die(esc_html__('Security check failed.', 'elementor-forms-sheets'));
            }

            if (class_exists('\Elementor\Plugin')) {
                \Elementor\Plugin::instance()->files_manager->clear_cache();
                add_settings_error(
                    'efgs_messages',
                    'efgs_cache_cleared',
                    __('Elementor cache cleared successfully.', 'elementor-forms-sheets'),
                    'updated'
                );
            }
        }

        if (isset($_POST['efgs_clear_logs'])) {
            if (
                !isset($_POST['efgs_clear_logs_nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['efgs_clear_logs_nonce'])), 'efgs_clear_logs')
            ) {
                wp_die(esc_html__('Security check failed.', 'elementor-forms-sheets'));
            }

            update_option('efgs_logs', []);
            add_settings_error(
                'efgs_messages',
                'efgs_logs_cleared',
                __('Activity logs cleared.', 'elementor-forms-sheets'),
                'updated'
            );
        }

        if (isset($_POST['efgs_sync_workspace'])) {
            if (
                !isset($_POST['efgs_sync_workspace_nonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['efgs_sync_workspace_nonce'])), 'efgs_sync_workspace')
            ) {
                wp_die(esc_html__('Security check failed.', 'elementor-forms-sheets'));
            }

            $result = $this->sync_workspace_to_plugin();
            if (is_wp_error($result)) {
                add_settings_error(
                    'efgs_messages',
                    'efgs_sync_failed',
                    $result->get_error_message(),
                    'error'
                );
            } else {
                add_settings_error(
                    'efgs_messages',
                    'efgs_sync_complete',
                    __('Workspace synced into the live plugin successfully.', 'elementor-forms-sheets'),
                    'updated'
                );
            }
        }
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->handle_admin_actions();
        $submission_logs = $this->get_submission_logs(20);
        $workspace_source = WP_CONTENT_DIR . '/efgs-workspace/source';
        $workspace_release = WP_CONTENT_DIR . '/efgs-workspace/releases/elementor-forms-google-sheets-installable.zip';
        $has_workspace_tools = is_dir($workspace_source) || file_exists($workspace_release);
        ?>
        <div class="wrap">
            <h1><?php _e('Elementor Forms Google Sheets - Global Settings', 'elementor-forms-sheets'); ?></h1>
            <?php settings_errors('efgs_messages'); ?>
            
            <!-- Debug Info -->
            <div class="notice notice-info">
                <p style="margin: 0;">
                    <button
                        type="button"
                        class="button-link efgs-toggle-section"
                        data-target="#efgs-debug-panel"
                        aria-expanded="false"
                        style="font-size: 18px; font-weight: 600; text-decoration: none;"
                    >
                        🔍 <?php _e('Debug Information', 'elementor-forms-sheets'); ?>
                    </button>
                </p>

                <div id="efgs-debug-panel" style="display: none; margin-top: 12px;">
                <table class="widefat" style="max-width: 600px;">
                    <tr>
                        <td><strong>Elementor Installed:</strong></td>
                        <td><?php echo defined('ELEMENTOR_VERSION') ? '✅ Yes (v' . ELEMENTOR_VERSION . ')' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Elementor Pro Installed:</strong></td>
                        <td><?php echo defined('ELEMENTOR_PRO_VERSION') ? '✅ Yes (v' . ELEMENTOR_PRO_VERSION . ')' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Plugin Active:</strong></td>
                        <td><?php echo is_plugin_active(plugin_basename(__FILE__)) ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Action Class Loaded:</strong></td>
                        <td><?php echo class_exists('Elementor_Forms_Google_Sheets_Action') ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Action Base Available:</strong></td>
                        <td><?php echo class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base') ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Action File Exists:</strong></td>
                        <td><?php echo file_exists(EFGS_PLUGIN_DIR . 'includes/action-google-sheets.php') ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                </table>
                
                <?php
                // Show recent debug logs
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                    $recent_logs = $this->get_recent_debug_lines($log_file, 20);
                    if (!empty($recent_logs)) {
                        echo '<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;">';
                        echo '<strong>📋 Recent Debug Logs (last 20):</strong><br>';
                        echo '<pre style="font-size: 11px; margin: 5px 0 0 0;">' . esc_html(implode("\n", $recent_logs)) . '</pre>';
                        echo '</div>';
                    }
                }
                ?>
                
                <p style="margin-top: 15px;">
                    <strong>✅ If all show green checkmarks, the plugin is working!</strong><br>
                    If Google Sheets doesn't appear in Elementor:
                </p>
                <ol style="margin-left: 20px;">
                    <li>Enable WordPress debug logging (see instructions below)</li>
                    <li>Clear Elementor cache using button below</li>
                    <li>Reload this page to see debug logs</li>
                    <li>Check debug logs above for errors</li>
                </ol>
                
                <?php if (!defined('WP_DEBUG') || !WP_DEBUG): ?>
                <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                    <strong>⚠️ Debug logging is not enabled</strong><br>
                    To see detailed logs, add this to your wp-config.php:<br>
                    <code style="background: #f5f5f5; padding: 2px 6px; display: inline-block; margin-top: 5px;">
                        define('WP_DEBUG', true);<br>
                        define('WP_DEBUG_LOG', true);<br>
                        define('WP_DEBUG_DISPLAY', false);
                    </code>
                </div>
                <?php endif; ?>
                </div>
            </div>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('How to use:', 'elementor-forms-sheets'); ?></strong><br>
                    1. <?php _e('Set a default webhook URL below (optional)', 'elementor-forms-sheets'); ?><br>
                    2. <?php _e('Edit any page with a Form widget in Elementor', 'elementor-forms-sheets'); ?><br>
                    3. <?php _e('Click on the Form widget', 'elementor-forms-sheets'); ?><br>
                    4. <?php _e('Go to "Actions After Submit" tab', 'elementor-forms-sheets'); ?><br>
                    5. <?php _e('Look for "Google Sheets" in the actions list', 'elementor-forms-sheets'); ?><br>
                    6. <?php _e('If you don\'t see it, click the button below to clear Elementor cache', 'elementor-forms-sheets'); ?>
                </p>
            </div>
            
            <?php if ($has_workspace_tools) : ?>
                <h2><?php _e('Maintenance Tools', 'elementor-forms-sheets'); ?></h2>
                <table class="widefat striped" style="max-width: 900px; margin-bottom: 20px;">
                    <tbody>
                        <tr>
                            <td style="width: 240px;"><strong><?php _e('Workspace Source', 'elementor-forms-sheets'); ?></strong></td>
                            <td>
                                <?php echo is_dir($workspace_source) ? '✅ ' . esc_html($workspace_source) : '❌ ' . esc_html__('Not found', 'elementor-forms-sheets'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Release ZIP', 'elementor-forms-sheets'); ?></strong></td>
                            <td>
                                <?php echo file_exists($workspace_release) ? '✅ ' . esc_html($workspace_release) : '❌ ' . esc_html__('Not found', 'elementor-forms-sheets'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
                    <form method="post">
                        <?php wp_nonce_field('efgs_regenerate_elementor', 'efgs_regenerate_nonce'); ?>
                        <input type="hidden" name="regenerate_elementor" value="1">
                        <button type="submit" class="button button-secondary">
                            <?php _e('Clear Elementor Cache', 'elementor-forms-sheets'); ?>
                        </button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('efgs_sync_workspace', 'efgs_sync_workspace_nonce'); ?>
                        <input type="hidden" name="efgs_sync_workspace" value="1">
                        <button type="submit" class="button button-primary" <?php disabled(!is_dir($workspace_source)); ?>>
                            <?php _e('Sync Workspace to Live Plugin', 'elementor-forms-sheets'); ?>
                        </button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('efgs_clear_logs', 'efgs_clear_logs_nonce'); ?>
                        <input type="hidden" name="efgs_clear_logs" value="1">
                        <button type="submit" class="button button-secondary">
                            <?php _e('Clear Activity Logs', 'elementor-forms-sheets'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <hr>
            
            <form method="post" action="options.php">
                <?php settings_fields('efgs_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="efgs_default_webhook_url"><?php _e('Default Webhook URL', 'elementor-forms-sheets'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                name="efgs_default_webhook_url" 
                                id="efgs_default_webhook_url" 
                                value="<?php echo esc_attr(get_option('efgs_default_webhook_url')); ?>" 
                                class="large-text"
                                placeholder="https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec"
                            />
                            <p class="description">
                                <?php _e('Forms without a specific webhook URL will use this default.', 'elementor-forms-sheets'); ?>
                                <br>
                                <button
                                    type="button"
                                    class="button-link efgs-toggle-section"
                                    data-target="#webhook-setup-guide"
                                    aria-expanded="false"
                                    style="padding: 0; border: 0; background: transparent;"
                                >
                                    <?php _e('📖 Show Setup Guide', 'elementor-forms-sheets'); ?>
                                </button>
                            </p>
                            
                            <div id="webhook-setup-guide" style="display: none; margin-top: 15px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                                <h3 style="margin-top: 0;"><?php _e('Google Apps Script Setup', 'elementor-forms-sheets'); ?></h3>
                                <ol style="line-height: 1.8;">
                                    <li><?php _e('Open your Google Spreadsheet', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Click Extensions → Apps Script', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Delete default code and paste the code below', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Click Deploy → New deployment', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Type: Web app, Execute as: Me, Access: Anyone', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Click Deploy and authorize', 'elementor-forms-sheets'); ?></li>
                                    <li><?php _e('Copy the webhook URL and paste here', 'elementor-forms-sheets'); ?></li>
                                </ol>
                                
                                <h4><?php _e('Apps Script Code:', 'elementor-forms-sheets'); ?></h4>
                                <textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; padding: 10px;">
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheetName = data.sheetName || 'Sheet1';
    var sheet = ss.getSheetByName(sheetName);
    
    if (!sheet) {
      sheet = ss.insertSheet(sheetName);
    }
    
    var lastRow = sheet.getLastRow();
    if (lastRow === 0 && data.headers) {
      sheet.appendRow(data.headers);
    }
    
    if (data.values) {
      sheet.appendRow(data.values);
    }
    
    return ContentService.createTextOutput(JSON.stringify({
      'status': 'success',
      'message': 'Data added successfully',
      'row': sheet.getLastRow()
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({
      'status': 'error',
      'message': error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
                                </textarea>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="efgs_enable_logging"><?php _e('Enable Activity Logging', 'elementor-forms-sheets'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="efgs_enable_logging" 
                                    id="efgs_enable_logging" 
                                    value="1"
                                    <?php checked(get_option('efgs_enable_logging'), 1); ?>
                                />
                                <?php _e('Log all form submissions to Google Sheets', 'elementor-forms-sheets'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Logs will be stored in WordPress database for debugging.', 'elementor-forms-sheets'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php _e('Recent Activity', 'elementor-forms-sheets'); ?></h2>
            <?php if (empty($submission_logs)) : ?>
                <p><?php _e('No activity logs yet.', 'elementor-forms-sheets'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width: 1000px;">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Form', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Sheet', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Status', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Response', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Webhook', 'elementor-forms-sheets'); ?></th>
                            <th><?php _e('Message', 'elementor-forms-sheets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submission_logs as $log_entry) : ?>
                            <tr>
                                <td><?php echo esc_html(isset($log_entry['timestamp']) ? $log_entry['timestamp'] : ''); ?></td>
                                <td><?php echo esc_html(isset($log_entry['form_name']) ? $log_entry['form_name'] : ''); ?></td>
                                <td><?php echo esc_html(isset($log_entry['sheet_name']) ? $log_entry['sheet_name'] : ''); ?></td>
                                <td>
                                    <?php
                                    $status = isset($log_entry['status']) ? $log_entry['status'] : '';
                                    echo $status === 'success' ? '✅ ' . esc_html__('Success', 'elementor-forms-sheets') : '⚠️ ' . esc_html__('Error', 'elementor-forms-sheets');
                                    ?>
                                </td>
                                <td><?php echo esc_html(isset($log_entry['response_code']) && $log_entry['response_code'] !== null ? (string) $log_entry['response_code'] : ''); ?></td>
                                <td><?php echo esc_html(isset($log_entry['webhook_url']) ? $log_entry['webhook_url'] : ''); ?></td>
                                <td><?php echo esc_html(isset($log_entry['message']) ? $log_entry['message'] : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
            #webhook-setup-guide textarea {
                background: #282c34;
                color: #abb2bf;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.efgs-toggle-section').on('click', function() {
                var $button = $(this);
                var $target = $($button.data('target'));
                var isExpanded = $button.attr('aria-expanded') === 'true';

                $target.slideToggle(150);
                $button.attr('aria-expanded', isExpanded ? 'false' : 'true');
            });
        });
        </script>
        <?php
    }
}
}

// Initialize plugin
Elementor_Forms_Google_Sheets::instance();
