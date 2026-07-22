<?php
/**
 * Plugin Name: WPOSS(阿里云对象存储)
 * Plugin URI: https://www.lezaiyun.com/cloud-disk.html
 * Description: WordPress同步附件内容远程至阿里云OSS对象存储中，实现网站数据与静态资源分离，提高网站加载速度。微信公众号：  <font color="red">老蒋朋友圈</font>
 * Version: 5.0
 * Author: 老蒋
 * Author URI: https://www.laojiang.me
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>WPOSS</strong> 需要 PHP 7.4 或更高版本，当前版本：' . esc_html(PHP_VERSION) . '。请升级 PHP 后重新激活插件。</p></div>';
    });
    return;
}

use WPOSS\Api;

if (!class_exists('WPOSS')) {
    class WPOSS {

        private $option_name     = 'wposs_options';               // 插件参数保存名称
        private $menu_title      = 'WPOSS设置';                    // 设置菜单的菜单名
        private $page_title      = 'WPOSS设置';                    // 设置菜单的页面title
        private $capability      = 'manage_options';              // 设置页面管理所需权限
        private $version         = '5.0';                         // 插件数据版本， 每次修改应与上方的Version值相同
        private $setting_notices = [
                    'update_success' => '设置已保存',              // post数据保存成功时提示内容
                    'update_failed'  => '插件设置更新失败',  // 失败时提示
                ];
        private $image_display_default_value = 'image/auto-orient,1/quality,q_90/format,webp';  // 数据万象默认规则
        private $image_display_default_tab   = '?x-oss-process=';                                               // 万象规则url连字符

        private $base_folder;
        private $wp_upload_dir;
        private $object_storage;
        private $options;
        private static $pending_local_deletes = array();

        function __construct() {
            # 插件 activation 函数当一个插件在 WordPress 中”activated(启用)”时被触发。
            register_activation_hook(__FILE__, array($this, 'init_options'));
            register_deactivation_hook(__FILE__, array($this, 'restore_options'));  # 禁用时触发钩子

            $this->includes();
            $this->constants();

            # 避免上传插件/主题被同步到对象存储
            $req_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($req_uri, '/update.php') === false) {
                add_filter('wp_handle_upload', array($this, 'upload_attachments'));
                if ( version_compare(get_bloginfo('version'), 5.3, '<') ){
                    add_filter( 'wp_update_attachment_metadata', array($this, 'upload_and_thumbs') );
                } else {
                    add_filter( 'wp_generate_attachment_metadata', array($this, 'upload_and_thumbs') );
                    add_filter( 'wp_save_image_editor_file', array($this, 'save_image_editor_file') );
                }
            }

            if ($this->object_storage && $this->object_storage->is_client()) {
                # 检测不重复的文件名
                add_filter('wp_unique_filename', array($this, 'unique_filename') );
            }

            # 删除文件时触发删除远端文件，该删除会默认删除缩略图
            add_action('delete_attachment', array($this, 'delete_remote_attachment'));

            # 添加插件设置菜单
            add_action('admin_menu', array($this, 'admin_menu_setting'));
            add_filter('plugin_action_links', array($this, 'setting_plugin_action_links'), 10, 2);
            # 自动重命名
            add_filter( 'sanitize_file_name', array($this, 'sanitize_file_name_handler'), 10, 1 );
            # 图片显示处理
            add_filter( 'the_content', array($this, 'image_display_processing') );
        }

        private function includes() {
            require_once('api.php');
        }

        private function constants() {
            $this->base_folder = plugin_basename(dirname(__FILE__));
            $this->wp_upload_dir = wp_get_upload_dir();
            $this->options = get_option($this->option_name);
            if (!is_array($this->options)) {
                $this->init_options();
            }
            $this->object_storage = new Api(is_array($this->options) ? $this->options : array());
        }

        /**
         * 上传前确保使用最新配置（ajax 上传时可能使用与主页面不同的加载时机）
         */
        private function ensure_oss_client() {
            if ($this->object_storage && $this->object_storage->is_client()) {
                return;
            }
            $this->options = get_option($this->option_name);
            if (!is_array($this->options)) {
                $this->options = array();
            }
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                $hasCreds = !empty($this->options['accessKeyId']) && !empty($this->options['bucket']) && !empty($this->options['endpoint']);
                if (!$hasCreds) {
                    error_log('[WPOSS] ensure_oss_client: 配置缺失 accessKeyId/bucket/endpoint');
                }
            }
            $this->object_storage = new Api($this->options);
        }

        /**
         * 文件上传功能基础函数，被其它需要进行文件上传的模块调用
         * @param string $key 远端 Key（包含路径）
         * @param string $file_local_path 本地路径
         * @return bool
         */
        public function _file_upload($key, $file_local_path) {
            $this->ensure_oss_client();
            // 确保使用最新配置（ajax 上传等场景下 $this->options 可能未刷新）
            $this->options = get_option($this->option_name);
            if (!is_array($this->options)) {
                $this->options = array();
            }
            try {
                $this->object_storage->Upload(
                    $this->key_handler($key, get_option('upload_url_path')),
                    $file_local_path
                );
                return True;
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                    $errDetail = $e->getMessage();
                    if ($e instanceof \OSS\Core\OssException) {
                        $errDetail = 'OSS: ' . (method_exists($e, 'getHTTPStatus') ? 'HTTP=' . $e->getHTTPStatus() . ' ' : '')
                            . (method_exists($e, 'getErrorCode') ? 'Code=' . $e->getErrorCode() . ' ' : '') . $errDetail;
                    }
                    error_log('[WPOSS] 上传失败: ' . $errDetail . ' | key: ' . $key . ' | path: ' . $file_local_path);
                }
                return false;
            }
        }

        private function remote_key_exist($filename) {
            $subdir = isset($this->wp_upload_dir['subdir']) ? $this->wp_upload_dir['subdir'] : '';
            return $this->object_storage->hasExist($this->key_handler($subdir . '/' . $filename, get_option('upload_url_path') ?: ''));
        }

        /**
         * 删除远程附件（包括图片的原图）
         * @param int $post_id 附件 ID
         */
        public function delete_remote_attachment($post_id) {
            if (!$this->object_storage || !$this->object_storage->is_client()) {
                return;
            }
            // 获取要删除的对象Key的数组
            $deleteObjects = array();
            $meta = wp_get_attachment_metadata( $post_id );
            $upload_url_path = get_option('upload_url_path');

            if (isset($meta['file'])) {
                $attachment_key = $meta['file'];
                array_push($deleteObjects, $this->key_handler($attachment_key, $upload_url_path));
            } else {
                $file = get_attached_file( $post_id );
                $attached_key = str_replace( $this->wp_upload_dir['basedir'] . '/', '', $file );  # 不能以/开头
                $deleteObjects[] = $this->key_handler($attached_key, $upload_url_path);
            }

            if (isset($meta['sizes']) && count($meta['sizes']) > 0) {
                foreach ($meta['sizes'] as $val) {
                    $attachment_thumbs_key = dirname($meta['file']) . '/' . $val['file'];
                    $deleteObjects[] = $this->key_handler($attachment_thumbs_key, $upload_url_path);
                }
            }

            if ( !empty( $deleteObjects ) ) {
                // 执行删除远程对象
                $allKeys = array_chunk($deleteObjects, 1000);  # 每次最多删除1000个，多于1000循环进行
                foreach ($allKeys as $keys){
                    //删除文件, 每个数组1000个元素
                    $this->object_storage->Delete($keys);
                }
            }
        }

        // 初始化选项
        // TODO: 让不同对象存储适用相同参数与setting
        public function init_options() {
            $options = array(
                'version' => $this->version,  # 用于以后当有数据结构升级时初始化数据
                'bucket' => "",
                'endpoint' => "",
                'accessKeyId' => "",
                'accessKeySecret' => "",
                'no_local_file' => False,     # 不在本地保留备份
                'backup_url_path' => '',
                'cname' => False,             # true为开启CNAME。CNAME是指将自定义域名绑定到存储空间上。可以用来代替ENDPOINT
                'upload_information' => array(
                    'original' => array(
                        'upload_path' => '',
                        'upload_url_path' => '',
                    ),
                    'active' => array(
                        'upload_path' => '',
                        'upload_url_path' => '',
                    ),
                ),
                'opt' => array(
                    'auto_rename' => False,
                    'img_process' => array(
                        'switch' => False,
                        'style_value' => '',
                    ),
                ),
            );

            $this->options = $this->options ?? get_option($this->option_name);
            if (!$this->options || !is_array($this->options)) {
                if (add_option($this->option_name, $options, '', 'yes')) {
                    $this->options = get_option($this->option_name);
                }
            }

            if ( isset($this->options['backup_url_path']) && $this->options['backup_url_path'] != '' ) {
                update_option('upload_url_path', $this->options['backup_url_path']);
                // 理论上来说，更新完upload_url_path后，这里的option的backup_url_path还需要修改为'';
                // 但因为时机上目前只有激活与禁用2种，因此就由禁用时直接赋值，这里减少一次更新。
                // 后续出现多种场景判断再考虑。
            }
        }

        public function restore_options () {
            if (!is_array($this->options)) {
                $this->options = get_option($this->option_name) ?: array();
            }
            $this->options['backup_url_path'] = get_option('upload_url_path');
            if (update_option($this->option_name, $this->options)) {  // 此处修改的参数不影响对象存储实例
                $this->options = get_option($this->option_name);      // 上面的赋值及更新，这里似乎不用再重新获取。 - -!
            }
            update_option('upload_url_path', '');
        }

        /**
         * 此函数处理上传的key，用于支持 对象存储子目录
         * @param $key
         * @param $upload_url_path
         * @return string
         */
        private function key_handler($key, $upload_url_path) {
            $url_parse = wp_parse_url($upload_url_path ?: '');
            $url_parse = is_array($url_parse) ? $url_parse : array();
            # 约定url不要以/结尾，当 path 存在且非空时才拼接
            if (!empty($url_parse['path'])) {
                if ( substr($key, 0, 1) == '/' ) {
                    $key = $url_parse['path'] . $key;
                } else {
                    $key = $url_parse['path'] . '/' . $key;
                }
            }
            return ltrim((string) $key, '/');
        }

        /**
         * 将待删除路径加入队列，请求结束时统一删除（避免影响 WP 图片处理流程）
         * @param array $paths 本地文件路径数组
         */
        private function queue_local_deletes($paths) {
            $opts = get_option($this->option_name);
            if (empty($opts['no_local_file']) || !is_array($opts) || !is_array($paths)) {
                return;
            }
            foreach ($paths as $p) {
                if (is_string($p) && $p !== '' && file_exists($p)) {
                    self::$pending_local_deletes[$p] = true;
                }
            }
            if (!empty(self::$pending_local_deletes) && !has_action('shutdown', array($this, 'flush_pending_local_deletes'))) {
                add_action('shutdown', array($this, 'flush_pending_local_deletes'), 999);
            }
        }

        /**
         * 请求结束时执行：删除队列中的本地文件
         */
        public function flush_pending_local_deletes() {
            if (empty(self::$pending_local_deletes)) {
                return;
            }
            foreach (array_keys(self::$pending_local_deletes) as $path) {
                if (file_exists($path)) {
                    $this->delete_local_file($path);
                }
            }
            self::$pending_local_deletes = array();
        }

        /**
         * 删除本地文件
         * @param $file_path : 文件路径
         * @return bool
         */
        public function delete_local_file($file_path) {
            try {
                if (!is_string($file_path) || $file_path === '') {
                    return FALSE;
                }
                if (!file_exists($file_path)) {
                    return TRUE;
                }
                $udir = wp_get_upload_dir();
                $basedir = isset($udir['basedir']) ? trim($udir['basedir']) : '';
                if ($basedir === '') {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[WPOSS] delete_local_file: basedir 为空');
                    }
                    return FALSE;
                }
                $real = realpath($file_path);
                $base_real = realpath($basedir);
                if ($real === false || $base_real === false) {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[WPOSS] delete_local_file realpath 失败: file=' . $file_path . ' base=' . $basedir);
                    }
                    return FALSE;
                }
                $real_n = str_replace(array('\\', '/'), '/', $real);
                $base_n = str_replace(array('\\', '/'), '/', $base_real);
                $base_n = rtrim($base_n, '/');
                if (strpos($real_n, $base_n) !== 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[WPOSS] delete_local_file 路径不在 uploads 内: real=' . $real_n . ' base=' . $base_n);
                    }
                    return FALSE;
                }
                $after = substr($real_n, strlen($base_n), 1);
                if ($after !== '' && $after !== '/') {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[WPOSS] delete_local_file 路径校验失败 after=' . $after);
                    }
                    return FALSE;
                }
                if (!@unlink($file_path)) {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[WPOSS] delete_local_file unlink 失败(权限?): ' . $file_path);
                    }
                    return FALSE;
                }
                return TRUE;
            } catch (\Exception $ex) {
                if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                    error_log('[WPOSS] delete_local_file 异常: ' . $ex->getMessage());
                }
                return FALSE;
            }
        }

        /**
         * 上传图片及缩略图
         * @param $metadata: 附件元数据
         * @return array $metadata: 附件元数据
         * 官方的钩子文档上写了可以添加 $attachment_id 参数，但实际测试过程中部分wp接收到不存在的参数时会报错，上传失败，返回报错为“HTTP错误”
         */
        public function upload_and_thumbs( $metadata, $attachment_id = 0 ) {
            if (!isset($metadata['file'])) {
                return $metadata;
            }
            $udir = wp_get_upload_dir();
            $basedir = isset($udir['basedir']) ? rtrim($udir['basedir'], '/\\') : '';
            if ($basedir === '') {
                return $metadata;
            }
            # 1.先上传主图（优先从 attachment 获取实际路径，更可靠）
            $attachment_key = $metadata['file'];
            $main_file = $attachment_id ? wp_get_attached_file($attachment_id) : '';
            $attachment_local_path = ($main_file !== '' && file_exists($main_file)) ? $main_file : ($basedir . '/' . $attachment_key);
            $this->_file_upload($attachment_key, $attachment_local_path);

            # 2.上传缩略图
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $file_dir = $main_file ? dirname($main_file) : ($basedir . '/' . dirname($metadata['file']));
                foreach ($metadata['sizes'] as $val) {
                    if (!isset($val['file'])) {
                        continue;
                    }
                    $attachment_thumbs_key = dirname($metadata['file']) . '/' . $val['file'];
                    $thumbs_path = $file_dir . '/' . $val['file'];
                    $this->_file_upload($attachment_thumbs_key, $thumbs_path);
                }
            }
            # 3.勾选“不在本地保留”时，请求结束时删除本地文件
            $paths = array($attachment_local_path);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $file_dir = $main_file ? dirname($main_file) : ($basedir . '/' . dirname($metadata['file']));
                foreach ($metadata['sizes'] as $val) {
                    if (isset($val['file'])) {
                        $paths[] = $file_dir . '/' . $val['file'];
                    }
                }
            }
            $this->queue_local_deletes($paths);
            return $metadata;
        }

        /**
         * @param array  $upload {
         *     Array of upload data.
         *
         *     @type string $file Filename of the newly-uploaded file.
         *     @type string $url  URL of the uploaded file.
         *     @type string $type File type.
         * @return array  $upload
         */
        public function upload_attachments ($upload) {
            if (!is_array($upload) || !isset($upload['type'], $upload['file'])) {
                return $upload;
            }
            $mime_types       = get_allowed_mime_types();
            $image_mime_types = array(
                $mime_types['jpg|jpeg|jpe'] ?? '',
                $mime_types['gif'] ?? '',
                $mime_types['png'] ?? '',
                $mime_types['bmp'] ?? '',
                $mime_types['tiff|tif'] ?? '',
                $mime_types['ico'] ?? '',
            );
            if (!in_array($upload['type'], $image_mime_types)) {
                $udir = wp_get_upload_dir();
                $basedir = isset($udir['basedir']) ? $udir['basedir'] : '';
                $full = str_replace('\\', '/', $upload['file']);
                $base = $basedir !== '' ? str_replace('\\', '/', rtrim($basedir, '/\\')) : '';
                $key = $base !== '' && strpos($full, $base) === 0 ? ltrim(substr($full, strlen($base)), '/') : basename($upload['file']);
                $this->_file_upload($key, $upload['file']);
                $this->queue_local_deletes(array($upload['file']));
            }
            return $upload;
        }

        public function save_image_editor_file($override){
            add_filter( 'wp_update_attachment_metadata', array($this,'image_editor_file_save' ));
            return $override;
        }

        public function image_editor_file_save( $metadata ){
            $metadata = $this->upload_and_thumbs($metadata);
            remove_filter( 'wp_update_attachment_metadata', array($this, 'image_editor_file_save') );
            return $metadata;
        }

        /**
         * Filters the result when generating a unique file name.
         *
         * @since 4.5.0
         *
         * @param string        $filename                 Unique file name.

         * @return string New filename, if given wasn't unique
         *
         * 参数 $ext 在官方钩子文档中可以使用，部分 WP 版本因为多了这个参数就会报错。 返回“HTTP错误”
         */
        public function unique_filename( $filename ) {
            $ext = '.' . (is_string($filename) ? pathinfo($filename, PATHINFO_EXTENSION) : '');
            $number = '';

            while ( $this->remote_key_exist( $filename ) ) {
                $new_number = (int) $number + 1;
                if ( '' == "$number$ext" ) {
                    $filename = "$filename-" . $new_number;
                } else {
                    $filename = str_replace( array( "-$number$ext", "$number$ext" ), '-' . $new_number . $ext, $filename );
                }
                $number = $new_number;
            }
            return $filename;
        }

        public function sanitize_file_name_handler( $filename ){
            if (!empty($this->options['opt']['auto_rename']) && is_string($filename) && $filename !== '') {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                return date('YmdHis') . mt_rand(100, 999) . '.' . ($ext !== '' ? $ext : 'jpg');
            }
            return is_string($filename) ? $filename : '';
        }

        /** 根据提交数据进行缩略图设置修改与备份。 (暂时取消在这一步对插件参数更新的步骤，留到后面一起进行更新)
         * @param $options
         * @param $set_thumb
         * @return mixed
         */
        private function set_thumbsize_handler($options, $set_thumb){
            if($set_thumb) {
                $options['opt']['thumbsize'] = array(
                    'thumbnail_size_w' => get_option('thumbnail_size_w'),
                    'thumbnail_size_h' => get_option('thumbnail_size_h'),
                    'medium_size_w'    => get_option('medium_size_w'),
                    'medium_size_h'    => get_option('medium_size_h'),
                    'large_size_w'     => get_option('large_size_w'),
                    'large_size_h'     => get_option('large_size_h'),
                    'medium_large_size_w' => get_option('medium_large_size_w'),
                    'medium_large_size_h' => get_option('medium_large_size_h'),
                );
                update_option('thumbnail_size_w', 0);
                update_option('thumbnail_size_h', 0);
                update_option('medium_size_w', 0);
                update_option('medium_size_h', 0);
                update_option('large_size_w', 0);
                update_option('large_size_h', 0);
                update_option('medium_large_size_w', 0);
                update_option('medium_large_size_h', 0);
            } else {
                if(isset($options['opt']['thumbsize'])) {
                    update_option('thumbnail_size_w', $options['opt']['thumbsize']['thumbnail_size_w']);
                    update_option('thumbnail_size_h', $options['opt']['thumbsize']['thumbnail_size_h']);
                    update_option('medium_size_w', $options['opt']['thumbsize']['medium_size_w']);
                    update_option('medium_size_h', $options['opt']['thumbsize']['medium_size_h']);
                    update_option('large_size_w', $options['opt']['thumbsize']['large_size_w']);
                    update_option('large_size_h', $options['opt']['thumbsize']['large_size_h']);
                    update_option('medium_large_size_w', $options['opt']['thumbsize']['medium_large_size_w']);
                    update_option('medium_large_size_h', $options['opt']['thumbsize']['medium_large_size_h']);
                    unset($options['opt']['thumbsize']);
                }
            }
            return $options;
        }

        private function legacy_data_replace() {
            if(in_array(get_option('upload_path'), ["", "wp-content/uploads"])){
                global $wpdb;
                $originalContent = home_url('/wp-content/uploads');
                $newContent = get_option('upload_url_path') ?: '';

                # 文章内容文字/字符替换（使用 prepare 防止 SQL 注入）
                $result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}posts SET post_content = REPLACE(post_content, %s, %s)",
                        $originalContent,
                        $newContent
                    )
                );

                $this->options['opt'] = isset($this->options['opt']) ? $this->options['opt'] : array();
                $this->options['opt']['legacy_data_replace'] = 1;  # 值为1 表示已完成替换
            } else {
                $this->options['opt'] = isset($this->options['opt']) ? $this->options['opt'] : array();
                $this->options['opt']['legacy_data_replace'] = 2;  # 值为2 表示upload_path非初始默认值，无法替换，建议使用wpreplace插件替换
            }
            update_option($this->option_name, $this->options);  // 文字替换，参数变动不影响Api实例
            return $this->options;
        }

        public function image_display_processing($content){
            if (!empty($this->options['opt']['img_process']['switch'])) {
                $media_url = get_option('upload_url_path') ?: '';
                $style_value = $this->options['opt']['img_process']['style_value'] ?? '';
                $pattern = '#<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>#ims';
                $content = preg_replace_callback(
                    $pattern,
                    function($matches) use ($media_url, $style_value) {
                        if ($media_url === '' || strpos($matches[1], $media_url) === false) {
                            return $matches[0];
                        } else {
                            return str_replace(
                                $matches[1],
                                $matches[1] . $this->image_display_default_tab . $style_value,
                                $matches[0]);
                        }
                    },
                    $content);
            }
            return $content;
        }

        private function set_img_process_handle($options, $img_process){
            $options['opt'] = isset($options['opt']) ? $options['opt'] : array();
            $options['opt']['img_process'] = isset($options['opt']['img_process']) ? $options['opt']['img_process'] : array('switch' => false, 'style_value' => '');
            if (!empty($img_process['img_process_switch'])) {
                $options['opt']['img_process']['switch'] = true;
                $choice = isset($img_process['img_process_style_choice']) ? sanitize_text_field(trim(stripslashes($img_process['img_process_style_choice']))) : '0';
                $options['opt']['img_process']['style_value'] = ($choice === '1' && isset($img_process['img_process_style_customize']))
                    ? sanitize_text_field(trim(stripslashes($img_process['img_process_style_customize'])))
                    : $this->image_display_default_value;
            } else {
                $options['opt']['img_process']['switch'] = false;
            }
            return $options;
        }

        // 在插件列表页添加设置按钮
        public function setting_plugin_action_links($links, $file) {
            if ($file == plugin_basename(dirname(__FILE__) . '/index.php')) {
                $links[] = '<a href="admin.php?page=' . $this->base_folder . '/index.php">设置</a>';
            }
            return $links;
        }

        // 在导航栏“设置”中添加条目
        public function admin_menu_setting() {
            add_options_page($this->page_title, $this->menu_title, $this->capability, __FILE__, array($this, 'setting_page'));
        }

        /**
         *  插件设置页面
         */
        public function setting_page() {
            // 如果当前用户权限不足
            if (!current_user_can( $this->capability )) wp_die('Insufficient privileges!');

            $this->options = get_option($this->option_name);
            if (!is_array($this->options)) {
                $this->options = array('bucket' => '', 'endpoint' => '', 'accessKeyId' => '', 'accessKeySecret' => '', 'opt' => array(), 'no_local_file' => false, 'cname' => false);
            }
            $this->options['opt'] = isset($this->options['opt']) ? $this->options['opt'] : array();
            $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : (isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '');
            $nonce_ok = $nonce && wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'wposs_settings');
            if ($nonce_ok && !empty($_POST)) {
                if (isset($_POST['type']) && $_POST['type'] === 'info_set') {
                    $this->options['bucket'] = isset($_POST['bucket']) ? sanitize_text_field(trim(stripslashes($_POST['bucket']))) : '';
                    $this->options['endpoint'] = isset($_POST['endpoint']) ? sanitize_text_field(trim(stripslashes($_POST['endpoint']))) : '';
                    $this->options['accessKeyId'] = isset($_POST['accessKeyId']) ? sanitize_text_field(trim(stripslashes($_POST['accessKeyId']))) : '';
                    $this->options['accessKeySecret'] = isset($_POST['accessKeySecret']) ? sanitize_text_field(trim(stripslashes($_POST['accessKeySecret']))) : '';
                    $this->options['opt']['auto_rename'] = isset($_POST['auto_rename']);
                    $this->options['no_local_file'] = isset($_POST['no_local_file']);

                    $this->options = $this->set_img_process_handle($this->options, $_POST);  // 更新数据万象设置，返回options，但未调用update_option
                    $this->options = $this->set_thumbsize_handler($this->options, isset($_POST['disable_thumb']) );

                    $upload_url = isset($_POST['upload_url_path']) ? esc_url_raw(trim(stripslashes($_POST['upload_url_path']))) : '';
                    update_option('upload_url_path', $upload_url);
                    update_option($this->option_name, $this->options);
                    $this->object_storage = new Api($this->options);
                    # 原本想做update_option判断，但内容不改变时返回值为0，会当作失败处理，从业务逻辑上不合理。
                    ?>
                        <div class="notice notice-success settings-error is-dismissible"><p><?php echo esc_html($this->setting_notices['update_success']); ?></p></div>
                    <?php

                } else if ($_POST['type'] == 'info_replace') {
                    $this->options = $this->legacy_data_replace();
                }
            }
            require_once('setting.php');
        }
    }

    global $WPOSS;
    $WPOSS = new WPOSS();
}
