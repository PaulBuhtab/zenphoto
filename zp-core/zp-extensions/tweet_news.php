<?php
/**
 * Use to tweet new objects as they are published
 *
 */
$plugin_is_filter = 9|THEME_PLUGIN|ADMIN_PLUGIN;
$plugin_description = gettext('Tweet news articles when published.');
$plugin_author = "Stephen Billard (sbillard)";
$plugin_version = '1.4.2';
$plugin_disable = (function_exists('curl_init')) ? false : gettext('The <em>php_curl</em> extension is required');
if ($plugin_disable) {
	setOption('zp_plugin_tweet_news',0);
} else {
	$option_interface = 'tweet';
	zp_register_filter('show_change', 'tweet::published');
	if (getOption('tweet_news_albums'))	zp_register_filter('new_album', 'tweet::published');
	if (getOption('tweet_news_images'))	zp_register_filter('new_image', 'tweet::published');
	if (getOption('tweet_news_news'))		zp_register_filter('new_article', 'tweet::newZenpageObject');
	if (getOption('tweet_news_pages'))		zp_register_filter('new_page', 'tweet::newZenpageObject');
	zp_register_filter('admin_head', 'tweet::scan');
	zp_register_filter('load_theme_script', 'tweet::scan');
	zp_register_filter('admin_overview', 'tweet::errorsOnOverview',0);
	zp_register_filter('admin_note', 'tweet::errorsOnAdmin');
	zp_register_filter('edit_album_utilities', 'tweet::tweeter');
	zp_register_filter('save_album_utilities_data', 'tweet::tweeterExecute');
	zp_register_filter('edit_image_utilities', 'tweet::tweeter');
	zp_register_filter('save_image_utilities_data', 'tweet::tweeterExecute');
	zp_register_filter('general_zenpage_utilities', 'tweet::tweeter');
	zp_register_filter('save_article_custom_data', 'tweet::tweeterZenpageExecute');
	zp_register_filter('save_page_custom_data', 'tweet::tweeterZenpageExecute');

	require_once(getPlugin('tweet_news/twitteroauth.php'));
}

$option_interface = 'tweet';

/**
 *
 * Standard options interface
 * @author Stephen
 *
 */
class tweet {

	function __construct() {
		setOptionDefault('tweet_news_consumer', NULL);
		setOptionDefault('tweet_news_consumer_secret', NULL);
		setOptionDefault('tweet_news_oauth_token', NULL);
		setOptionDefault('tweet_news_oauth_token_secret', NULL);
		setOptionDefault('tweet_news_rescan', 1);
		setOptionDefault('tweet_news_categories_none', NULL);
		setOptionDefault('tweet_news_images', NULL);
		setOptionDefault('tweet_news_albums', NULL);
		setOptionDefault('tweet_news_news', 1);
		setOptionDefault('tweet_news_protected', NULL);
		setOptionDefault('tweet_news_pages', 0);
	}

