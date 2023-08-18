<?php
/*
Plugin Name: 微信公众平台验证码查看插件
Plugin URI: https://gitcafe.net/
Description: 微信公众平台验证码可见插件，接口地址为：域名?wxcaptcha  ,其实随便
Version: 1.0
Author: 云落
Author URI: https://gitcafe.net/
*/

// 此token必须和微信公众平台中的设置保持一致
define('WX_TOKEN', '');
define('WX_QR', 'https://p.ssl.qhimg.com/t0162cc8398cbf7dea3.jpg');//公众号二维码

// 以下内容不需要改动

/***  微信端开始 ***/
class Wechat_Captcha {
	function __construct($wx_captcha) {
		$this->captcha = $wx_captcha;
	}
	private function checkSignature() {
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		$token = WX_TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		if ($tmpStr == $signature)
		            return true; else
		            return false;
	}
	protected function valid() {
		$echoStr = $_GET["echostr"];
		//valid signature , option
		if ($this->checkSignature()) {
			echo $echoStr;
			exit;
		} else {
			echo 'error signature';
		}
	}
	public function responseMsg() {
		//如果是验证请求,则执行签名验证并退出
		if (!empty($_GET["echostr"])) {
			$this->valid();
			//验证签名是否有效
			return;
			//返回退出
		}
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			echo '';
			return;
		}
		//如果不是验证请求，则
		//首先，取得POST原始数据(XML格式)
		//$postData = $GLOBALS["HTTP_RAW_POST_DATA"];
		$postData = file_get_contents('php://input');
		if (empty($postData)) {
			echo '';
			return;
		}
		//如果没有POST数据，则退出
		if (!empty($postData)) {
			//解析POST数据(XML格式)
			$object = simplexml_load_string($postData, 'SimpleXMLElement', LIBXML_NOCDATA);
			$messgeType = trim($object->MsgType);//取得消息类型
			$this->fromUser = "" . $object->FromUserName;
			$this->toUser = "" . $object->ToUserName;
			$keyword = trim($object->Content);
			if( $messgeType == 'text' && $keyword == '验证码') {
				$response_content = '您的验证码为：【'.$this->captcha.'】，验证码有效期为2分钟，请抓紧使用，过期需重新申请';
				$xmlTemplate = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[text]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    <FuncFlag>%d</FuncFlag>
                    </xml>";
				$xmlText = sprintf($xmlTemplate, $this->fromUser, $this->toUser, time(), $response_content, 0);
				echo $xmlText;
			}
		} else {
			echo "";
			exit;
		}
	}
}
//class end

//生成微信验证码
function wx_captcha(){
	date_default_timezone_set('Asia/Shanghai');
	$min = floor(date("i")/2);
	$day = date("d");
	$day = ltrim($day,0);
	$url = home_url();
	$captcha = sha1($min.$url.WX_TOKEN);
	$captcha = substr($captcha , $day , 6);
	return $captcha;

}


function wx_process() {
	if(isset($_GET["signature"])) {
		global $object;
		if(!isset($object)) {
			$object = new Wechat_Captcha(wx_captcha());
			$object->responseMsg();
			exit;
		}
	}
}
add_action('parse_request', 'wx_process', 4);
/***  微信端结束 ***/



/***  WP端开始 ***/
//密码可见
function wx_captcha_view() {
	$action = $_POST['action'];
	$post_id = $_POST['id'];
	$pass = $_POST['pass'];
	$wxcaptcha = wx_captcha();
	if(!isset( $action )  ||  !isset( $post_id )  ||  !isset( $pass )   ) exit('400');
	if($pass == $wxcaptcha ) {
	$pass_content = get_post_meta($post_id, '_pass_content')[0];
	exit($pass_content);
	}else{
		exit('400');
	}
}
add_action('wp_ajax_nopriv_gdk_pass_view', 'wx_captcha_view');
add_action('wp_ajax_gdk_pass_view', 'wx_captcha_view');


// 部分内容输入密码可见
function gdk_secret_view($atts, $content = null) {
    $pid = get_the_ID();
    add_post_meta($pid, '_pass_content', $content, true) or update_post_meta($pid, '_pass_content', $content);
    if ( current_user_can( 'administrator' ) ) { return $content; }//admin show
        return '<link rel="stylesheet" id="pure_css-css"  href="https://cdn.jsdelivr.net/npm/css-mint@2.0.7/build/css-mint.min.css?ver=0.0.1" type="text/css"/>
		<div class="cm-grid cm-card pass_viewbox">
   <div class="cm-row">
      <div class="cm-col-md-4">
         <img src="'.WX_QR.'" class="cm-resp-img">
      </div>
      <div class="cm-col-md-8">
         <div class="hide_content_info" style="margin:10px 0">
			<div class="cm-alert primary">本段内容已被隐藏，您需要扫码关注微信公众号申请验证码查看，发送【验证码】获取验证码，验证码2分钟有效</div>
		<input type="text" id="pass_view" placeholder="输入验证码并提交" style="width:70%"> &nbsp;&nbsp;<input id="submit_pass_view" class="cm-btn success" data-action="gdk_pass_view" data-id="'.$pid.'" type="button" value="提交">
         </div>
      </div>
   </div>
</div>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery@2.1.0/dist/jquery.min.js?ver=2.1"></script>
<script>
	function show_hide_content(a, b) {
		$(a).hide();
		$(a).after("<div class=\"cm-alert success\">" + b + "</div>");
	}
	/**
	 * 点击开启密码可见
	 */
	$("#submit_pass_view").click(function () {
		var ajax_data = {
			action: $("#submit_pass_view").data("action"),
			id: $("#submit_pass_view").data("id"),
			pass: $("#pass_view").val()
		};
		$.post("'.admin_url( 'admin-ajax.php' ).'", ajax_data, function (c) {
			c = $.trim(c);
			if (c !== "400") {
				show_hide_content(".pass_viewbox", c);
				localStorage.setItem("gdk_pass_" + ajax_data["id"], c); /**隐藏内容直接存入浏览器缓存,下次直接读取,ps.有个问题,内容更新会略坑,不管了 */
			} else {
				alert("您的密码错误，请重新申请");
			}
		});
	});


	/**
	 * 已经密码可见的自动从浏览器读取内容
	 * 并显示,这里加个延时处理
	 */
	 
	(function () {
		if ($("#submit_pass_view").length > 0) { /**如果网站有密码可见,就执行 */
			setTimeout(function () {
				var id = "gdk_pass_" + $("#submit_pass_view").data("id"),
					length = localStorage.length;
				for (var i = 0; i < length; i++) {
					var key = localStorage.key(i),
						value = localStorage.getItem(key);
					if (key.indexOf(id) >= 0) { /*发现目标 */
						show_hide_content(".pass_viewbox", value);
						break;
					}
				}

			}, 900);
		}
	}());

	/**密码可见end */
</script>
';

}
add_shortcode('wxcaptcha', 'gdk_secret_view');

//按钮
function wx_captcha_btn() {
	?>
	<script type="text/javascript">
	QTags.addButton( 'wxcaptcha', '微信公众号验证码可见', '[wxcaptcha]', '[/wxcaptcha]\n' );//快捷输入标签
	</script>
	<?php
}
add_action('after_wp_tiny_mce', 'wx_captcha_btn');

/***  WP端结束 ***/