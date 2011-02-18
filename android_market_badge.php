<?php
/*
Plugin Name: Android Market Badges
Plugin URI:
Feed URI:
Description:
Version: 0.51
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

	public function __construct() {
		$this->readConfig();

		add_filter('the_content', array(&$this, 'addBadge'));
		add_filter('comment_text', array(&$this, 'addBadge'));

		if ($this->config["qr"]["active"] == 1) {
			add_filter('the_content', array(&$this, 'addQR'));
			add_filter('comment_text', array(&$this, 'addQR'));
		}

		add_filter('admin_init', array(&$this, 'adminInit'));
		add_filter('admin_menu', array(&$this, 'adminMenu'));
	}

	private function readConfig() {
		$this->config["qr"]		= get_option('qr', array("active" => 0));
		$this->config["badge"]	= get_option('badge', array(
			"cache"	=> 60,
			"url"	=> "http://www.cyrket.com/p/android/%s/"
		));
		$this->config["google"]	= get_option('google', array());
	}

	function adminMenu() {
		add_submenu_page('plugins.php', 'Android Market', 'App Badges', 'manage_options', 'android_app_options', array(&$this, 'settingsPage'));
	}

	function adminInit() {
		add_settings_section('market', 'Android Market', array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('google_login', 'Google Login', array(&$this, 'optionCallbackLogin'), 'android_app', 'market');
		add_settings_field('google_password', 'Google Password', array(&$this, 'optionCallbackPassword'), 'android_app', 'market');
		add_settings_field('google_device', 'Android Device ID', array(&$this, 'optionCallbackDevice'), 'android_app', 'market');

		add_settings_section('badge', 'Badge settings', array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('badge_cache', 'Cache time', array(&$this, 'optionCallbackCache'), 'android_app', 'badge');
		add_settings_field('badge_design', 'Badge design', array(&$this, 'optionCallbackDesign'), 'android_app', 'badge');
		add_settings_field('badge_url', 'Badge link URL', array(&$this, 'optionCallbackBadgeLink'), 'android_app', 'badge');

		add_settings_section('qr', 'QR-BBCodes', array(&$this, 'sectionCallback'), 'android_app');
		add_settings_field('qr_active', 'Enable QR-BBCode', array(&$this, 'optionCallbackQR'), 'android_app', 'qr');


		register_setting('android_app', 'google'); //, 'android_app_validate');
		register_setting('android_app', 'badge'); //, 'android_app_validate');
		register_setting('android_app', 'qr'); //, 'android_app_validate');
	}

	function addQR($content) {
		$qrSize	= 3;
		$urlPre = "http://qrcode.kaywa.com/img.php?s={$qrSize}&d=";

		$content = preg_replace_callback("/\[qr=([.\w]*)\]/", create_function(
			'$matches',
			'return "<img src=\"'.$urlPre.'".urlencode("market://details?id=".$matches[1])."\" title=\"".$matches[1]."\"/>";'), $content);
		//http://chart.apis.google.com/chart?cht=qr&chs=250x250&chl=market://details?id=".$app->getExtendedInfo()->getPackageName()."&chld=L|1";

		$content = preg_replace_callback("/\[qr\]([.\w]*)\[\/qr\]/", create_function(
			'$matches',
			'return "<img src=\"'.$urlPre.'".urlencode("market://details?id=".$matches[1])."\" title=\"".$matches[1]."\"/>";'), $content);

		return $content;
	}

	function addBadge($content) {
		$design		= $this->config["badge"]["design"];

		//Fallback to an existing design if the badge was removed
		if (!file_exists("badges/{$design}/badge.php")) $design = "default";

		$cacheAge	= $this->config["badge"]["cache"] * 60;
		$cachePath	= "/".basename(dirname(__FILE__))."/cache/";
		preg_match_all("/\[app=([.\w]*)\]/", $content, $aMatches);


		foreach ($aMatches[1] as $index => $pname) {
			$cacheFile = $cachePath.$pname."_{$design}.png";

			//If no cached file exists or it is older than $cacheAge, get a new one
			$success = false;
			if (!file_exists(WP_PLUGIN_DIR.$cacheFile) || time() - filemtime(WP_PLUGIN_DIR.$cacheFile) >= $cacheAge) {
				if (!$this->loggedIn) {
					include_once("market/protocolbuffers.inc.php");
					include_once("market/market.proto.php");
					include_once("market/MarketSession.php");

					include_once("badges/{$design}/badge.php");


					$session = new MarketSession();
					$session->login($this->config["google"]["login"], $this->config["google"]["password"]);
					$session->setAndroidId($this->config["google"]["device"]);

					$this->loggedIn = true;
				}

				$func	= "android_app_badge_{$design}";
				$image	= $func($session, $pname);

				if ($image !== false) {
					$success = @file_put_contents(WP_PLUGIN_DIR.$cacheFile, $image) !== false;
				}
			} else {
				$success = true;
			}

			//Link to market if browsing from app
			$android	= stripos($_SERVER["HTTP_USER_AGENT"], "Android") !== false;
			if ($android) {
				$link		= "market://search?q=pname:".$pname;
			} else {
				$link		= sprintf($this->config["badge"]["url"], urlencode($pname));
			}
			$aSearch[]	= $aMatches[0][$index];

			if ($success === true) {
				$aReplace[]	= '<a href="'.$link.'" target="_blank"><img src="'.WP_PLUGIN_URL.$cacheFile.'" alt="'.$pname.'"/></a>';
			} else {
				$aReplace[]	= '<a href="'.$link.'" target="_blank">Link to '.$pname.'</a>';
			}


		}
		$content = str_replace($aSearch, $aReplace, $content);

		return $content;
	}

	/**
	* Adds settings/options page
	*/
	function settingsPage() {
		//must check that the user has the required capability
		if (!current_user_can('manage_options')) {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}

		if ($_POST['google'] || $_POST['badge']) {
			update_option("google", $_POST["google"]);
			update_option("badge", $_POST["badge"]);
			update_option("qr", $_POST["qr"]);

			$this->readConfig();
			echo '<div class="updated"><p>Success! Your changes were sucessfully saved!</p></div>';
		}

		?>
		<div class="wrap">
		<h2>Android Market Badges</h2>
		<?php
		$cachePath	= WP_PLUGIN_DIR."/".basename(dirname(__FILE__))."/cache/";
		if (!is_writable($cachePath)) {
			echo "<div class=\"error\">The folder wp-content/plugins".$cachePath." must be writable</div>";
		}
		?>
		<form method="post" action="">
			<?php settings_fields('google_group'); ?>
			<?php do_settings_sections('android_app'); ?>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"  /></p>
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
		echo "<input id='google_device' name='google[device]' size='40' type='text' value='{$options['device']}' /> <a href=\"http://code.google.com/p/android-market-api-php/wiki/HowToGetDeviceID\" target=\"_blank\">What is this?</a>";
	}

	function optionCallbackCache() {
		$options = $this->config["badge"];
		echo "<input id='badge_cache' name='badge[cache]' size='3' type='text' value='{$options['cache']}' /> minutes";
	}

	function optionCallbackQR() {
		echo "<input id='qr_active' name='qr[active]' type='checkbox' value='1'".($this->config["qr"]['active'] == "1" ? "checked" : "")." />";
	}

	function optionCallbackDesign() {
		$options = $this->config["badge"];

		$dir = WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__))."/badges/";

		$aDesigns = scandir($dir);
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