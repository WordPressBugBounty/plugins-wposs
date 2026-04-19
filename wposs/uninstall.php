<?php
/**
 * WPOSS 插件卸载脚本
 * 恢复 WordPress 上传路径为默认值，兼容旧版 upload_information 及新版 backup_url_path
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
	// 如果 uninstall 不是从 WordPress 调用，则退出
	exit();
}

$wposs_options = get_option('wposs_options');

if (is_array($wposs_options)) {
	// 恢复为 WordPress 默认本地存储（空字符串表示使用 wp-content/uploads）
	$upload_path = '';
	$upload_url_path = '';

	// 优先使用 backup_url_path（停用时保存的当前 upload_url_path）
	if (!empty($wposs_options['backup_url_path'])) {
		$upload_url_path = $wposs_options['backup_url_path'];
	}
	// 兼容旧版本：若存在 upload_information.original 则使用其保存的原始值
	if (!empty($wposs_options['upload_information']['original']['upload_path'])) {
		$upload_path = $wposs_options['upload_information']['original']['upload_path'];
	}
	if (empty($upload_url_path) && isset($wposs_options['upload_information']['original']['upload_url_path'])) {
		$upload_url_path = $wposs_options['upload_information']['original']['upload_url_path'];
	}

	update_option('upload_path', $upload_path);
	update_option('upload_url_path', $upload_url_path);
}

// 从 options 表删除选项
delete_option('wposs_options');
