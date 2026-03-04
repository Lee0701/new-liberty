<?php

use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class SkinNewLiberty extends SkinMustache {

    public function getTemplateData() {
		global $wgNewLibertyEnableLiveRC, $wgNewLibertyMaxRecent, $wgNewLibertyLiveRCArticleNamespaces, $wgNewLibertyLiveRCTalkNamespaces;
        $data = parent::getTemplateData();

		// Add parsed navbar data
        $data['navbar'] = $this->renderPortal( $this->parseNavbar() );

		// Remove notice if user has disabled it
		if($this->getRequest()->getCookie('disable-notice')) {
			unset($data['html-site-notice']);
		}

		// Convert portlet items to associative arrays
		if(array_key_exists('data-languages', $data['data-portlets'])) {
			$data['data-portlets']['data-languages']['array-items'] = $this->convertPortletItems($data['data-portlets']['data-languages']['array-items']);
		}
		if(array_key_exists('data-personal', $data['data-portlets'])) {
			$data['data-portlets']['data-personal']['array-items'] = $this->convertPortletItems($data['data-portlets']['data-personal']['array-items']);
			$divider = array('is-divider' => true);
			$exclude_keys = array('pt-notifications-alert');
			$result = array();
			foreach($data['data-portlets']['data-personal']['array-items'] as $item) {
				if($item['id'] == 'pt-notifications-notice') $item['id'] = 'pt-notifications';
				// Add divider before logout menu
				if($item['id'] == 'pt-logout') $result[] = $divider;
				// Add item if not excluded
				if(!in_array($item['id'], $exclude_keys)) $result[] = $item;
				// Add divider after user page menu
				if($item['id'] == 'pt-userpage') $result[] = $divider;

				if($item['id'] == 'pt-logout') $data['logout-btn'] = $item;
			}
			$data['data-portlets']['data-personal']['array-items'] = $result;
		}
		if(array_key_exists('data-views', $data['data-portlets'])) {
			$data['data-portlets']['data-views']['array-items'] = $this->convertPortletItems($data['data-portlets']['data-views']['array-items']);
		}
		if(array_key_exists('data-actions', $data['data-portlets'])) {
			$data['data-portlets']['data-actions']['array-items'] = $this->convertPortletItems($data['data-portlets']['data-actions']['array-items']);
		}
		if(array_key_exists('data-notifications', $data['data-portlets'])) {
			// Filter out all read notification links
			$result = array();
			foreach($data['data-portlets']['data-notifications']['array-items'] as $key => $item) {
				foreach($item['array-links'] as $link) {
					foreach($link['array-attributes'] as $attr) {
						if($attr['key'] == 'data-counter-num' and $attr['value'] != 0) {
							$result[] = $item;
						}
					}
				}
			}
			$data['data-portlets']['data-notifications']['array-items'] = $result;
		}

		// Add "designed by libre" icon to footer
		if(array_key_exists('data-icons', $data['data-footer'])) {
			$src = $this->getSkin()->getConfig()->get( 'StylePath' ) . '/NewLiberty/img/designedbylibre.png';
			$href = '//librewiki.net';
			$html = '<a href="' . $href . '"><img src="' . $src . '" style="height:31px" alt="Designed by Librewiki"></a>';
			$data['data-footer']['data-icons']['array-items'][] = array(
				'name' => 'designedbylibre',
				'id' => 'footer-designedbylibreico',
				'class' => 'noprint mw-list-item',
				'html' => $html,
			);
		}

		// Live recent changes
		$data['liverc-enabled'] = $wgNewLibertyEnableLiveRC;
		$articleNS = implode( '|', $wgNewLibertyLiveRCArticleNamespaces );
		$talkNS = implode( '|', $wgNewLibertyLiveRCTalkNamespaces );
		$data['liverc-article-ns'] = $articleNS;
		$data['liverc-talk-ns'] = $talkNS;
		$recentItems = array();
		foreach(range(0, $wgNewLibertyMaxRecent) as $i) {
			$recentItems[] = array(
				'class' => 'recent-item',
				'html-content' => '&nbsp;',
			);
		}
		$data['liverc-items'] = $recentItems;
		$data['link-recentchanges'] = SpecialPage::getTitleFor( 'Recentchanges' )->getLocalURL();

		// Login box
		$data['user-is-registered'] = $this->getUser()->isRegistered();
		$data['user-avatar'] = $this->getUserAvatar();

		// Login modal form
		$data['link-createaccount'] = SpecialPage::getTitleFor( 'createaccount' )->getLocalURL();
		$data['link-userlogin'] = SpecialPage::getTitleFor( 'userlogin' )->getLocalURL();
		$data['link-passwordreset'] = SpecialPage::getTitleFor( 'passwordreset' )->getLocalURL();

        return $data;
    }

	/**
	 * Converts portlet array-items into key-value associative array.
	 *
	 * @param array $items
	 * @return array
	 * @access protected
	 */
	protected function convertPortletItems($items) {
		$result = array();
		foreach($items as $item) {
			foreach($item['array-links'] as $link) {
				$attrs = array();
				$attrs['id'] = $item['id'];
				foreach($link as $key => $val) {
					if($key == 'array-attributes' and is_array($val)) {
						foreach($val as $attr) {
							$attrs[$attr['key']] = $attr['value'];
						}
					}
					else if(is_string($key)) $attrs[$key] = $val;
				}
				$result[] = $attrs;
			}
		}
		return $result;
	}

	public function initPage( OutputPage $out ) {
		global $wgSitename, $wgNewLibertyPrimaryColor, $wgNewLibertySecondaryColor, $wgNewLibertyNavBarLogoImage, $wgNewLibertyEnableLiveRC;
		$skin = $this->getSkin();

		$primaryColor = $wgNewLibertyPrimaryColor;
		$defaultSecondaryColor = '#' . dechex( hexdec( substr( $primaryColor, 1, 6 ) ) - hexdec( '1A1415' ) );
		$secondaryColor = $wgNewLibertySecondaryColor or $defaultSecondaryColor;

		$out->addMeta( 'viewport', 'width=device-width, initial-scale=1, maximum-scale=1' );

		if (
			!class_exists( ArticleMetaDescription::class ) ||
			!class_exists( Description2::class )
		) {
			// The validator complains if there's more than one description,
			// so output this here only if none of the aforementioned SEO
			// extensions aren't installed
			$out->addMeta( 'description', strip_tags(
				preg_replace( '/<table[^>]*>([\s\S]*?)<\/table[^>]*>/', '', $out->mBodytext ),
				'<br>'
			) );
		}
		$out->addMeta( 'keywords', $wgSitename . ',' . $skin->getTitle() );

		$modules = [
			'skins.liberty.bootstrap',
			'skins.liberty.layoutjs'
		];

		// Only load ad-related JS if ads are enabled in site configuration
		if ( isset( $wgNewLibertyAdSetting['client'] ) && $wgNewLibertyAdSetting['client'] ) {
			$modules[] = 'skins.liberty.ads';
		}

		// Only load LiveRC JS is we have enabled that feature in site config
		if ( $wgNewLibertyEnableLiveRC ) {
			$modules[] = 'skins.liberty.liverc';
		}

		// Only load modal login JS for anons, no point in loading it for logged-in
		// users since the modal HTML isn't even rendered for them.
		if ( $this->getUser()->isAnon() ) {
			$modules[] = 'skins.liberty.loginjs';
		}

		$out->addModules( $modules );

		/* IOS 기기 및 모바일 크롬에서의 웹앱 옵션 켜기 및 상단바 투명화 */
		$out->addMeta( 'apple-mobile-web-app-capable', 'Yes' );
		$out->addMeta( 'apple-mobile-web-app-status-bar-style', 'black-translucent' );
		$out->addMeta( 'mobile-web-app-capable', 'Yes' );

		/* 모바일에서의 테마 컬러 적용 */
		// 크롬, 파이어폭스 OS, 오페라
		$out->addMeta( 'theme-color', $primaryColor );
		// 윈도우 폰
		$out->addMeta( 'msapplication-navbutton-color', $primaryColor );

		$out->addInlineStyle("
			.Liberty .nav-wrapper,
			.Liberty .nav-wrapper .navbar .form-inline .btn:hover,
			.Liberty .nav-wrapper .navbar .form-inline .btn:focus,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link.active::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:hover::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:focus::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:active::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-footer .label,
			.Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn:hover,
			.Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn:focus,
			.Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn:active {
				background-color: $primaryColor;
			}

			.Liberty .nav-wrapper .navbar .form-inline .btn:hover,
			.Liberty .nav-wrapper .navbar .form-inline .btn:focus {
				border-color: $secondaryColor;
			}

			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link.active::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:hover::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:focus::before,
			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link:active::before {
				border-bottom: 2px solid $primaryColor;
			}

			.Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-footer .label:hover,
			.Liberty .nav-wrapper .navbar .navbar-nav .nav-item .nav-link:hover,
			.Liberty .nav-wrapper .navbar .navbar-nav .nav-item .nav-link:focus,
			.dropdown-menu .dropdown-item:hover {
				background-color: $secondaryColor;
			}


			.Liberty .content-wrapper #liberty-bottombtn,
			.Liberty .content-wrapper #liberty-bottombtn:hover {
				background-color: $primaryColor;
			}
		");

		// navbar image settings
		if ( isset( $wgNewLibertyNavBarLogoImage ) ) {
			$out->addInlineStyle(
				".Liberty .nav-wrapper .navbar .navbar-brand {
					background: transparent url($wgNewLibertyNavBarLogoImage) no-repeat scroll left center/auto 1.9rem;
				}
				@media screen and (max-width: 397px){
					.Liberty .nav-wrapper .navbar .navbar-brand {
						background: transparent url($wgNewLibertyNavBarLogoImage) no-repeat scroll left center/auto 1.5rem;
					}
				}"
			);
		}

		$LibertyDarkCss = "
			body, .Liberty, .dropdown-menu, .dropdown-item, .Liberty .nav-wrapper .navbar .form-inline .btn, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item .nav-link.active, .Liberty .content-wrapper .liberty-content .liberty-content-main table.wikitable tr > th, .Liberty .content-wrapper .liberty-content .liberty-content-main table.wikitable tr > td, table.mw_metadata th, .Liberty .content-wrapper .liberty-content .liberty-content-main table.infobox th, #preferences fieldset:not(.prefsection), #preferences div.mw-prefs-buttons, .navbox, .navbox-subgroup, .navbox > tbody > tr:nth-child(even) > .navbox-list {
				background-color: #000;
				color: #DDD;
			}

			.liberty-content-header, .liberty-footer, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-footer, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item, .Liberty .content-wrapper .liberty-content .liberty-content-header, .Liberty .content-wrapper .liberty-footer, .editOptions, html .wikiEditor-ui-toolbar, #pagehistory li.selected, .mw-datatable td, .Liberty .content-wrapper .liberty-content .liberty-content-main table.wikitable tr > td, table.mw_metadata td, .Liberty .content-wrapper .liberty-content .liberty-content-main table.wikitable, .Liberty .content-wrapper .liberty-content .liberty-content-main table.infobox, #preferences, .navbox-list, .dropdown-divider {
				background-color: #1F2023;
				color: #DDD;
			}

			.Liberty .content-wrapper .liberty-content .liberty-content-main, .mw-datatable th, .mw-datatable tr:hover td, textarea, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-content, div.mw-warning-with-logexcerpt, div.mw-lag-warn-high, div.mw-cascadeprotectedwarning, div#mw-protect-cascadeon {
				background-color: #000;
			}

			.Liberty .content-wrapper .liberty-content .liberty-content-header .title>h1, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-content .live-recent-list .recent-item, caption { color: #DDD; }

			.btn-secondary { background: transparent; color: #DDD; }

			#pagehistory li { border: 0; }

			.Liberty .content-wrapper .liberty-footer, .Liberty .content-wrapper .liberty-content .liberty-content-header, .Liberty .content-wrapper .liberty-content .liberty-content-main, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-footer, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-content, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-header .nav .nav-item + .nav-item, .Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn:hover, .Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn:focus, .Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools .tools-btn, .dropdown-menu, .dropdown-divider, .Liberty .content-wrapper .liberty-content .liberty-content-main fieldset, hr, .Liberty .content-wrapper .liberty-sidebar .live-recent-wrapper .live-recent .live-recent-content .live-recent-list li, .mw-changeslist-legend, .Liberty .content-wrapper .liberty-content .liberty-content-header .content-tools { border-color: #555; }

			.flow-post, .Liberty .content-wrapper .liberty-content .liberty-content-main .toc .toctext { color: #DDD; }
			.flow-topic-titlebar { color: #000; }
			.flow-ui-navigationWidget { color: #FFF; }
			.Liberty .content-wrapper .liberty-content .liberty-content-main .toccolours, .Liberty .content-wrapper .liberty-content .liberty-content-main .toc ul, .Liberty .content-wrapper .liberty-content .liberty-content-main .toc li { background-color: #000; }
			.Liberty .content-wrapper .liberty-content .liberty-content-main .toc .toctitle { background-color: #1F2023; }
		";

		$out->addInlineStyle( "@media (prefers-color-scheme: dark) { $LibertyDarkCss }" );
	}

    /**
	 * Render Portal function, build top menu contents.
	 *
	 * @param array $contents Menu data that will made by parseNavbar function.
	 */
	protected function renderPortal( $contents ) {
		$skin = $this->getSkin();
		$user = $skin->getUser();
		$services = MediaWikiServices::getInstance();
		$userGroupManager = $services->getUserGroupManager();
		$userGroup = $userGroupManager->getUserGroups( $user );
		$userRights = $services->getPermissionManager()->getUserPermissions( $user );

        $result = '';

		foreach ( $contents as $content ) {
			if ( !$content ) {
				break;
			}
			if (
				( $content['right'] && !in_array( $content['right'], $userRights ) ) ||
				( $content['group'] && !in_array( $content['group'], $userGroup ) )
			) {
				continue;
			}

			$result .= Html::openElement( 'li', [
				'class' => [ 'dropdown', 'nav-item' ]
			] );

			array_push( $content['classes'], 'nav-link' );

			if ( is_array( $content['children'] ) && count( $content['children'] ) > 1 ) {
				array_push( $content['classes'], 'dropdown-toggle', 'dropdown-toggle-fix' );
			}

			$result .= Html::openElement( 'a', [
				'class' => $content['classes'],
				'data-toggle' => is_array( $content['children'] ) &&
					count( $content['children'] ) > 1 ? 'dropdown' : '',
				'role' => 'button',
				'aria-haspopup' => 'true',
				'aria-expanded' => 'true',
				'title' => $content['title'],
				'href' => $content['href']
			] );

			if ( isset( $content['icon'] ) ) {
				$result .= Html::rawElement( 'span', [
					'class' => 'fa fa-' . $content['icon']
				] );
			}

			if ( isset( $content['text'] ) && !empty( $content['text'] ) ) {
				$result .= Html::rawElement( 'span', [
					'class' => 'hide-title'
				], $content['text'] );
			}

			$result .= Html::closeElement( 'a' );

			if ( is_array( $content['children'] ) && count( $content['children'] ) ) {
				$result .= Html::openElement( 'div', [
					'class' => 'dropdown-menu',
					'role' => 'menu'
				] );

				foreach ( $content['children'] as $child ) {
					if (
						( $child['right'] && !in_array( $child['right'], $userRights ) ) ||
						( $child['group'] && !in_array( $child['group'], $userGroup ) )
					) {
						continue;
					}
					array_push( $child['classes'], 'dropdown-item' );

					if ( is_array( $child['children'] ) ) {
						array_push( $child['classes'], 'dropdown-toggle', 'dropdown-toggle-sub' );
					}

					$result .= Html::openElement( 'a', [
						'accesskey' => $child['access'],
						'class' => $child['classes'],
						'href' => $child['href'],
						'title' => $child['title']
					] );

					if ( isset( $child['icon'] ) ) {
						$result .= Html::rawElement( 'span', [
							'class' => 'fa fa-' . $child['icon']
						] );
					}

					if ( isset( $child['text'] ) ) {
						$result .= $child['text'];
					}

					$result .= Html::closeElement( 'a' );

					if (
						is_array( $content['children'] ) &&
						count( $content['children'] ) > 2 &&
						!empty( $child['children'] )
					) {
						$result .= Html::openElement( 'div', [
							'class' => 'dropdown-menu dropdown-submenu',
							'role' => 'menu'
						] );

						foreach ( $child['children'] as $sub ) {
							if (
								( $sub['right'] && !in_array( $sub['right'], $userRights ) ) ||
								( $sub['group'] && !in_array( $sub['group'], $userGroup ) )
							) {
								continue;
							}
							array_push( $sub['classes'], 'dropdown-item' );

							$result .= Html::openElement( 'a', [
								'accesskey' => $sub['access'],
								'class' => $sub['classes'],
								'href' => $sub['href'],
								'title' => $sub['title']
							] );

							if ( isset( $sub['icon'] ) ) {
								$result .= Html::rawElement( 'span', [
									'class' => 'fa fa-' . $sub['icon']
								] );
							}

							if ( isset( $sub['text'] ) ) {
								$result .= $sub['text'];
							}

							$result .= Html::closeElement( 'a' );
						}

						$result .= Html::closeElement( 'div' );
					}
				}

				$result .= Html::closeElement( 'div' );
			}

			$result .= Html::closeElement( 'li' );
		}

        return $result;
	}

	protected function getUserAvatar() {
		global $wgNewLibertyUseGravatar;
		$user = $this->getUser();

		$avatar = '';

    	// If the user is logged in...
		if ( $user->isRegistered() ) {
			// ...and Gravatar is enabled in site config...
			if ( $wgNewLibertyUseGravatar ) {
				// ...and the user has a confirmed email...
				if ( $user->getEmailAuthenticationTimestamp() ) {
					// ...then, and only then, build the correct Gravatar URL
					$email = trim( $user->getEmail() );
					$email = strtolower( $email );
					$email = md5( $email ) . '?d=identicon';
				} else {
					$email = '00000000000000000000000000000000?d=identicon&f=y';
				}
				$avatar = Html::element( 'img', [
					'class' => 'profile-img',
					'src' => '//secure.gravatar.com/avatar/' . $email
				] );
			}

			// SocialProfile support
			if ( class_exists( 'wAvatar' ) ) {
				$avatar = new wAvatar( $user->getId(), 'm' );
				$avatar = $avatar->getAvatarURL( [
					'class' => 'profile-img'
				] );
			}
		}

		return $avatar;
	}

	/**
	 * Parse [[MediaWiki:Liberty-Navbar]].
	 *
	 * Its format is:
	 * * <icon name>|Name of the menu displayed to the user
	 * ** link target|Link title (can be the name of an interface message)
	 *
	 * @return array Menu data
	 */
	protected function parseNavbar() {
		global $wgArticlePath;

		$headings = [];
		$currentHeading = null;
		$skin = $this->getSkin();
		$userName = $skin->getUser()->getName();
		$userLang = $skin->getLanguage()->mCode;
		$globalData = $this->getContentText( $this->getContentOfTitle(
			Title::newFromText( 'Liberty-Navbar', NS_MEDIAWIKI )
		) );
		$globalLangData = $this->getContentText( $this->getContentOfTitle(
			Title::newFromText( 'Liberty-Navbar/' . $userLang, NS_MEDIAWIKI )
		) );
		$userData = $this->getContentText( $this->getContentOfTitle(
			Title::newFromText( $userName . '/Liberty-Navbar', NS_USER )
		) );
		if ( !empty( $userData ) ) {
			$data = $userData;
		} elseif ( !empty( $globalLangData ) ) {
			$data = $globalLangData;
		} else {
			$data = $globalData;
		}
		// Well, [[MediaWiki:Liberty-Navbar]] *should* have some content, but
		// if it doesn't, bail out here so that we don't trigger E_NOTICEs
		// about undefined indexes later on
		if ( empty( $data ) ) {
			return $headings;
		}

		$lines = explode( "\n", $data );

		$types = [ 'icon', 'display', 'title', 'link', 'access', 'class' ];

		foreach ( $lines as $line ) {
			$line = rtrim( $line, "\r" );
			if ( $line[0] !== '*' ) {
				// Line does not start with '*'
				continue;
			}
			if ( $line[1] !== '*' ) {
				// First level menu
				$data = [];
				$split = explode( '|', $line );
				$split[0] = substr( $split[0], 1 );
				foreach ( $split as $key => $value ) {
					$valueArr = explode( '=', trim( $value ) );
					if ( isset( $valueArr[1] ) ) {
						$newValue = implode( '=', array_slice( $valueArr, 1 ) );
						$data[$valueArr[0]] = $newValue;
					} else {
						$data[$types[$key]] = trim( $value );
					}
				}

				// Initialize item
				$icon = isset( $data['icon'] ) ? htmlentities( $data['icon'], ENT_QUOTES, 'UTF-8' ) : null;
				$group = isset( $data['group'] ) ? htmlentities( $data['group'], ENT_QUOTES, 'UTF-8' ) : null;
				$right = isset( $data['right'] ) ? htmlentities( $data['right'], ENT_QUOTES, 'UTF-8' ) : null;

				// Parse display
				$text = '';
				if ( isset( $data['display'] ) ) {
					$textObj = $skin->msg( $data['display'] );
					if ( $textObj->isDisabled() ) {
						$text = htmlentities( $data['display'], ENT_QUOTES, 'UTF-8' );
					} else {
						$text = $textObj->text();
					}
				}

				// Parse iitle
				$title = '';
				if ( isset( $data['title'] ) ) {
					$titleObj = $skin->msg( $data['title'] );
					if ( $titleObj->isDisabled() ) {
						$title = htmlentities( $data['title'], ENT_QUOTES, 'UTF-8' );
					} else {
						$title = $titleObj->text();
					}
				} else {
					$title = $text;
				}
				$split[0] = substr( $split[0], 1 );
				foreach ( $split as $key => $value ) {
					$valueArr = explode( '=', trim( $value ) );
					if ( isset( $valueArr[1] ) ) {
						$newValue = implode( '=', array_slice( $valueArr, 1 ) );
						$data[$valueArr[0]] = $newValue;
					} else {
						$data[$types[$key]] = trim( $value );
					}
				}

				// Parse Icon
				$icon = isset( $data['icon'] ) ? htmlentities( $data['icon'], ENT_QUOTES, 'UTF-8' ) : null;

				// Parse Group
				$group = isset( $data['group'] ) ? htmlentities( $data['group'], ENT_QUOTES, 'UTF-8' ) : null;

				// Parse Right
				$right = isset( $data['right'] ) ? htmlentities( $data['right'], ENT_QUOTES, 'UTF-8' ) : null;

				// support the usual [[MediaWiki:Sidebar]] syntax of
				// ** link target|<some MW: message name> and if the
				// thing on the right side of the pipe isn't the name of a MW:
				// message, then and _only_ then render it as-is
				if ( isset( $data['display'] ) ) {
					$textObj = $skin->msg( $data['display'] );
					if ( $textObj->isDisabled() ) {
						$text = htmlentities( $data['display'], ENT_QUOTES, 'UTF-8' );
					} else {
						$text = $textObj->text();
					}
				} else {
					$text = '';
				}

				// If icon and text both empty
				if ( ( !isset( $icon ) && !isset( $text ) ) || ( empty( $icon ) && empty( $text ) ) ) {
					continue;
				}

				// Title
				if ( isset( $data['title'] ) ) {
					$titleObj = $skin->msg( $data['title'] );
					if ( $titleObj->isDisabled() ) {
						$title = htmlentities( $data['title'], ENT_QUOTES, 'UTF-8' );
					} else {
						$title = $titleObj->text();
					}
				} else {
					if ( isset( $text ) ) {
						$title = $text;
					}
				}

				// Link href
				if ( isset( $data['link'] ) ) {
					// @todo CHECKME: Should this use wfUrlProtocols() or somesuch instead?
					if ( preg_match( '/^((?:(?:http(?:s)?)?:)?\/\/(?:.{4,}))$/i', $data['link'] ) ) {
						$href = htmlentities( $data['link'], ENT_QUOTES, 'UTF-8' );
					} else {
						$href = str_replace( '%3A', ':', urlencode( $data['link'] ) );
						$href = str_replace( '$1', $href, $wgArticlePath );
					}
				} else {
					$href = null;
				}

				if ( isset( $data['access'] ) ) {
					// Access
					$access = preg_match( '/^([0-9a-z]{1})$/i', $data['access'] ) ? $data['access'] : '';
				} else {
					$access = null;
				}

				if ( isset( $data['class'] ) ) {
					// Classes
					$classes = explode( ',', htmlentities( $data['class'], ENT_QUOTES, 'UTF-8' ) );
					foreach ( $classes as $key => $value ) {
						$classes[$key] = trim( $value );
					}
				} else {
					$classes = [];
				}
				// @codingStandardsIgnoreStart
				$item = [
					'access' => $access,
					'classes' => $classes,
					'href' => $href,
					'icon' => @$icon,
					'text' => @$text,
					'title' => $title,
					'group' => $group,
					'right' => $right
				];
				// @codingStandardsIgnoreEnd
				$level2Children = &$item['children'];
				$headings[] = $item;
				continue;
			}
			if ( $line[2] !== '*' ) {
				// Second level menu
				// Initialize item
				$icon = null;
				$text = null;
				$title = null;
				$href = null;
				$access = null;
				$classes = [];
				$group = null;
				$right = null;

				$data = [];
				$split = explode( '|', $line );
				$split[0] = substr( $split[0], 2 );
				foreach ( $split as $key => $value ) {
					$valueArr = explode( '=', trim( $value ) );
					if ( isset( $valueArr[1] ) ) {
						$data[$valueArr[0]] = $valueArr[1];
					} else {
						$data[$types[$key]] = trim( $value );
					}
				}

				// Icon
				$icon = isset( $data['icon'] ) ? htmlentities( $data['icon'], ENT_QUOTES, 'UTF-8' ) : null;

				// Group
				$group = isset( $data['group'] ) ? htmlentities( $data['group'], ENT_QUOTES, 'UTF-8' ) : null;

				// Right
				$right = isset( $data['right'] ) ? htmlentities( $data['right'], ENT_QUOTES, 'UTF-8' ) : null;

				// support the usual [[MediaWiki:Sidebar]] syntax of
				// ** link target|<some MW: message name> and if the
				// thing on the right side of the pipe isn't the name of a MW:
				// message, then and _only_ then render it as-is
				if ( isset( $data['display'] ) ) {
					$textObj = $skin->msg( $data['display'] );
					if ( $textObj->isDisabled() ) {
						$text = htmlentities( $data['display'], ENT_QUOTES, 'UTF-8' );
					} else {
						$text = $textObj->text();
					}
				} else {
					$text = '';
				}

				// If icon and text both empty
				if ( empty( $icon ) && empty( $text ) ) {
					continue;
				}

				// Title
				if ( isset( $data['title'] ) ) {
					$titleObj = $skin->msg( $data['title'] );
					if ( $titleObj->isDisabled() ) {
						$title = htmlentities( $data['title'], ENT_QUOTES, 'UTF-8' );
					} else {
						$title = $titleObj->text();
					}
				} else {
					$title = $text;
				}

				if ( isset( $data['link'] ) ) {
					// Link href
					// @todo CHECKME: Should this use wfUrlProtocols() or somesuch instead?
					if ( preg_match( '/^((?:(?:http(?:s)?)?:)?\/\/(?:.{4,}))$/i', $data['link'] ) ) {
						$href = htmlentities( $data['link'], ENT_QUOTES, 'UTF-8' );
					} else {
						$href = str_replace( '%3A', ':', urlencode( $data['link'] ) );
						$href = str_replace( '$1', $href, $wgArticlePath );
					}
				}

				if ( isset( $data['access'] ) ) {
					// Access
					$access = preg_match( '/^([0-9a-z]{1})$/i', $data['access'] ) ? $data['access'] : '';
				} else {
					$access = null;
				}

				if ( isset( $data['class'] ) ) {
					// Classes
					$classes = explode( ',', htmlentities( $data['class'], ENT_QUOTES, 'UTF-8' ) );
					foreach ( $classes as $key => $value ) {
						$classes[$key] = trim( $value );
					}
				} else {
					$classes = [];
				}

				$item = [
					'access' => $access,
					'classes' => $classes,
					'href' => $href,
					'icon' => $icon,
					'text' => $text,
					'title' => $title,
					'group' => $group,
					'right' => $right
				];
				$level3Children = &$item['children'];
				$level2Children[] = $item;
				continue;
			}
			if ( $line[3] !== '*' ) {
				// Third level menu
				// Initialize item
				$icon = null;
				$text = null;
				$title = null;
				$href = null;
				$access = null;
				$classes = [];
				$group = null;
				$right = null;

				$data = [];
				$split = explode( '|', $line );
				$split[0] = substr( $split[0], 3 );
				foreach ( $split as $key => $value ) {
					$valueArr = explode( '=', trim( $value ) );
					if ( isset( $valueArr[1] ) ) {
						$data[$valueArr[0]] = $valueArr[1];
					} else {
						$data[$types[$key]] = trim( $value );
					}
				}

				// Icon
				$icon = isset( $data['icon'] ) ? htmlentities( $data['icon'], ENT_QUOTES, 'UTF-8' ) : null;

				// Group
				$group = isset( $data['group'] ) ? htmlentities( $data['group'], ENT_QUOTES, 'UTF-8' ) : null;

				// Right
				$right = isset( $data['right'] ) ? htmlentities( $data['right'], ENT_QUOTES, 'UTF-8' ) : null;

				// support the usual [[MediaWiki:Sidebar]] syntax of
				// ** link target|<some MW: message name> and if the
				// thing on the right side of the pipe isn't the name of a MW:
				// message, then and _only_ then render it as-is
				if ( isset( $data['display'] ) ) {
					$textObj = $skin->msg( $data['display'] );
					if ( $textObj->isDisabled() ) {
						$text = htmlentities( $data['display'], ENT_QUOTES, 'UTF-8' );
					} else {
						$text = $textObj->text();
					}
				} else {
					$text = '';
				}

				// If icon and text both empty
				if ( empty( $icon ) && empty( $text ) ) {
					continue;
				}

				// Title
				if ( isset( $data['title'] ) ) {
					$titleObj = $skin->msg( $data['title'] );
					if ( $titleObj->isDisabled() ) {
						$title = htmlentities( $data['title'], ENT_QUOTES, 'UTF-8' );
					} else {
						$title = $titleObj->text();
					}
				} else {
					if ( isset( $text ) ) {
						$title = $text;
					} else {
						$title = '';
					}
				}

				// Link href
				// @todo CHECKME: Should this use wfUrlProtocols() or somesuch instead?
				if ( preg_match( '/^((?:(?:http(?:s)?)?:)?\/\/(?:.{4,}))$/i', $data['link'] ) ) {
					$href = htmlentities( $data['link'], ENT_QUOTES, 'UTF-8' );
				} else {
					$href = str_replace( '%3A', ':', urlencode( $data['link'] ) );
					$href = str_replace( '$1', $href, $wgArticlePath );
				}

				// Access
				if ( isset( $data['access'] ) ) {
					$access = preg_match( '/^([0-9a-z]{1})$/i', $data['access'] ) ? $data['access'] : '';
				} else {
					$access = null;
				}

				if ( isset( $data['class'] ) ) {
					// Classes
					$classes = explode( ',', htmlentities( $data['class'], ENT_QUOTES, 'UTF-8' ) );
					foreach ( $classes as $key => $value ) {
						$classes[$key] = trim( $value );
					}
				} else {
					$classes = [];
				}

				$item = [
					'access' => $access,
					'classes' => $classes,
					'href' => $href,
					'icon' => $icon,
					'text' => $text,
					'title' => $title,
					'group' => $group,
					'right' => $right
				];
				$level3Children[] = $item;
				continue;
			} else {
				// Not supported
				continue;
			}
		}

		return $headings;
	}

	/**
	 * Helper function for parseNavbar() to not trigger deprecation warnings on MW 1.37+ and to continue
	 * functioning on MW 1.43+.
	 *
	 * @param Content|null $content
	 * @return string|null Textual form of the content, if available.
	 */
	private function getContentText( ?Content $content = null ) {
		if ( $content === null ) {
			return '';
		}

		if ( $content instanceof TextContent ) {
			return $content->getText();
		}

		return null;
	}

	private function getContentOfTitle( Title $title ): ?Content {
		$page = null;

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			$page = $wikiPageFactory->newFromTitle( $title );
		} else {
			$page = WikiPage::factory( $title );
		}

		return $page->getContent( RevisionRecord::RAW );
	}
}

?>