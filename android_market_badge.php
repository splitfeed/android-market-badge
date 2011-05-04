<?php
/*
Plugin Name: Android Market Badges
Plugin URI: http://www.splitfeed.net/market-badges/
Feed URI:
Description:
Version: 0.7.4
Author: Niklas Nilsson
Author URI: http://www.splitfeed.net
*/
/**
 *
 * @todo Move all settings to prefixed names
 * @todo Add more comments
 */
class AndroidAppBadge {
	private $loggedIn = false;
	private $config = array();
	private $session = null;

	public function init() {
		load_plugin_textdomain('android-market-badge', false, 'android-market-badge/lang');
		$this->readConfig();

		add_filter('the_content', array(&$this, 'compatFallback'));
		add_filter('comment_text', array(&$this, 'compatFallback'));

		add_shortcode('app', array(&$this, 'parseApp'));

		if ($this->config["qr"]["active"] == 1) {
			add_shortcode('qr', array(&$this, 'parseQR'));
		}


		add_filter('admin_init', array(&$this, 'adminInit'));
		add_filter('admin_menu', array(&$this, 'adminMenu'));
	}


	/**
	 * Loads the config with default values into a property
	 */
	private function readConfig() {
		$this->config["qr"]		= get_option('qr', array("active" => 0));
		$this->config["badge"]	= get_option('badge', array(
			"cache"	=> 60,
			"url"	=> "http://www.cyrket.com/p/android/%s/"
		));
		$this->config["google"]	= get_option('google', array());
	}

	/**
	 * Add "App Badges" link in admin menu
	 *
	 */
	function adminMenu() {
		add_submenu_page('plugins.php', __('Android Market', 'android-market-badge'), __('App Badges', 'android-market-badge'), 'manage_options', 'android_app_options', array(&$this, 'settingsPage'));
	}

