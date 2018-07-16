<?php
/*
Addon Name: Add WP excerpt on import
Description: Experimental rss import addon - Trims description to fit and adds to excerpt.
Author: Daniel J. Schneider
Author URI: http://www.scrollwright.com
*/

//This function intelligently trims a body of text to a certain
//number of words, but will not break a sentence.
function smart_trim($instring, $truncation) {
	//a little regex kills datelines
    $instring = preg_replace("/\A((([A-Z ]+)\\,\s?([a-zA-Z ]+)\\.?)|[A-Z]+)\s?(&#8211;|&#8212;?)\s?/u", "", $instring);
    //replaye closing paragraph tags with a space to avoid collisions after punctuation
    $instring = str_replace("</p>", " ", $instring);
    //strip the HTML tags and then kill the entities
    $string = html_entity_decode( (strip_tags($instring) ), ENT_QUOTES, 'UTF-8');

    $matches = preg_split("/\s+/", $string);
    $count = count($matches);

    if($count > $truncation) {
        //Grab the last word; we need to determine if
        //it is the end of the sentence or not
        $last_word = strip_tags($matches[$truncation-1]);
        $lw_count = strlen($last_word);

        //The last word in our truncation has a sentence ender
        if($last_word[$lw_count-1] == "." || $last_word[$lw_count-1] == "?" || $last_word[$lw_count-1] == "!") {
            for($i=$truncation;$i<$count;$i++) {
                unset($matches[$i]);
            }

        //The last word in our truncation doesn't have a sentence ender, find the next one
        } else {
            //Check each word following the last word until
            //we determine a sentence's ending
            $ending_found = FALSE;
            for($i=($truncation);$i<$count;$i++) {
                if($ending_found != TRUE) {
                    $len = strlen(strip_tags($matches[$i]));
                    if($matches[$i][$len-1] == "." || $matches[$i][$len-1] == "?" || $matches[$i][$len-1] == "!") {
                        //Test to see if the next word starts with a capital
                        if($matches[$i+1][0] == strtoupper($matches[$i+1][0])) {
                            $ending_found = TRUE;
                        }
                    }
                } else {
                    unset($matches[$i]);
                }
            }
        }
        $body = implode(' ', $matches);
        return $body;
    } else {
        return $string;
    }
}

class A_ExcerptCacheAddon {

	function __construct() {

		add_action( 'autoblog_post_post_insert', array(&$this, 'process_excerpt'), 10, 3 );
	}

	function A_ExcerptCacheAddon() {
		$this->__construct();
	}

	function process_excerpt( $post_ID, $ablog, $item ) {

		set_time_limit(300);

		// excerpt
		$posting['post_excerpt'] = smart_trim($item->get_description(), 20);
		$posting['ID'] = $post_ID;
		$post_ID = wp_update_post($posting);

		// Returning the $post_ID even though it's an action and we really don't need to
		return $post_ID;
	}
}

$aexcerptcacheaddon = new A_ExcerptCacheAddon();

?>