	/**
	 *
	 * supported options
	 */
	function getOptionsSupported() {
		global $_zp_zenpage;
		$options = array(	gettext('Consumer key') => array('key'=>'tweet_news_consumer', 'type'=>OPTION_TYPE_TEXTBOX,
																												'order'=>2,
																												'desc'=>gettext('This <code>tweet_news</code> app for this site needs a <em>consumer key</em>, a <em>consumer key secret</em>, an <em>access token</em>, and an <em>access token secret</em>.').'<p class="notebox">'. gettext('Get these from <a href="http://dev.twitter.com/">Twitter developers</a>').'</p>'),
											gettext('Secret') => array('key'=>'tweet_news_consumer_secret', 'type'=>OPTION_TYPE_TEXTBOX,
																									'order'=>3,
																									'desc'=>gettext('The <em>secret</em> associated with your <em>consumer key</em>.')),
											gettext('Access token') => array('key'=>'tweet_news_oauth_token', 'type'=>OPTION_TYPE_TEXTBOX,
																												'order'=>4,
																												'desc'=>gettext('The application <em>oauth_token</em> token.')),
											gettext('Access token secret') => array('key'=>'tweet_news_oauth_token_secret', 'type'=>OPTION_TYPE_TEXTBOX,
																															'order'=>5,
																															'desc'=>gettext('The application <em>oauth_token</em> secret.')),
											gettext('Protected objects') => array('key'=>'tweet_news_protected', 'type'=>OPTION_TYPE_CHECKBOX,
																														'order'=>7,
																														'desc'=>gettext('If checked, protected items will be tweeted. <strong>Note:</strong> followers will need the password to visit the tweeted link.'))
										);
		$note = '';
		$list = array('<em>'.gettext('Albums').'</em>'=>'tweet_news_albums', '<em>'.gettext('Images').'</em>'=>'tweet_news_images');
		if (getOption('zp_plugin_zenpage')) {
			$list['<em>'.gettext('News').'</em>'] = 'tweet_news_news';
			$list['<em>'.gettext('Pages').'</em>'] = 'tweet_news_pages';
			$options[gettext('Scan pending')] = array('key'=>'tweet_news_rescan', 'type'=>OPTION_TYPE_CHECKBOX,
																								'order'=>8,
																								'desc'=>gettext('<code>tweet_news</code> notices when a page or an article is published. '.
																																'If the date is in the future, it is put in the <em>to-be-tweeted</em> and tweeted when that date arrives. '.
																																'This option allows you to re-populate that list to the current state of scheduled tweets.')
																								);


		} else {
			setOption('tweet_news_news', 0);
			setOption('tweet_news_pages', 0);
		}
		$options[gettext('Tweet')] = array('key'=>'tweet_news_items', 'type'=>OPTION_TYPE_CHECKBOX_ARRAY,
																			'order'=>6,
																			'checkboxes' => $list,
																			'desc'=>gettext('If an <em>type</em> is checked, a Tweet will be made when an object of that <em>type</em> is published.'));

		if (getOption('tweet_news_news')) {
			$catlist = unserialize(getOption('tweet_news_categories'));
			$news_categories = $_zp_zenpage->getAllCategories(false);
			$catlist = array(gettext('*not categorized*')=>'tweet_news_categories_none');
			foreach ($news_categories as $category) {
				$option = 'tweet_news_categories_'.$category['titlelink'];
				$catlist[$category['title']] = $option;
				setOptionDefault($option, NULL);
			}
			$options[gettext('News categories')] = array('key'=>'tweet_news_categories', 'type'=>OPTION_TYPE_CHECKBOX_UL,
																													'order'=>6.5,
																													'checkboxes' => $catlist,
																													'desc'=>gettext('Only those <em>news categories</em> checked will be Tweeted. <strong>Note:</strong> <em>*not categorized*</em> means those news articles which have no category assigned.'));
		}
		if (getOption('tweet_news_rescan')) {
			setOption('tweet_news_rescan', 0);
			$note = tweet::tweetRepopulate();
		}
		if ($note) {
			$options['note'] = array('key'=>'tweet_news_rescan', 'type'=>OPTION_TYPE_NOTE,
															'order'=>0,
															'desc'=>$note);
		}

		return $options;
	}

	/**
	 *
	 * place holder
	 * @param string $option
	 * @param mixed $currentValue
	 */
	function handleOption($option, $currentValue) {
	}

	/**
	 *
	 * Actual tweet processing of message
	 * @param string $status
	 */
	private static function sendTweet($status) {
		global $tweet;
		if (!is_object($tweet)) {
			$consumerKey = getOption('tweet_news_consumer');
			$consumerSecret = getOption('tweet_news_consumer_secret');
			$OAuthToken = getOption('tweet_news_oauth_token');
			$OAuthSecret = getOption('tweet_news_oauth_token_secret');
			$tweet = new TwitterOAuth($consumerKey, $consumerSecret, $OAuthToken, $OAuthSecret);
		}
		$response = $tweet->post('statuses/update', array('status' => $status));
		if (isset($response->error)) {
			return $response->error;
		}
		return false;
	}

	/**
	 *
	 * filter for new news articles
	 * @param string $msg
	 * @param object $article
	 */
	static function newZenpageObject($msg, $article) {
		$error = tweet::tweetObjectWithCheck($article);
		if ($error) {
			$msg .= '<p class="errorbox">'.$error.'</p>';
		}
		return $msg;
	}

