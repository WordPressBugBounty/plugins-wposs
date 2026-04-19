=== WPOSS阿里云对象存储 ===
Contributors: laobuluo
Donate link: https://www.lezaiyun.com/donate/
Tags:阿里云oss,oss,对象存储,wordpress oss
Requires at least: 5.5
Tested up to: 6.9.1
Stable tag: 5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress阿里云对象存储插件（简称:WPOSS），基于阿里云OSS对象存储与WordPress实现静态资源到OSS存储。支持阿里云OSS图片编辑，水印、裁剪、压缩等。

== Description ==

WordPress阿里云对象存储插件（简称:WPOSS），基于阿里云OSS对象存储与WordPress实现静态资源到OSS存储。支持阿里云OSS图片编辑，水印、裁剪、压缩等。

## 插件特点

1. 新增支持图像自定义处理 设置水印、编辑图片、压缩WEBP等
2. 支持已有图片编辑功能
3. 提高上传速度
4. 支持一键替换静态本地化至对象存储远程URL
5. 支持一键禁止缩略图
6. 支持自定义任意对象存储目录，一个存储桶可以多网站
7. 支持自动文件重命名
8. 支持本地和对象存储分离和同步
9. 2020年重构代码改变传统逻辑模型

阿里云对象存储插件安装方法：[https://www.lezaiyun.com/?p=1095](https://www.lezaiyun.com/?p=1095)

## 网站支持

* [老蒋玩运营](https://www.laojiang.me/ "老蒋玩运营")

* [主机评价网](https://www.zhujipingjia.com/ "主机评价网")

* 欢迎加入插件和站长微信公众号：老蒋朋友圈（公众号）

== Installation ==

* 1、把wposs文件夹上传到/wp-content/plugins/目录下<br />
* 2、在后台插件列表中激活wposs<br />
* 3、在【设置】-【WPOSS设置】菜单中输入阿里云OSS云存储相关信息和API信息<br />
* 4、我们可以在编辑文章的时候将静态资源上传到阿里云OSS以及本地备份。

== Frequently Asked Questions ==

* 1.当发现插件出错时，开启调试获取错误信息。
* 2.我们可以选择备份OSS或者本地同时备份。
* 3.支持HTTPS以及自定义域名。

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Changelog ==

= 5.0 =
* 修复「不在本地保留备份」功能：勾选后于请求结束时删除本地文件，不再影响上传流程
* 修复 legacy_data_replace 的 SQL 注入风险
* 修复 PHP 8.x 兼容性（Exception 命名空间、restore_options、sanitize_file_name_handler 等）
* 修复 OSS 上传失败时的异常处理
* 增加表单安全校验（wp_nonce_field、esc_html 等）
* 精简设置页：移除图像处理、一键替换 OSS 地址等选项
* 移动端隐藏「关注公众号」侧栏

= 4.9 =
* 微调样式
* 解决媒体无法删除问题（用户手动替换最新SDK）

= 4.8 =
* 兼容WP6.2测试
* 解决PHP8.x激活报错问题

= 4.7 =
* 兼容WP6.1测试

= 4.6 =
* 兼容WP5.9.3测试

= 4.5 =
* 兼容WP5.7测试
* 兼容支持PHP8.0

= 4.4 =
* 新版本WP5.6调试兼容

= 4.3 =
* 新版本WP调试兼容
* 文档说明调整
* 细节调优

= 4.2 =
* 重构插件前端 引入前端样式
* 修复细节说明文档
* 自定义目录调优

= 4.1 =
* 定稿2020新架构功能完善
* 调整部分说明文档
* 提高处理速度 修复上传速度BUG

= 3.0 =
* 兼容WordPress5.4.2测试
* 重构代码代码逻辑更合理轻便
* 新增图片处理，包括裁剪、压缩、格式转化、水印等
* 新增禁止缩略图
* 新增自动重命名等
* 调整对象存储URL格式，合并成一条网址可自定义目录

= 1.0.2 =
* 兼容WordPress5.4.1测试

= 1.0.1 =
* 通过1年测试WPCOS比较稳定正式版本发布
* 重构CSS样式极简风格
* 兼容WordPress5.4版本
* 计划重构核心代码提高上传速度

= 0.3 =
* 修复"停用"和"插件"后恢复原WP的目录文件夹
* 解决用户安装与主题冲突空白问题

= 0.2 =
* 根据WP官方发布要求进行修改函数匹配和安全。
* 第一次提交WP官方平台，需要修改适配WP官方插件要求。

= 0.1 =
* WPOSS正式发布。
* 本插件经过几周的测试，支持最新的WordPress程序，现予以发布。

== Upgrade Notice ==

= 5.0 =
修复「不在本地保留备份」功能，提升安全性与 PHP 8 兼容性，建议升级。 