	function adminInit() {
		add_settings_section('market', __('Android Market', 'android-market-badge'), array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('google_login', __('Google Login', 'android-market-badge'), array(&$this, 'optionCallbackLogin'), 'android_app', 'market');
		add_settings_field('google_password', __('Google Password', 'android-market-badge'), array(&$this, 'optionCallbackPassword'), 'android_app', 'market');
		add_settings_field('google_device', __('Android Device ID', 'android-market-badge'), array(&$this, 'optionCallbackDevice'), 'android_app', 'market');

		add_settings_section('badge', __('Badge settings', 'android-market-badge'), array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('badge_cache', __('Cache time', 'android-market-badge'), array(&$this, 'optionCallbackCache'), 'android_app', 'badge');
		add_settings_field('badge_design', __('Badge design', 'android-market-badge'), array(&$this, 'optionCallbackDesign'), 'android_app', 'badge');
		add_settings_field('badge_url', __('Badge link URL', 'android-market-badge'), array(&$this, 'optionCallbackBadgeLink'), 'android_app', 'badge');

		add_settings_section('qr', __('QR-BBCodes', 'android-market-badge'), array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('qr_active', __('Enable QR-BBCode', 'android-market-badge'), array(&$this, 'optionCallbackQR'), 'android_app', 'qr');


		register_setting('android_app', 'google'); //, 'android_app_validate');
		register_setting('android_app', 'badge'); //, 'android_app_validate');
		register_setting('android_app', 'qr'); //, 'android_app_validate');
	}

	function parseQR($atts, $content = null, $code = "") {
		if ($content)  {
			if (isset($atts["size"]) && is_numeric($atts["size"])) {
				$qrSize	= $atts["size"];
			} else {
				$qrSize	= 3;
			}
			$urlPre = "http://qrcode.kaywa.com/img.php?s={$qrSize}&d=";
			return "<img src=\"".$urlPre.urlencode("market://details?id=".$content)."\" title=\"".$content."\"/>";
		}
	}

	function parseApp($atts, $content = null, $code = "") {
		if ($content) {
			$pname	= $content;
			$badge	= $this->getBadge($pname);

			//Link to market if browsing from app
			$android	= stripos($_SERVER["HTTP_USER_AGENT"], "Android") !== false;
			if ($android) {
				$link		= "market://search?q=pname:".$pname;
			} else {
				$link		= sprintf($this->config["badge"]["url"], urlencode($pname));
			}

			if ($badge) {
				return '<a href="'.$link.'" target="_blank"><img src="'.$badge.'" alt="'.$pname.'"/></a>';
			} else {
				return '<a href="'.$link.'" target="_blank">'.__('Link to', 'android-market-badge').' '.$pname.'</a>';
			}
		}
	}

	function getBadge($pname) {
		#$pname		= strtolower($pname);
		$design		= $this->config["badge"]["design"];

		//Fallback to an existing design if the badge was removed
		if (!file_exists("badges/{$design}/badge.php")) $design = "default";

		$cacheAge	= $this->config["badge"]["cache"] * 60;
		$cachePath	= "/".basename(dirname(__FILE__))."/cache/";
		$cacheFile = $cachePath.$pname."_{$design}.png";

		//If no cached file exists or it is older than $cacheAge, get a new one
		$success = false;
		if (!file_exists(WP_PLUGIN_DIR.$cacheFile) || time() - filemtime(WP_PLUGIN_DIR.$cacheFile) >= $cacheAge) {
			if (!$this->loggedIn) {
				include_once("market/protocolbuffers.inc.php");
				include_once("market/market.proto.php");
				include_once("market/MarketSession.php");
				include_once("badges/{$design}/badge.php");

				$this->session = new MarketSession();
				$this->session->login($this->config["google"]["login"], $this->config["google"]["password"]);
				$this->session->setAndroidId($this->config["google"]["device"]);

				$this->loggedIn = true;
			}

			$func	= "android_app_badge_{$design}";
			$image	= $func($this->session, $pname);

			if ($image !== false) {
				$success = @file_put_contents(WP_PLUGIN_DIR.$cacheFile, $image) !== false;
			}
		} else {
			$success = true;
		}

		if ($success === true) {
			return WP_PLUGIN_URL.$cacheFile;
		} else {
			return false;
		}
	}

	/**
	 * Handle old shortcode format
	 *
	 * @param string $content Post or comment text
	 */
	function compatFallback($content) {
		$content = preg_replace("/\[qr=([.\w]*)\]/", "[qr]$1[/qr]", $content);
		$content = preg_replace("/\[app=([.\w]*)\]/", "[app]$1[/app]", $content);

		return $content;
	}

	/**
	* Adds settings/options page
	*/
	function settingsPage() {
		//must check that the user has the required capability
		if (!current_user_can('manage_options')) {
			wp_die( __('You do not have sufficient permissions to access this page.', 'android-market-badge') );
		}

		if ($_POST['google'] || $_POST['badge']) {
			update_option("google", $_POST["google"]);
			update_option("badge", $_POST["badge"]);
			update_option("qr", $_POST["qr"]);

			$this->readConfig();
		}

		?>
		<div class="wrap">
		<h2>Android Market Badges</h2>
		<?php
		$cachePath	= WP_PLUGIN_DIR."/".basename(dirname(__FILE__))."/cache/";
		if (!is_writable($cachePath)) {
			echo "<div class=\"error\">".sprintf(__("The folder %s must be writable", 'android-market-badge')," ".$cachePath)."</div>";
		}
		?>
		<form method="post" action="">
			<?php settings_fields('google_group'); ?>
			<?php do_settings_sections('android_app'); ?>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'android-market-badge') ?>"  /></p>
		</form>
		</div>
		<?php
	}

	function sectionCallback() {
		//echo '<p>Intro text for our settings section</p>';
	}

	function optionCallbackLogin() {
		$options = $this->config["google"];
		echo "<input id='google_login' name='google[login]' size='40' type='text' value='{$options['login']}' />";
	}

	function optionCallbackPassword() {
		$options = $this->config["google"];
		echo "<input id='google_password' name='google[password]' size='40' type='password' value='{$options['password']}' />";
	}

	function optionCallbackDevice() {
		$options = $this->config["google"];
		echo "<input id='google_device' name='google[device]' size='40' type='text' value='{$options['device']}' /> <a href=\"http://code.google.com/p/android-market-api-php/wiki/HowToGetDeviceID\" target=\"_blank\">".__('What is this?', 'android-market-badge')."</a>";
	}

	function optionCallbackCache() {
		$options = $this->config["badge"];
		echo "<input id='badge_cache' name='badge[cache]' size='3' type='text' value='{$options['cache']}' /> ".__('minutes', 'android-market-badge');
	}

	function optionCallbackQR() {
		echo "<input id='qr_active' name='qr[active]' type='checkbox' value='1'".($this->config["qr"]['active'] == "1" ? "checked" : "")." />";
	}

	function optionCallbackDesign() {
		$options	= $this->config["badge"];

		$dir		= WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__))."/badges/";
		$aDesigns	= scandir($dir);
		echo "<select id='badge_design' name='badge[design]'>";
		foreach ($aDesigns as $design) {
			if (substr($design, 0, 1) == "." || !is_dir($dir."/".$design)) continue;
			echo "<option value='{$design}'";
			if ($design == $options['design']) echo " selected";
			echo ">{$design}</option>";
		}
		echo "</select>";
	}

	function optionCallbackBadgeLink() {
		$options = $this->config["badge"];
		echo "<input id='badge_url' name='badge[url]' size='60' type='text' value='{$options['url']}' />";
	}

}

$androidAppBadge = new AndroidAppBadge();
add_action('init', array(&$androidAppBadge, 'init'));