	/**
	 *
	 * filter for the setShow() methods
	 * @param object $obj
	 */
	static function published($obj) {
		$error = tweet::tweetObjectWithCheck($obj);
		if ($error) {
			query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","error",'.db_quote($error).')');
		}
		return $obj;
	}

	/**
	 *
	 * used by the filters to decide if to tweet
	 * @param object $obj
	 */
	private static function tweetObjectWithCheck($obj) {
		$error = '';
		$type = $obj->table;
		if (getOption('tweet_news_'.$type)) {
			if ($obj->getShow()) {
				if (getOption('tweet_news_protected') || !$obj->isProtected()) {
					switch ($type = $obj->table) {
						case 'pages':
							$dt = $obj->getDateTime();
							if($dt > date('Y-m-d H:i:s')) {
								$result = query_single_row('SELECT * FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_pages" AND `data`='.db_quote($obj->getTitlelink()));
								if (!$result) {
									query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_pages",'.db_quote($obj->getTitlelink()).')');
								}
							} else {
								$error = tweet::tweetObject($obj);
							}
							break;
						case 'news':
							$tweet = false;
							$mycategories = $obj->getCategories();
							if (empty($mycategories)) {
								$tweet = getOption('tweet_news_categories_none');
							} else {
								foreach($mycategories as $cat) {
									if ($tweet = getOption('tweet_news_categories_'.$cat['titlelink'])) {
										break;
									}
								}
							}
							if (!$tweet) {
								break;
							}
							$dt = $obj->getDateTime();
							if($dt > date('Y-m-d H:i:s')) {
								$result = query_single_row('SELECT * FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending" AND `data`='.db_quote($obj->getTitlelink()));
								if (!$result) {
									query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending",'.db_quote($obj->getTitlelink()).')');
								}
							} else {
								$error = tweet::tweetObject($obj);
							}
							break;
						case 'albums':
							$dt = $obj->getPublishDate();
							if($dt > date('Y-m-d H:i:s')) {
								$result = query_single_row('SELECT * FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_albums" AND `data`='.db_quote($obj->name));
								if (!$result) {
									query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_albums",'.db_quote($obj->name).')');
								}
							} else {
								$error = tweet::tweetObject($obj);
							}
							break;
						case 'images':
							$dt = $obj->getPublishDate();
							if($dt > date('Y-m-d H:i:s')) {
								$result = query_single_row('SELECT * FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_images" AND `data`='.db_quote($obj->album->name.'/'.$obj->filename));
								if (!$result) {
									query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_images",'.db_quote($obj->album->name.'/'.$obj->filename).')');
								}
							} else {
								$error = tweet::tweetObject($obj);
							}
							break;
					}
				}
			}
		}
		return $error;
	}

	/**
	 *
	 * Formats the message and calls sendTweet() on an object
	 * @param object $obj
	 */
	private static function tweetObject($obj) {
		$error = '';
		$link = getTinyURL($obj);
		switch ($type = $obj->table) {
			case 'pages':
			case 'news':
				$text = trim(html_entity_decode(strip_tags($obj->getContent()),ENT_QUOTES));
				if (strlen($text) > 140) {
					$title = trim(html_entity_decode(strip_tags($obj->getTitle()),ENT_QUOTES));
					$c = 140 - strlen($link);
					if (mb_strlen($title) >= ($c - 25)) {	//	not much point in the body if shorter than 25
						$text = truncate_string($title, $c - 4, '... ').$link;	//	allow for ellipsis
					} else {
						$c = $c - mb_strlen($title) - 5;
						$text = $title.': '.truncate_string($text, $c, '... ').$link;
					}
				}
				$error = tweet::sendTweet($text);
				if ($error) {
					$error =  sprintf(gettext('Error tweeting <code>%1$s</code>: %2$s'),$obj->getTitlelink(),$error);
				}

				break;
			case 'albums':
			case 'images':
				if ($type=='images') {
					$text = sprintf(gettext('New image: [%2$s]%1$s '),$item = trim(html_entity_decode(strip_tags($obj->getTitle()),ENT_QUOTES)),
																															trim(html_entity_decode(strip_tags($obj->album->name),ENT_QUOTES)));
				} else {
					$text = sprintf(gettext('New album: %s '),$item = trim(html_entity_decode(strip_tags($obj->getTitle()),ENT_QUOTES)));
				}
				if (mb_strlen($text.$link) > 140) {
					$c = 140 - strlen($link);
					$text = truncate_string($text, $c-4, '... ').$link;	//	allow for ellipsis
				} else {
					$text = $text.$link;
				}
				$error = tweet::sendTweet($text);
				if ($error) {
					$error = sprintf(gettext('Error tweeting <code>%1$s</code>: %2$s'),$item,$error);
				}
				break;
			case 'comments':
				$text = trim(html_entity_decode(strip_tags($obj->getComment()),ENT_QUOTES));
				if (mb_strlen($text.$link) > 140) {
					$c = 140 - strlen($link);
					$text = truncate_string($text, $c-4, '... ').$link;	//	allow for ellipsis
				} else {
					$text = $text.$link;
				}
				$error = tweet::sendTweet($text);
				if ($error) {
					$error = sprintf(gettext('Error tweeting <code>%1$s</code>: %2$s'),$item,$error);
				}
				break;
		}
		return $error;
	}

