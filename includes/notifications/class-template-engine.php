<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Template_Engine {
    private $template_path;
    private $cache_path;
    private $cache_enabled = false;

    public function __construct() {
        $this->template_path = SANDCRIME_PLUGIN_DIR . 'templates/';
        $this->cache_path = WP_CONTENT_DIR . '/cache/sandcrime/templates/';

        if (wp_is_writable(WP_CONTENT_DIR . '/cache')) {
            $this->cache_enabled = true;
            $this->ensure_cache_directory();
        }
    }

    public function render($template_name, $data = array()) {
        $template_file = $this->get_template_file($template_name);
        
        if (!file_exists($template_file)) {
            throw new Exception("Template file not found: {$template_name}");
        }

        if ($this->cache_enabled) {
            return $this->render_cached($template_file, $data);
        }

        return $this->render_template($template_file, $data);
    }

    private function render_cached($template_file, $data) {
        $cache_key = $this->generate_cache_key($template_file, $data);
        $cache_file = $this->cache_path . $cache_key . '.php';

        if ($this->is_cache_valid($cache_file, $template_file)) {
            return $this->include_template($cache_file, $data);
        }

        $content = $this->render_template($template_file, $data);
        $this->cache_template($cache_file, $content);

        return $content;
    }

    private function render_template($template_file, $data) {
        ob_start();
        $this->include_template($template_file, $data);
        return ob_get_clean();
    }

    private function include_template($template_file, $data) {
        // Extract data to make variables available in template
        extract($data);
        
        // Include template file
        include $template_file;
    }

    private function get_template_file($template_name) {
        // Check for theme override
        $theme_template = get_stylesheet_directory() . '/sandcrime/' . $template_name;
        if (file_exists($theme_template)) {
            return $theme_template;
        }

        // Fall back to plugin template
        return $this->template_path . $template_name;
    }

    private function generate_cache_key($template_file, $data) {
        $template_hash = md5_file($template_file);
        $data_hash = md5(serialize($data));
        return sprintf('%s_%s', basename($template_file, '.php'), $template_hash . $data_hash);
    }

    private function is_cache_valid($cache_file, $template_file) {
        if (!file_exists($cache_file)) {
            return false;
        }

        $cache_time = filemtime($cache_file);
        $template_time = filemtime($template_file);

        return $cache_time > $template_time;
    }

    private function cache_template($cache_file, $content) {
        file_put_contents($cache_file, $content, LOCK_EX);
    }

    private function ensure_cache_directory() {
        if (!file_exists($this->cache_path)) {
            wp_mkdir_p($this->cache_path);
        }
    }

    public function clear_cache() {
        if (!$this->cache_enabled) {
            return;
        }

        $files = glob($this->cache_path . '*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}