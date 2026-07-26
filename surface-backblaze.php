<?php
/**
 * Plugin Name: Surface Backblaze
 * Description: Surface Infrastructure storage layer for Backblaze B2. Handles auth, upload, delete, and public asset URLs.
 * Version: 1.1.0
 * Author: KX
 */

if (!defined('ABSPATH')) exit;

final class Surface_Backblaze {

    const OPT_KEY_ID      = 'surface_b2_key_id';
    const OPT_APP_KEY     = 'surface_b2_app_key';
    const OPT_BUCKET_ID   = 'surface_b2_bucket_id';
    const OPT_BUCKET_NAME = 'surface_b2_bucket_name';
    const OPT_PUBLIC_BASE = 'surface_b2_public_base_url';

   public static function boot() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('wp_ajax_surface_b2_get_browser_upload', [__CLASS__, 'ajax_get_browser_upload'], 5);
    add_action('wp_ajax_nopriv_surface_b2_get_browser_upload', [__CLASS__, 'ajax_get_browser_upload'], 5);

   }

    public static function admin_menu() {
        add_options_page(
            'Surface Backblaze',
            'Surface Backblaze',
            'manage_options',
            'surface-backblaze',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings() {
        register_setting('surface_backblaze_group', self::OPT_KEY_ID);
        register_setting('surface_backblaze_group', self::OPT_APP_KEY);
        register_setting('surface_backblaze_group', self::OPT_BUCKET_ID);
        register_setting('surface_backblaze_group', self::OPT_BUCKET_NAME);
        register_setting('surface_backblaze_group', self::OPT_PUBLIC_BASE);
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;

        $key_id      = esc_attr(get_option(self::OPT_KEY_ID, ''));
        $app_key     = esc_attr(get_option(self::OPT_APP_KEY, ''));
        $bucket_id   = esc_attr(get_option(self::OPT_BUCKET_ID, ''));
        $bucket_name = esc_attr(get_option(self::OPT_BUCKET_NAME, ''));
        $public_base = esc_attr(get_option(self::OPT_PUBLIC_BASE, ''));

        echo '<div class="wrap">';
        echo '<h1>Surface Backblaze</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('surface_backblaze_group');

        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="'.self::OPT_KEY_ID.'">Key ID</label></th><td>';
        echo '<input type="text" name="'.self::OPT_KEY_ID.'" id="'.self::OPT_KEY_ID.'" value="'.$key_id.'" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="'.self::OPT_APP_KEY.'">Application Key</label></th><td>';
        echo '<input type="password" name="'.self::OPT_APP_KEY.'" id="'.self::OPT_APP_KEY.'" value="'.$app_key.'" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="'.self::OPT_BUCKET_ID.'">Bucket ID</label></th><td>';
        echo '<input type="text" name="'.self::OPT_BUCKET_ID.'" id="'.self::OPT_BUCKET_ID.'" value="'.$bucket_id.'" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="'.self::OPT_BUCKET_NAME.'">Bucket Name</label></th><td>';
        echo '<input type="text" name="'.self::OPT_BUCKET_NAME.'" id="'.self::OPT_BUCKET_NAME.'" value="'.$bucket_name.'" class="regular-text" />';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="'.self::OPT_PUBLIC_BASE.'">Public Base URL (optional)</label></th><td>';
        echo '<input type="text" name="'.self::OPT_PUBLIC_BASE.'" id="'.self::OPT_PUBLIC_BASE.'" value="'.$public_base.'" class="regular-text" />';
        echo '<p class="description">Optional CDN/custom public base URL. Leave empty to use Backblaze download URL.</p>';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Save Backblaze Settings');
        echo '</form>';

        echo '<hr />';
        echo '<h2>Connection Check</h2>';

        $test = self::authorize_account();
        if (is_wp_error($test)) {
            echo '<div style="background:#b91c1c;color:#fff;padding:10px;border-radius:8px;max-width:760px;">';
            echo 'Connection failed: '.esc_html($test->get_error_message());
            echo '</div>';
        } else {
            echo '<div style="background:#065f46;color:#fff;padding:10px;border-radius:8px;max-width:760px;">';
            echo 'Connection OK. API URL: '.esc_html($test['apiUrl']).' | Download URL: '.esc_html($test['downloadUrl']);
            echo '</div>';
        }

        echo '</div>';
        echo '<hr />';
echo '<h2>Browser Upload Config Test</h2>';
echo '<p><button type="button" id="surface-b2-browser-test" class="button button-primary">Test Browser Upload Config</button></p>';
echo '<pre id="surface-b2-browser-test-result" style="background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;max-width:900px;white-space:pre-wrap;word-break:break-word;"></pre>';

echo '<script>
document.addEventListener("DOMContentLoaded", function(){
  var btn = document.getElementById("surface-b2-browser-test");
  var out = document.getElementById("surface-b2-browser-test-result");
  if (!btn || !out) return;

  var fileInput = document.createElement("input");
  fileInput.type = "file";
  fileInput.style.display = "none";
  document.body.appendChild(fileInput);

  btn.addEventListener("click", function(){
    fileInput.click();
  });

  fileInput.addEventListener("change", function(){
    if (!fileInput.files.length) return;

    var file = fileInput.files[0];
    out.textContent = "Preparing upload...";

    var body = new URLSearchParams();
    body.append("action", "surface_b2_get_browser_upload");
    body.append("folder", "surface-test");
    body.append("filename", file.name);
    body.append("filesize", file.size);
    body.append("surface_type", "market");

    fetch(ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
      },
      body: body.toString()
    })
    .then(function(r){ return r.json(); })
    .then(function(cfg){
      if (!cfg.success) {
        out.textContent = "CONFIG ERROR:\\n\\n" + JSON.stringify(cfg, null, 2);
        return;
      }

      var data = cfg.data;
      out.textContent = "Uploading to Backblaze...";

      return fetch(data.upload_url, {
        method: "POST",
        headers: {
          "Authorization": data.auth_token,
          "X-Bz-File-Name": encodeURIComponent(data.remote_name),
          "Content-Type": file.type || "b2/x-auto",
          "X-Bz-Content-Sha1": "do_not_verify"
        },
        body: file
      })
      .then(function(r){ return r.json(); })
      .then(function(uploadRes){
        out.textContent = "UPLOAD SUCCESS\\n\\n" + JSON.stringify({
          file: file.name,
          url: data.public_url,
          b2: uploadRes
        }, null, 2);
      });
    })
    .catch(function(err){
      out.textContent = "ERROR: " + err;
    });
  });
});
</script>';
    }
