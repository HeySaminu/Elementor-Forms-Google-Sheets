<?php
/**
 * Elementor Google Sheets Form Action
 * 
 * Adds Google Sheets as an action after submit in Elementor Forms
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base')) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('EFGS Action: ElementorPro Action_Base class not available');
    }
    return;
}

class Elementor_Forms_Google_Sheets_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {
    /**
     * Mask webhook URL before storing it in logs.
     */
    private function mask_webhook_url($webhook_url) {
        $parts = wp_parse_url($webhook_url);

        if (empty($parts['host'])) {
            return '';
        }

        $masked = $parts['scheme'] . '://' . $parts['host'];

        if (!empty($parts['path'])) {
            $path_segments = array_values(array_filter(explode('/', trim($parts['path'], '/'))));
            if (!empty($path_segments)) {
                $last_segment = end($path_segments);
                $masked .= '/.../' . substr($last_segment, 0, 8);
            }
        }

        return $masked;
    }

    /**
     * Detect Google Apps Script web app URLs.
     */
    private function is_google_apps_script_webhook($webhook_url) {
        $parts = wp_parse_url($webhook_url);

        if (empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return $host === 'script.google.com' || $host === 'script.googleusercontent.com';
    }

    /**
     * Google Apps Script may process the POST successfully before returning a redirect/non-2xx response.
     */
    private function is_successful_delivery($webhook_url, $response, $status_code) {
        if ($status_code >= 200 && $status_code < 300) {
            return true;
        }

        if (!$this->is_google_apps_script_webhook($webhook_url)) {
            return false;
        }

        $location = wp_remote_retrieve_header($response, 'location');

        if ($status_code >= 300 && $status_code < 400 && !empty($location)) {
            $location_parts = wp_parse_url($location);
            $location_host = !empty($location_parts['host']) ? strtolower($location_parts['host']) : '';

            return $location_host === 'script.googleusercontent.com';
        }

        return false;
    }
    
    /**
     * Check whether plugin logging is enabled
     */
    private function is_logging_enabled() {
        return (bool) get_option('efgs_enable_logging');
    }

    /**
     * Store a submission log entry
     */
    private function log_submission($data) {
        if (!$this->is_logging_enabled()) {
            return;
        }

        $default_entry = [
            'timestamp' => current_time('mysql'),
            'form_name' => '',
            'sheet_name' => '',
            'webhook_url' => '',
            'status' => 'unknown',
            'fields_count' => 0,
            'message' => '',
            'response_code' => null,
        ];

        $log_entry = array_merge($default_entry, $data);
        $logs = get_option('efgs_logs', []);

        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 100); // Keep last 100

        update_option('efgs_logs', $logs);
    }
    
    /**
     * Get action name
     */
    public function get_name() {
        return 'google_sheets';
    }
    
    /**
     * Get action label
     */
    public function get_label() {
        return __('Google Sheets', 'elementor-forms-sheets');
    }
    
    /**
     * Register action controls (settings in Elementor editor)
     */
    public function register_settings_section($widget) {
        $widget->start_controls_section(
            'section_google_sheets',
            [
                'label' => __('Google Sheets', 'elementor-forms-sheets'),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );
        
        // Webhook URL
        $widget->add_control(
            'google_sheets_webhook_url',
            [
                'label' => __('Webhook URL', 'elementor-forms-sheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec',
                'description' => __('Google Apps Script webhook URL. Leave empty to use default from plugin settings.', 'elementor-forms-sheets'),
                'label_block' => true,
            ]
        );
        
        // Sheet Name
        $widget->add_control(
            'google_sheets_sheet_name',
            [
                'label' => __('Sheet Tab Name', 'elementor-forms-sheets'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'Sheet1',
                'default' => 'Sheet1',
                'description' => __('Name of the sheet tab where data will be added. Tab will be created if it doesn\'t exist.', 'elementor-forms-sheets'),
            ]
        );
        
        // Include Field IDs
        $widget->add_control(
            'google_sheets_include_metadata',
            [
                'label' => __('Include Metadata', 'elementor-forms-sheets'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'description' => __('Include submission date/time and form name in the sheet.', 'elementor-forms-sheets'),
            ]
        );
        
        // Help Text
        $widget->add_control(
            'google_sheets_help',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_help_html(),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );
        
        $widget->end_controls_section();
    }
    
    /**
     * Get help HTML for the settings panel
     */
    private function get_help_html() {
        $default_webhook = get_option('efgs_default_webhook_url');
        $has_default = !empty($default_webhook);
        
        $html = '<div style="line-height: 1.6;">';
        
        if ($has_default) {
            $html .= '<p><strong>✓ Default webhook is configured</strong></p>';
            $html .= '<p>Leave webhook URL empty to use the default, or enter a different URL for a different spreadsheet.</p>';
        } else {
            $html .= '<p><strong>⚠ No default webhook configured</strong></p>';
            $html .= '<p>Please enter a webhook URL above or set a default in <a href="' . admin_url('options-general.php?page=elementor-forms-sheets') . '" target="_blank">plugin settings</a>.</p>';
        }
        
        $html .= '<hr style="margin: 10px 0;">';
        $html .= '<p><strong>How it works:</strong></p>';
        $html .= '<ol style="margin: 0; padding-left: 20px;">';
        $html .= '<li>User submits this form</li>';
        $html .= '<li>Data is sent to your Google Sheet</li>';
        $html .= '<li>Headers are added automatically (first submission)</li>';
        $html .= '<li>Each submission creates a new row</li>';
        $html .= '</ol>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Run action (when form is submitted)
     */
    public function run($record, $ajax_handler) {
        $settings = $record->get('form_settings');
        
        // Get webhook URL
        $webhook_url = !empty($settings['google_sheets_webhook_url']) 
            ? $settings['google_sheets_webhook_url'] 
            : get_option('efgs_default_webhook_url');

        $webhook_url = esc_url_raw(trim((string) $webhook_url), array('https'));
        $masked_webhook_url = $this->mask_webhook_url($webhook_url);
        
        if (empty($webhook_url)) {
            $ajax_handler->add_error_message(__('Google Sheets webhook URL is not configured.', 'elementor-forms-sheets'));
            return;
        }

        if (!wp_http_validate_url($webhook_url) || stripos($webhook_url, 'https://') !== 0) {
            $ajax_handler->add_error_message(__('Google Sheets webhook URL must be a valid HTTPS URL.', 'elementor-forms-sheets'));
            return;
        }
        
        // Get sheet name
        $sheet_name = !empty($settings['google_sheets_sheet_name']) 
            ? $settings['google_sheets_sheet_name'] 
            : 'Sheet1';
        
        // Get form fields
        $raw_fields = $record->get('fields');
        $form_name = $record->get_form_settings('form_name');
        $include_metadata = isset($settings['google_sheets_include_metadata']) && $settings['google_sheets_include_metadata'] === 'yes';
        
        // Prepare headers and values
        $headers = [];
        $values = [];
        
        // Add metadata if enabled
        if ($include_metadata) {
            $headers[] = 'Timestamp';
            $headers[] = 'Form Name';
            $values[] = current_time('Y-m-d H:i:s');
            $values[] = $form_name;
        }
        
        // Add form fields
        foreach ($raw_fields as $field_id => $field) {
            $headers[] = $field['title'];
            
            // Handle different field types
            if (is_array($field['value'])) {
                // Multiple values (checkboxes, etc.)
                $values[] = implode(', ', $field['value']);
            } else {
                $values[] = $field['value'];
            }
        }
        
        // Prepare payload
        $payload = [
            'sheetName' => $sheet_name,
            'headers' => $headers,
            'values' => $values,
        ];
        
        $request_args = [
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
            'sslverify' => true,
        ];

        if ($this->is_google_apps_script_webhook($webhook_url)) {
            // Apps Script commonly acknowledges a successful POST with a redirect.
            $request_args['redirection'] = 0;
        }

        // Send to Google Sheets
        $response = wp_remote_post($webhook_url, $request_args);
        
        // Handle response
        if (is_wp_error($response)) {
            if ($this->is_logging_enabled()) {
                error_log('Elementor Forms Google Sheets Error: ' . $response->get_error_message());
            }

            $this->log_submission([
                'form_name' => $form_name,
                'sheet_name' => $sheet_name,
                'webhook_url' => $masked_webhook_url,
                'status' => 'error',
                'fields_count' => count($raw_fields),
                'message' => $response->get_error_message(),
            ]);

            $ajax_handler->add_error_message(__('Unable to send this submission to Google Sheets right now.', 'elementor-forms-sheets'));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);

        if (!$this->is_successful_delivery($webhook_url, $response, $status_code)) {
            if ($this->is_logging_enabled()) {
                error_log('Elementor Forms Google Sheets Error: HTTP ' . $status_code);
            }

            $this->log_submission([
                'form_name' => $form_name,
                'sheet_name' => $sheet_name,
                'webhook_url' => $masked_webhook_url,
                'status' => 'error',
                'fields_count' => count($raw_fields),
                'message' => 'HTTP ' . $status_code,
                'response_code' => $status_code,
            ]);

            $ajax_handler->add_error_message(__('Google Sheets rejected this submission. Please try again later.', 'elementor-forms-sheets'));
            return;
        }
        
        $this->log_submission([
            'form_name' => $form_name,
            'sheet_name' => $sheet_name,
            'webhook_url' => $masked_webhook_url,
            'status' => 'success',
            'fields_count' => count($raw_fields),
            'message' => 'Submission delivered successfully.',
            'response_code' => $status_code,
        ]);
    }
    
    /**
     * On export (when form is exported/imported)
     */
    public function on_export($element) {
        // Remove webhook URL on export for security
        unset($element['google_sheets_webhook_url']);
        return $element;
    }
}
