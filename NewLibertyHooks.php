<?php

class NewLibertyHooks {
    /**
	 * @since 1.17.0
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param array &$bodyAttrs
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, &$bodyAttrs ) {
		if ( $sk->getSkinName() === 'new-liberty' ) {
			$bodyAttrs['class'] .= ' Liberty width-size';
		}
	}
}

?>