public static function ajax_get_browser_upload() {
    $upload = self::get_upload_url();
    if (is_wp_error($upload)) {
        wp_send_json_error([
            'message' => $upload->get_error_message(),
        ], 500);
    }

    $s = self::get_settings();

    if (empty($s['bucket_name'])) {
        wp_send_json_error([
            'message' => 'Backblaze bucket name is missing.',
        ], 500);
    }

    $folder = isset($_POST['folder']) ? sanitize_text_field(wp_unslash($_POST['folder'])) : 'surface';
    $user_id = get_current_user_id();
    $filesize = isset($_POST['filesize']) ? absint($_POST['filesize']) : 0;
    $surface_type = isset($_POST['surface_type'])
        ? sanitize_key(wp_unslash($_POST['surface_type']))
        : '';

    $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';

    if ($filename === '') {
        wp_send_json_error([
            'message' => 'Filename is required.',
        ], 400);
    }

    $bandwidth = [
        'chargeable'   => false,
        'surface_type' => '',
        'charged_mb'   => 0,
    ];

    if (class_exists('SurfaceInfrastructure')) {
        $bandwidth = SurfaceInfrastructure::prepare_upload_charge(
            $user_id,
            $filesize,
            $surface_type,
            $folder
        );

        if (is_wp_error($bandwidth)) {
            wp_send_json_error([
                'message' => $bandwidth->get_error_message(),
                'code'    => $bandwidth->get_error_code(),
            ], 400);
        }
    }

    $remote_name = trim($folder, '/');
    if ($remote_name !== '') {
        $remote_name .= '/';
    }
    $remote_name .= $filename;
    $remote_name = self::normalize_remote_name($remote_name);

    wp_send_json_success([
        'upload_url'   => $upload['uploadUrl'],
        'auth_token'   => $upload['authorizationToken'],
        'bucket_name'  => $s['bucket_name'],
        'download_url' => $upload['downloadUrl'],
        'remote_name'  => $remote_name,
        'public_url'   => self::build_public_url($remote_name, $upload['downloadUrl']),
        'bandwidth'    => $bandwidth,
    ]);
}
    public static function get_settings() {
        return [
            'key_id'      => trim((string) get_option(self::OPT_KEY_ID, '')),
            'app_key'     => trim((string) get_option(self::OPT_APP_KEY, '')),
            'bucket_id'   => trim((string) get_option(self::OPT_BUCKET_ID, '')),
            'bucket_name' => trim((string) get_option(self::OPT_BUCKET_NAME, '')),
            'public_base' => trim((string) get_option(self::OPT_PUBLIC_BASE, '')),
        ];
    }

    public static function authorize_account() {
        $s = self::get_settings();

        if (empty($s['key_id']) || empty($s['app_key'])) {
            return new WP_Error('b2_missing_credentials', 'Backblaze credentials are missing.');
        }

        $response = wp_remote_get(
            'https://api.backblazeb2.com/b2api/v2/b2_authorize_account',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($s['key_id'] . ':' . $s['app_key']),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('b2_auth_http', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return new WP_Error('b2_auth_failed', 'Backblaze authorization failed.');
        }

        if (empty($json['authorizationToken']) || empty($json['apiUrl']) || empty($json['downloadUrl'])) {
            return new WP_Error('b2_auth_invalid', 'Backblaze authorization response was incomplete.');
        }

        return [
            'authorizationToken' => $json['authorizationToken'],
            'apiUrl'              => $json['apiUrl'],
            'downloadUrl'         => $json['downloadUrl'],
        ];
    }

    public static function get_upload_url() {
        $auth = self::authorize_account();
        if (is_wp_error($auth)) return $auth;

        $s = self::get_settings();
        if (empty($s['bucket_id'])) {
            return new WP_Error('b2_missing_bucket_id', 'Backblaze bucket ID is missing.');
        }

        $endpoint = untrailingslashit($auth['apiUrl']) . '/b2api/v2/b2_get_upload_url';

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => $auth['authorizationToken'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'bucketId' => $s['bucket_id'],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('b2_upload_url_http', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return new WP_Error('b2_upload_url_failed', 'Could not get Backblaze upload URL.');
        }

        if (empty($json['uploadUrl']) || empty($json['authorizationToken'])) {
            return new WP_Error('b2_upload_url_invalid', 'Backblaze upload URL response was incomplete.');
        }

        return [
            'uploadUrl'          => $json['uploadUrl'],
            'authorizationToken' => $json['authorizationToken'],
            'downloadUrl'        => $auth['downloadUrl'],
        ];
    }

    public static function upload_file($args) {
        $file_path = isset($args['file_path']) ? (string) $args['file_path'] : '';
        $file_name = isset($args['file_name']) ? (string) $args['file_name'] : '';
        $mime      = isset($args['mime']) ? (string) $args['mime'] : '';

        if (!$file_path || !file_exists($file_path)) {
            return new WP_Error('b2_missing_file', 'Upload file path is missing or invalid.');
        }

        if ($file_name === '') {
            $file_name = basename($file_path);
        }

        $file_name = self::normalize_remote_name($file_name);

        if ($mime === '') {
            $mime = function_exists('mime_content_type') ? mime_content_type($file_path) : 'application/octet-stream';
        }
        if (!$mime) {
            $mime = 'application/octet-stream';
        }

        $upload = self::get_upload_url();
        if (is_wp_error($upload)) return $upload;

        $contents = file_get_contents($file_path);
        if ($contents === false) {
            return new WP_Error('b2_read_failed', 'Could not read upload file.');
        }

        $sha1 = sha1_file($file_path);
        if (!$sha1) {
            return new WP_Error('b2_sha1_failed', 'Could not hash upload file.');
        }

        $response = wp_remote_post(
            $upload['uploadUrl'],
            [
                'timeout' => 300,
                'headers' => [
                    'Authorization'        => $upload['authorizationToken'],
                    'X-Bz-File-Name'       => rawurlencode($file_name),
                    'Content-Type'         => $mime,
                    'Content-Length'       => (string) filesize($file_path),
                    'X-Bz-Content-Sha1'    => $sha1,
                    'X-Bz-Info-Author'     => 'Surface',
                ],
                'body' => $contents,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('b2_upload_http', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return new WP_Error('b2_upload_failed', 'Backblaze upload failed.');
        }

        if (empty($json['fileId']) || empty($json['fileName'])) {
            return new WP_Error('b2_upload_invalid', 'Backblaze upload response was incomplete.');
        }

        $public_url = self::build_public_url($json['fileName'], $upload['downloadUrl']);

        return [
            'file_id'   => $json['fileId'],
            'file_name' => $json['fileName'],
            'url'       => $public_url,
            'size'      => isset($json['contentLength']) ? (int) $json['contentLength'] : (int) filesize($file_path),
            'mime'      => $mime,
            'sha1'      => $sha1,
        ];
    }

    public static function delete_file($file_name, $file_id = '') {
        $auth = self::authorize_account();
        if (is_wp_error($auth)) return $auth;

        if (!$file_name || !$file_id) {
            return new WP_Error('b2_delete_missing', 'Backblaze delete requires both file_name and file_id.');
        }

        $endpoint = untrailingslashit($auth['apiUrl']) . '/b2api/v2/b2_delete_file_version';

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => $auth['authorizationToken'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'fileName' => $file_name,
                    'fileId'   => $file_id,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('b2_delete_http', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json)) {
            return new WP_Error('b2_delete_failed', 'Backblaze delete failed.');
        }

        return true;
    }

    public static function build_asset_meta($upload_result) {
        return [
            'provider'   => 'backblaze',
            'file_id'    => isset($upload_result['file_id']) ? $upload_result['file_id'] : '',
            'file_name'  => isset($upload_result['file_name']) ? $upload_result['file_name'] : '',
            'url'        => isset($upload_result['url']) ? $upload_result['url'] : '',
            'size'       => isset($upload_result['size']) ? (int) $upload_result['size'] : 0,
            'mime'       => isset($upload_result['mime']) ? $upload_result['mime'] : '',
            'sha1'       => isset($upload_result['sha1']) ? $upload_result['sha1'] : '',
        ];
    }

    private static function normalize_remote_name($name) {
        $name = str_replace('\\', '/', $name);
        $parts = array_filter(array_map('trim', explode('/', $name)), 'strlen');
        $parts = array_map(function($part) {
            return sanitize_file_name($part);
        }, $parts);

        return implode('/', $parts);
    }

    private static function build_public_url($file_name, $download_url) {
        $s = self::get_settings();

        if (!empty($s['public_base'])) {
            return trailingslashit($s['public_base']) . ltrim($file_name, '/');
        }

        if (empty($s['bucket_name'])) {
            return '';
        }

        return untrailingslashit($download_url) . '/file/' . rawurlencode($s['bucket_name']) . '/' . str_replace('%2F', '/', rawurlencode($file_name));
    }
}

Surface_Backblaze::boot();
