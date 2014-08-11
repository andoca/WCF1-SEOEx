<?php
// wcf imports
require_once (WCF_DIR . 'lib/system/event/EventListener.class.php');

/**
 * Checks a new post for links an disables it when the user quite new
 * 
 * @author	Andreas Diendorfer
 * @copyright	2011 Andoca Haustier-WG UG
 *
 */
class SeoExListener implements EventListener {

	/**
	 * @see EventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		// check if the user is excluded from this plugin
		if (WCF::getUser()->getPermission('user.board.excludeFromSeoEx')) {
			return;
		}
		
		// check if this user has more than the configured postings
		// so we won't put his posting into moderation
		if (WCF::getUser()->posts >= SEOEX_MIN_ENTRIES) {
			return;
		}
		
		// regexp to check if there is a link in the message
		$pattern = '#(?<!\B|=|"|\'|,|\|/]|\?)(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#ix';
		if (!preg_match_all($pattern, $eventObj->text, $matches))
			return;
		
		// build a whitelist from the WCF config variables
		// we will allow these links
		$whitelist = array ();
		$whitelist [] = parse_url(PAGE_URL, PHP_URL_HOST);
		foreach (ArrayUtil::trim(explode("\n", PAGE_URLS)) as $url) {
			$host = parse_url($url, PHP_URL_HOST);
			if ($host)
				$whitelist [] = $host;
		}
		
		// check if there is a link in the string that is not whitelisted
		$foundLink = false;
		foreach ($matches [0] as $match) {
			if (!in_array(parse_url($match, PHP_URL_HOST), $whitelist))
				$foundLink = true;
		}
		
		// we have not found external links
		if (!$foundLink)
			return;
		
		// no excuses but links found - so we give this posting a break
		if ($eventName == 'save') {
			if ($className == 'ThreadAddForm') {
				$eventObj->disableThread = true;
			} else if ($className == 'PostAddForm' || $className == 'PostQuickAddForm') {
				$eventObj->disablePost = true;
			} else {
				$eventObj->post->disable();
			}
		} elseif ($eventName == 'saved') {
			if ($className == 'ThreadAddForm') {
				$url = 'index.php?page=Board&boardID=' . $eventObj->boardID . SID_ARG_2ND_NOT_ENCODED;
				$message = 'wbb.threadAdd.seoexmoderation.redirect';
			} else if ($className == 'PostAddForm' || $className == 'PostQuickAddForm') {
				$url = 'index.php?page=Thread&threadID=' . $eventObj->threadID . SID_ARG_2ND_NOT_ENCODED;
				$message = 'wbb.postAdd.seoexmoderation.redirect';
			} else if ($className == 'PostEditForm') {
				$url = 'index.php?page=Thread&threadID=' . $eventObj->post->threadID . SID_ARG_2ND_NOT_ENCODED;
				$message = 'wbb.postAdd.seoexmoderation.redirect';
			}
			WCF::getTPL()->assign(array (
					'url' => $url, 
					'message' => WCF::getLanguage()->get($message), 
					'wait' => 30 
			));
			WCF::getTPL()->display('redirect');
			exit();
		}
	}
}
?>