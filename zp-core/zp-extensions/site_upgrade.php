<?php
/**
 * Switches site into <i>update</i> mode for Zenphoto upgrades. When the site is in <i>update</i> mode, links
 * to the gallery pages will be redirected to a single page that indicates the site is undergoing
 * an upgrade.
 *
 * Requires mod_rewrite to be active and that the <var>.htaccess</var> file exists
 *
 * Change the files in <var>plugins/site_upgrade</var> to meet your needs. (Note these files will
 * be copied to that folder the first time the plugin runs.)
 *
 * @author Stephen Billard (sbillard)
 * @package plugins
 * @subpackage admin
 */

switch (OFFSET_PATH) {
	case 0:
		break;
	case 2:
		if (!file_exists(SERVERPATH.'/'.USER_PLUGIN_FOLDER.'/site_upgrade/')) {
			mkdir_recursive(SERVERPATH.'/'.USER_PLUGIN_FOLDER.'/site_upgrade/', FOLDER_MOD);
			$html = file_get_contents(SERVERPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade/closed.html');
			$html = sprintf($html, sprintf(gettext('%s upgrade'),$_zp_gallery->getTitle()),FULLWEBPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade/closed.png',sprintf(gettext('<strong><em>%s</em></strong> is undergoing an upgrade'),$_zp_gallery->getTitle()), gettext('Please return later'));
			file_put_contents(SERVERPATH.'/'.USER_PLUGIN_FOLDER.'/site_upgrade/closed.html', $html);
			copy(SERVERPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade/closed.png',SERVERPATH.'/'.USER_PLUGIN_FOLDER.'/site_upgrade/closed.png');
		}
		break;
	default:
		$plugin_is_filter = 5|ADMIN_PLUGIN;
		$plugin_description = gettext('Utility to divert access to the gallery to a screen saying the site is upgrading.');
		$plugin_author = "Stephen Billard (sbillard)";
		$plugin_disable = (MOD_REWRITE) ? false : gettext('The <em>mod_rewrite</em> must be enabled');

		zp_register_filter('admin_utilities_buttons', 'site_upgrade_button');
		function site_upgrade_button($buttons) {
			$ht = @file_get_contents(SERVERPATH.'/.htaccess');
			if (empty($ht)) {
				$buttons[] = array(
						'XSRFTag'=>'site_upgrade',
						'category'=>gettext('Admin'),
						'enable'=>false,
						'button_text'=>gettext('Close the site.'),
						'formname'=>'site_upgrade.php',
						'action'=>WEBPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade.php',
						'icon'=>'images/action.png',
						'title'=>gettext('There is no .htaccess file'),
						'alt'=>'',
						'hidden'=>'',
						'rights'=> ADMIN_RIGHTS
				);
			} else {
				preg_match('|[# ][ ]*RewriteRule(.*)plugins/site_upgrade/closed|',$ht,$matches);
				$enable = strpos($matches[0],'#')===0;
				$buttons[] = array(
						'XSRFTag'=>'site_upgrade',
						'category'=>gettext('Admin'),
						'enable'=>$enable,
						'button_text'=>gettext('Close site'),
						'formname'=>'site_upgrade.php',
						'action'=>WEBPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade/site_upgrade.php',
						'icon'=>'images/lock.png',
						'title'=>$enable?gettext('Make site unavailable for viewing by redirecting to the "closed.html" page.'):gettext('The site is closed.'),
						'alt'=>'',
						'hidden'=>'',
						'rights'=> ADMIN_RIGHTS
				);
				$buttons[] = array(
						'XSRFTag'=>'site_upgrade',
						'category'=>gettext('Admin'),
						'enable'=>!$enable,
						'button_text'=>gettext('Open the site'),
						'formname'=>'site_upgrade.php',
						'action'=>WEBPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/site_upgrade/site_upgrade.php',
						'icon'=>'images/lock_open.png',
						'title'=>$enable?gettext('The site is open.'):gettext('Make the site available for viewing by removing the redirection.'),
						'alt'=>'',
						'hidden'=>'',
						'rights'=> ADMIN_RIGHTS
				);
			}
			return $buttons;
		}
		break;
}


?>
