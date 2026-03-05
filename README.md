# New Liberty MediaWiki Skin

A rewritten version of [Liberty skin](https://github.com/librewiki/liberty-skin), using SkinMustache and portlets. Original skin is licensed under the GNU General Public License v3.0.

## Installation
* Place the skin folder in your `skins/` directory. The directory should be named `NewLiberty`.
* Add the following to your `LocalSettings.php`: `wfLoadSkin( 'NewLiberty' );`

## Configuration
You can configure the skin by adding the following to your `LocalSettings.php`:
* `$wgNewLibertyPrimaryColor`
* `$wgNewLibertySecondaryColor`
* `$wgNewLibertyUseGravatar`
* `$wgNewLibertyEnableLiveRC`
* `$wgNewLibertyLiveRCArticleNamespaces`
* `$wgNewLibertyLiveRCTalkNamespaces`
* `$wgNewLibertyMaxRecent`

### Navigation Menu
Fill out `MediaWiki:new-liberty-navbar` with the desired menu items. The format is as follows:

`* icon=(Icon) | display=(Display) | title=(Hover text) | link=(Link to page) | access=(Access key) | class=(Custom classname) | group=(Required group name) | right=(Required right name)`

Submenu items can by added by nesting the bullet list up to 3 levels.
