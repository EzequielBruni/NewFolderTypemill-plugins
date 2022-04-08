<?php

namespace Plugins\search;

use \Typemill\Plugin;
use \Typemill\Models\Write;

class Search extends index
{
	protected $item;
	
    public static function getSubscribedEvents()
    {
		return array(
			'onSettingsLoaded' 		=> 'onsettingsLoaded',
			'onContentArrayLoaded' 	=> 'onContentArrayLoaded',
			'onPageReady'			=> 'onPageReady',
			'onPagePublished'		=> 'onPagePublished',
			'onPageUnpublished'		=> 'onPageUnpublished',
			'onPageSorted'			=> 'onPageSorted',
			'onPageDeleted'			=> 'onPageDeleted',	
		);
	}
	
	# get search.json with route
	# update search.json on publish

	public static function addNewRoutes()
	{
		# the route for the api calls
		return array(
			array(
				'httpMethod'    => 'get', 
				'route'         => '/indexrs51gfe2o2',
				'class'         => 'Plugins\search\index:index'
			),
		);
	}

	public function onSettingsLoaded($settings)
	{
		$this->settings = $settings->getData();
	}

	# at any of theses events, delete the old search index
	public function onPagePublished($item)
	{
		$this->deleteSearchIndex();
	}
	public function onPageUnpublished($item)
	{
		$this->deleteSearchIndex();
	}
	public function onPageSorted($inputParams)
	{
		$this->deleteSearchIndex();
	}
	public function onPageDeleted($item)
	{
		$this->deleteSearchIndex();
	}

	private function deleteSearchIndex()
	{
    	$write = new Write();

    	# delete the index file here
    	$write->deleteFileWithPath('cache' . DIRECTORY_SEPARATOR . 'index.json');		
	}
	
	# add the placeholder for search results in frontend
	public function onContentArrayLoaded($contentArray)
	{
		# get content array
		$content 			= $contentArray->getData();
		$pluginsettings 	= $this->getPluginSettings('search');
		$salt 				= "asPx9Derf2";
		$langsupport 		= [	'ar' => true,
								'da' => true,
								'de' => true,
								'du' => true,
								'es' => true,
								'fi' => true,
								'fr' => true,
								'hi' => true,
								'hu' => true,
								'it' => true,
								'ja' => true,
								'jp' => true,
								'nl' => true,
								'no' => true,
								'pt' => true,
								'ro' => true,
								'ru' => true,
								'sv' => true,
								'th' => true,
								'tr' => true,
								'vi' => true,
								'zh' => true ]; 


		# activate axios and vue in frontend
		$this->activateAxios();

		# add the css and lunr library
		$this->addJS('/search/public/lunr.js');
		
		# add language support 
		$langattr = ( isset($this->settings['settings']['langattr']) && $this->settings['settings']['langattr'] != '' ) ? $this->settings['settings']['langattr'] : 'en';
		if($langattr != 'en')
		{
			if(isset($langsupport[$langattr]))
			{
				$this->addJS('/search/public/lunr-languages/min/lunr.stemmer.support.min.js');
				$this->addJS('/search/public/lunr-languages/min/lunr.' . $langattr . '.min.js');
			}
			else
			{
				$langattr = false;
			}
		}

		# add the custom search script
		$this->addJS('/search/public/search.js');

		# simple security for first request
		$secret = time();
		$secret = substr($secret,0,-1);
		$secret = md5($secret . $salt);

		# simple csrf protection with a session for long following requests
		if (session_status() == PHP_SESSION_NONE)
		{
		    session_start();
		}

		$length 					= 32;
		$token 						= substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $length);
		$_SESSION['search'] 		= $token; 
		$_SESSION['search-expire'] 	= time() + 1300; # 60 seconds * 30 minutes

		# create div for search results
		$resulttext 	= (isset($pluginsettings['resulttext']) && $pluginsettings['resulttext'] != '' ) ? $pluginsettings['resulttext'] : 'Result for ';
		$noresulttext 	= (isset($pluginsettings['noresulttext']) && $pluginsettings['noresulttext'] != '' ) ? $pluginsettings['noresulttext'] : 'We did not find anything for that search term.';
		$closetext 		= (isset($pluginsettings['closetext']) && $pluginsettings['closetext'] != '' ) ? $pluginsettings['closetext'] : 'close';

		$search 		= '<div data-access="' . $secret . '" data-token="' . $token . '" data-language="' . $langattr . '" data-resulttext="' . $resulttext . '" data-noresulttext="' . $noresulttext . '" data-closetext="' . $closetext . '" id="searchresult"></div>';

		# create content type
		$search = Array
		(
			'rawHtml' 					=> $search,
			'allowRawHtmlInSafeMode' 	=> true,
			'autobreak' 				=> 1
		);

		$content[] = $search;

		$contentArray->setData($content);
	}

	# add the search form to frontend
	public function onPageReady($page)
	{
		$pageData = $page->getData($page);

		$settings 		= $this->getPluginSettings('search');
		$placeholder 	= (isset($settings['placeholder']) && $settings['placeholder'] != '') ? $settings['placeholder'] : 'search ...';

		$pageData['widgets']['search'] = '<form class="searchContainer content" id="searchForm">'.
                                            '<label for="searchField">Search form</label>' .
	        								'<input id="searchField" type="text" placeholder="' . $placeholder . '" />'.
	        								'<button id="searchButton" type="button">Search</button>'.
    									'</form>';
 		$page->setData($pageData);
	}
}