	/**
	 *
	 * filter which checks if there are any matured tweets to be sent
	 */
	static function scan($param) {
		$result = query_full_array('SELECT * FROM '.prefix('news').' AS news,'.prefix('plugin_storage').' AS store WHERE store.type="tweet_news" AND store.aux="pending" AND store.data = news.titlelink AND news.date <= '.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			foreach ($result as $article) {
				query('DELETE FROM '.prefix('plugin_storage').' WHERE `id`='.$article['id']);
				$news = new ZenpageNews($article['titlelink']);
				tweet::tweetObject($news);
			}
		}
		$result = query_full_array('SELECT * FROM '.prefix('pages').' AS page,'.prefix('plugin_storage').' AS store WHERE store.type="tweet_news" AND store.aux="pending_pages" AND store.data = page.titlelink AND page.date <= '.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			foreach ($result as $page) {
				query('DELETE FROM '.prefix('plugin_storage').' WHERE `id`='.$page['id']);
				$page = new ZenpageNews($page['titlelink']);
				tweet::tweetObject($page);
			}
		}
		$result = query_full_array('SELECT * FROM '.prefix('albums').' AS album,'.prefix('plugin_storage').' AS store WHERE store.type="tweet_news" AND store.aux="pending_albums" AND store.data = album.folder AND album.date <= '.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			$gallery = new Gallery();
			foreach ($result as $album) {
				query('DELETE FROM '.prefix('plugin_storage').' WHERE `id`='.$album['id']);
				$album = new Album($gallery, $album['folder']);
				tweet::tweetObject($album);
			}
		}
		$result = query_full_array('SELECT * FROM '.prefix('images').' AS image,'.prefix('plugin_storage').' AS store WHERE store.type="tweet_news" AND store.aux="pending_images" AND store.data LIKE image.filename AND image.date <= '.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			$gallery = new Gallery();
			foreach ($result as $image) {
				query('DELETE FROM '.prefix('plugin_storage').' WHERE `id`='.$image['id']);
				$album = query_single_row('SELECT * FROM '.prefix('albums').' WHERE `id`='.$image['albumid']);
				$album = new Album($gallery, $album['folder']);
				$image = newImage($album, $image['filename']);
				tweet::tweetObject($image);
			}
		}
		return $param;
	}

