<style>
	.wpossform .layui-form-label{width:120px;}
	.wpossform .layui-input{width: 350px;}
	.wpossform .layui-form-mid{margin-left:3.5%;}
	.laobuluo-wp-hidden {position: relative;}
	.laobuluo-wp-hidden .laobuluo-wp-eyes{padding: 5px;position:absolute;top:3px;z-index: 999;display: none;}
	.laobuluo-wp-hidden i{font-size:20px;}
	.laobuluo-wp-hidden i.dashicons-visibility{color:#009688 ;}
	@media (max-width: 992px) { .laobuluo-sidebar-qrcode { display: none !important; } }
</style>
<link rel='stylesheet'  href='<?php echo plugin_dir_url( __FILE__ );?>layui/css/layui.css' />
<link rel='stylesheet'  href='<?php echo plugin_dir_url( __FILE__ );?>layui/css/laobuluo.css'/>
<script src='<?php echo plugin_dir_url( __FILE__ );?>layui/layui.js'></script>

<div class="container-laobuluo-main">
   <div class="laobuluo-wbs-header" style="margin-bottom: 15px;">
             <div class="laobuluo-wbs-logo"><span class="wbs-span">WPOSS - 阿里云对象存储插件</span><span class="wbs-free">Free V5.0</span></div>
            <div class="laobuluo-wbs-btn">
                 <a class="layui-btn layui-btn-primary" href="https://www.lezaiyun.com/cloud-disk.html" target="_blank"><i class="layui-icon layui-icon-home"></i> 插件主页</a>
                 <a class="layui-btn layui-btn-primary" href="https://www.lezaiyun.com/contact/" target="_blank"><i class="layui-icon layui-icon-release"></i> 技术支持</a>
            </div>
       </div>
 </div>
 
<!-- 内容 -->
<div class="container-laobuluo-main">
       <div class="layui-container container-m">
           <div class="layui-row layui-col-space15">
			    <!-- 左边 -->
			    <div class="layui-col-md9">
					 <div class="laobuluo-panel">
						  <div class="laobuluo-controw">
							   <fieldset class="layui-elem-field layui-field-title site-title">
							       <legend><a name="get">设置选项</a></legend>
							   </fieldset>
							   <form class="layui-form wpossform" action="<?php echo esc_url(admin_url('admin.php?page=' . $this->base_folder . '/index.php')); ?>" name="wposs_form" method="post">
							   <?php wp_nonce_field('wposs_settings', '_wpnonce'); ?>
							         <div class="layui-form-item">
										  <label class="layui-form-label">Bucket 名称</label>
										  <div class="layui-input-block">
											    <input type="text"  class="layui-input"  name="bucket" value="<?php echo esc_attr($this->options['bucket'] ?? ''); ?>" size="50" placeholder="BUCKET"/>
												<div class="layui-form-mid layui-word-aux">我们需要在 <a href="https://oss.console.aliyun.com/overview" target="_blank" style="text-decoration:underline">阿里云OSS控制台</a> 创建
                                                <code>Bucket</code> ，再填写以上内容。 比如：<span class="layui-badge layui-bg-green">laojiang</span></div>
										  </div>
									 </div>
									 <div class="layui-form-item">
										 <label class="layui-form-label">EndPoint 地域节点</label>
										 <div class="layui-input-block">
											 <input type="text"  class="layui-input" name="endpoint" value="<?php echo esc_attr($this->options['endpoint'] ?? ''); ?>" size="50" placeholder="示范：oss-cn-shanghai.aliyuncs.com"/>
											 <div class="layui-form-mid layui-word-aux" style="line-height:30px;">
											 1. 我们在创建Bucket之后，在[概况]</span>中，可以看到 EndPoint 地域节点</br>
											 2. 如果我们的WordPress部署在非阿里云服务器，则输入[外网访问]对应的EndPoint节点</br>
											 3. 如果使用的是[ECS 的经典网络访问]或者[ECS 的 VPC 网络访问]则对应的EndPoint节点</br>
											 </div>
										 </div>
									 </div>
									 <div class="layui-form-item">
										  <label class="layui-form-label">Bucket 域名</label>
										  <div class="layui-input-block">
											   <input type="text" class="layui-input" name="upload_url_path" value="<?php echo esc_url(get_option('upload_url_path') ?: ''); ?>" size="60" placeholder="请输入Bucket域名（或者/）自定义目录"/>
											   <div class="layui-form-mid layui-word-aux" style="line-height:30px;">
											   	1. URL前缀的格式为 <code>{http或https}://{bucket} 域名地址</code>/<code><font color="red">可自定义目录</font></code>（最后不加斜杠）</br>
											    2. 示范A： <code>https://laojiang.oss-cn-shanghai.aliyuncs.com</code></br>
											   	3. 示范B： <code>http://oss.laojiang.me/<font color="red">laojiang</font></code></br>
											   </div>
										  </div>
									 </div>
									 <div class="layui-form-item">
										  <label class="layui-form-label">AccessKey ID</label>
										  <div class="layui-input-block">
											  <div class="laobuluo-wp-hidden">
											   <input type="password" class="layui-input" name="accessKeyId" value="<?php echo esc_attr($this->options['accessKeyId'] ?? ''); ?>" size="50" placeholder="AccessKeyId"/>
										       <span class="laobuluo-wp-eyes"><i class="dashicons dashicons-hidden"></i></span>
											  </div>
										  </div>
									 </div>
									 <div class="layui-form-item">
										   <label class="layui-form-label">AccessKey Secret</label>
										   <div class="layui-input-block">
											    <div class="laobuluo-wp-hidden">
											     <input type="password"class="layui-input"  name="accessKeySecret" value="<?php echo esc_attr($this->options['accessKeySecret'] ?? ''); ?>" size="50" placeholder="AccessKeySecret"/>
										         <span class="laobuluo-wp-eyes"><i class="dashicons dashicons-hidden"></i></span>
												 </div>
												 <div class="layui-form-mid layui-word-aux">AccessKey API需要我们参考教程中获取当前账户的API信息，然后填写。</div>
										   </div>
									 </div>
									 <div class="layui-form-item">
										  <label class="layui-form-label">自动重命名</label>
										  <div class="layui-input-inline" style="width:60px;">
											   <input type="checkbox" name="auto_rename" title="设置"
											        <?php
											            if (isset($this->options['opt']['auto_rename']) and $this->options['opt']['auto_rename']) {
											                 echo 'checked="TRUE"';
											             }
											        ?>
											    />
										  </div>
										  <div class="layui-form-mid layui-word-aux">上传文件自动重命名，解决中文文件名或者重复文件名问题</div>
									 </div>
									 <div class="layui-form-item">
										 <label class="layui-form-label">不在本地保留备份</label>
										 <div class="layui-input-inline" style="width:60px;">
											   <input type="checkbox" name="no_local_file" title="设置"
												   <?php
														if (!empty($this->options['no_local_file'])) {
															echo 'checked="TRUE"';
														}
												   ?>
												/>
										 </div>
										 <div class="layui-form-mid layui-word-aux">如果我们只需要将图片等静态文件上传放置OSS中，则勾选。</div>
									 </div>
									 <div class="layui-form-item">
										  <label class="layui-form-label">禁止缩略图</label>
										  <div class="layui-input-inline" style="width:60px;">
											   <input type="checkbox" name="disable_thumb" title="禁止"
											        <?php
											            if (isset($this->options['opt']['thumbsize'])) {
											                echo 'checked="TRUE"';
											            }
											        ?>
											    />
										  </div>
										  <div class="layui-form-mid layui-word-aux">仅生成和上传主图，禁止缩略图裁剪。</div>
									 </div>
									 <div class="layui-form-item">
										 <label class="layui-form-label"></label>
									 	  <div class="layui-input-block">
									 		    <button class="layui-btn" type="submit" name="submit" value="保存设置" lay-submit lay-filter="formDemo">保存设置</button>
									 	  </div>
									 </div>
									 <input type="hidden" name="type" value="info_set">
							   </form>
						  </div>
					 </div>
				</div>
			     <!-- 左边 -->
				 <!-- 右边  -->
				 <div class="layui-col-md3">
				 	 <div id="nav">
				 		 <div class="laobuluo-panel laobuluo-sidebar-qrcode">
                        <div class="laobuluo-panel-title">关注公众号</div>
                        <div class="laobuluo-code">
                            <img src="<?php echo plugin_dir_url(__FILE__); ?>layui/images/qrcode.png">
                            <p>微信扫码关注 <span class="layui-badge layui-bg-blue">老蒋朋友圈</span> 公众号</p>
                            <p><span class="layui-badge">优先</span> 获取插件更新 和 更多 <span class="layui-badge layui-bg-green">免费插件</span> </p>
                        </div>
                    </div>

                   
				 	 </div>
				 </div>
				 <!-- 右边 end -->
		   </div>
	</div>
</div>
<!-- 内容 -->
<!-- footer -->
   <div class="container-laobuluo-main">
	   <div class="layui-container container-m">
		   <div class="layui-row layui-col-space15">
			   <div class="layui-col-md12">
				<div class="laobuluo-footer-code">
					 <span class="codeshow"></span>
				</div>
				<div class="laobuluo-links">
                    <a href="https://www.lezaiyun.com/"  target="_blank">乐在云工作室</a>
                    <a href="https://www.zhujipingjia.com/pianyivps.html" target="_blank">便宜VPS推荐</a>
                    <a href="https://www.zhujipingjia.com/hkcn2.html" target="_blank">香港VPS推荐</a>
                    <a href="hhttps://www.zhujipingjia.com/uscn2gia.html" target="_blank">美国VPS推荐</a>
                </div>
			   </div>
		   </div>
	   </div>
   </div>
   <!-- footer -->
   <script>
	    layui.use(['form', 'element','jquery'], function() {
			var $ =layui.jquery;
			var form = layui.form;
			function menuFixed(id) {
			  var obj = document.getElementById(id);
			  var _getHeight = obj.offsetTop;
			  var _Width= obj.offsetWidth
			  window.onscroll = function () {
			    changePos(id, _getHeight,_Width);
			  }
			}
			function changePos(id, height,width) {
			  var obj = document.getElementById(id);
			  obj.style.width = width+'px';
			  var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
			  var _top = scrollTop-height;
			  if (_top < 150) {
			    var o = _top;
			    obj.style.position = 'relative';
			    o = o > 0 ? o : 0;
			    obj.style.top = o +'px';
			    
			  } else {
			    obj.style.position = 'fixed';
			    obj.style.top = 50+'px';
			
			  }
			}
			menuFixed('nav');
			
			var laobueys = $('.laobuluo-wp-hidden')
					  
			laobueys.each(function(){
						   
				var inpu = $(this).find('.layui-input');
				var eyes = $(this).find('.laobuluo-wp-eyes')
				var width = inpu.outerWidth(true);
				eyes.css('left',width+'px').show();
						   
				eyes.click(function(){
					if(inpu.attr('type') == "password"){
					   inpu.attr('type','text')
			           eyes.html('<i class="dashicons dashicons-visibility"></i>')
					}else{
						inpu.attr('type','password')
						eyes.html('<i class="dashicons dashicons-hidden"></i>')
					}
				})
			})
			
		})
   </script>