	/**
	 *
	 * Collects all published news & pages whose publish date is in the future. Sets the scan list to those found
	 */
	private static function tweetRepopulate() {
		$found = array();
		query('DELETE FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending"');
		$result = query_full_array('SELECT * FROM '.prefix('news').' WHERE `show`=1 AND `date`>'.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			foreach ($result as $pending) {
				query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending",'.db_quote($pending['titlelink']).')');
			}
			$found[] = gettext('news');
		}
		query('DELETE FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_pages"');
		$result = query_full_array('SELECT * FROM '.prefix('pages').' WHERE `show`=1 AND `date`>'.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			foreach ($result as $pending) {
				query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_pages",'.db_quote($pending['titlelink']).')');
			}
			$found[] = gettext('pages');
		}
		query('DELETE FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_albums"');
		$result = query_full_array('SELECT * FROM '.prefix('albums').' WHERE `show`=1 AND `date`>'.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			foreach ($result as $pending) {
				query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_albums",'.db_quote($pending['folder']).')');
			}
			$found[] = gettext('albums');
		}
		query('DELETE FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="pending_images"');
		$result = query_full_array('SELECT * FROM '.prefix('images').' WHERE `show`=1 AND `date`>'.db_quote(date('Y-m-d H:i:s')));
		if ($result) {
			$gallery = new Gallery();
			foreach ($result as $pending) {
				$album = query_single_row('SELECT * FROM '.prefix('albums').' WHERE `id`='.$pending['albumid']);
				query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","pending_images",'.db_quote($album['folder'].'/'.$pending['filename']).')');
			}
			$found[] = gettext('images');
		}
		if (empty($found)) {
			return '<p class="messagebox">'.gettext('No scheduled news articles found.').'</p>';
		} else {
			return '<p class="messagebox">'.gettext('Scheduled items have been noted for tweeting.').'</p>';
		}
	}

	/**
	 *
	 * returns any tweet errors accumulated but not displayed. Clears the list
	 */
	private static function tweetFetchErrors() {
		$result = query_full_array('SELECT * FROM '.prefix('plugin_storage').' WHERE `type`="tweet_news" AND `aux`="error"');
		$errors = '';
		foreach ($result as $error) {
			$errors .= $error['data'].'<br />';
			query('DELETE FROM'.prefix('plugin_storage').' WHERE `id`='.$error['id']);
		}
		return $errors;
	}

	/**
	 *
	 * filter which will display tweet errors on the admin overview page
	 * @param string $side
	 */
	static function errorsOnOverview($side){
		if ($side=='left') {
			$errors = tweet::tweetFetchErrors();
			if ($errors) {
				?>
				<div class="box" id="overview-news">
				<h2 class="h2_bordered"><?php echo gettext("Tweet News Errors:"); ?></h2>
				<?php echo '<p class="errorbox">'.$errors.'</p>'; ?>
				</div>
				<?php
			}
		}
		return $side;
	}

	/**
	 *
	 * filter to display tweet errors on an admin page
	 * @param string $tab
	 * @param string $subtab
	 */
	static function errorsOnAdmin($tab, $subtab) {
		$errors = tweet::tweetFetchErrors();
		if ($errors) {
			echo '<p class="errorbox">'.$errors.'</p>';
		}
		return $tab;
	}

	/**
	 *
	 * filter to place tweet action on object edit pages
	 * @param unknown_type $before
	 * @param unknown_type $object
	 * @param unknown_type $prefix
	 */
	static function tweeter($before, $object, $prefix=NULL) {
			$output = '<p class="checkbox">'."\n".'<label>'."\n".'<input type="checkbox" name="tweet_me'.$prefix.'" id="tweet_me'.$prefix.'" value="1" /> <img src="'.WEBPATH.'/'.ZENFOLDER.'/'.PLUGIN_FOLDER.'/tweet_news/twitter_newbird_blue.png" /> '.gettext('Tweet me')."\n</label>\n</p>\n";
			return $before.$output;;
	}

	/**
	 *
	 * processes the image and album tweet request
	 * @param object $object
	 * @param string $prefix
	 */
	static function tweeterExecute($object, $prefix) {
		if (isset($_POST['tweet_me'.$prefix])) {
			$error = tweet::tweetObject($object);
			if ($error) {
				query('INSERT INTO '.prefix('plugin_storage').' (`type`,`aux`,`data`) VALUES ("tweet_news","error",'.db_quote($error).')');
			}
		}
		return $object;
	}

	/**
	 *
	 * filter to process zenpage tweet requests
	 * @param unknown_type $custom
	 * @param unknown_type $object
	 */
	static function tweeterZenpageExecute($custom, $object) {
		tweeterExecute($object, '');
		return $custom;
	}

}
?>