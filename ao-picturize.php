<?php
/*
Plugin Name: AO picturize
Plugin URI: http://blog.futtta.be/
Description: AO power-up to use picture to do avif/ webp
Author: Frank Goossens (futtta)
Version: 0.0.1
Author URI: http://blog.futtta.be/
*/

// fixme: add filters

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    if ( class_exists( 'autoptimizeImages' ) && autoptimizeImages::imgopt_active() ) {
        add_filter( 'autoptimize_filter_imgopt_should_lazyload', '__return_false' );
        add_filter( 'autoptimize_filter_imgopt_ngimg_js', '__return_empty_string' );
        add_filter( 'autoptimize_filter_imgopt_tag_postopt', 'ao_img_to_picture' );
    }
});


function ao_img_to_picture( $tag ) {
    // should we check & bail if picture tag is already used? can we do that here?
    if ( strpos( $tag, parse_url( autoptimizeImages::get_imgopt_host_wrapper(), PHP_URL_HOST ) ) === false ) {
        return $tag;
    }

    $attributes         = ao_get_main_attribs( $tag );
    $picture_attributes = ao_build_picture_attributes( $attributes );
    $tmp_tag            = ao_img_prepare_for_source( $tag );
    
    $newtag =  '<picture ' . $picture_attributes . '>';
    $newtag .= ao_picture_source_ngimg( $tmp_tag, 'avif' );
    $newtag .= ao_picture_source_ngimg( $tmp_tag, 'webp' );
    $newtag .= str_replace( $attributes['id'], '', $tag ); // remove id, we let class, style & title stay (for now).
    $newtag .= '</picture>';

    return $newtag;
}

function ao_img_prepare_for_source( $tag ) {
    $newsource = str_replace( '<img ', '<source ', $tag );
    if ( strpos( $tag, ' srcset=' ) === false ) {
        $newsource = str_replace( ' src=', ' srcset=', $newsource );
    }
    $newsource = preg_replace( '/\s((?:id|width|height|alt|src|class|loading)=(?:\'|").*(?:\'|"))(>)?/Um', '$2 ', $newsource ); // remove unwanted attribs.
    $newsource = str_replace( array( '  ', '   ', '    ' ), ' ', $newsource ); // remove superflous whitespace after the preg_replace.

    return $newsource;      
}

function ao_picture_source_ngimg( $newsource, $type ) {
    // switch to new gen image format, but NOT for lqip's!
    $source_parts = explode( '=', $newsource );
    foreach ( $source_parts as &$part ) {
        if ( strpos( $part, 'https://') !== false && strpos( $part, 'q_lqip') === false ) {
            $part = str_replace( '/client/', '/client/to_' . $type . ',', $part );
            $part = str_replace( '/>', ' type="image/' . $type . '" />', $part );
        }
    }       
    $newsource = implode( '=', $source_parts );

    return $newsource;
}

function ao_get_main_attribs( $tag ) {
    $must_have_attrs = array( 'id', 'class', 'title', 'style', 'alt' );
    
    if ( class_exists('DOMDocument') ) {
        $dom = new \DOMDocument();
        @$dom->loadHTML( $tag );
        $image = $dom->getElementsByTagName( 'img' )->item(0);
        $attributes = array();

        foreach ( $image->attributes as $attr ) {
            $_attr[$attr->nodeName] = $attr->nodeValue;
        }
    } else {
        foreach ( $must_have_attrs as $attrname ) {
            preg_match('/ (' . $attrname . '=(?:\'|").*(?:\'|")) /Um', $tag, $matches );
            $_attr[$attrname] = $matches[1];
        }
    }

    // remove lazyload (should not be there anyway as we disable it).
    $_attr['class'] = str_replace( 'lazyload', '', $_attr['class'] );
    
    // re-instate native lazyloading?
    /* if ( ! array_key_exists( 'loading', $_attr ) ) {
        $_attr['loading'] = 'lazy'; // but only in <img ? see https://addyosmani.com/blog/lazy-loading/ fixme!
    } */

    // make sure we have 'id' even if empty so we can replace.
    if ( ! array_key_exists( 'id', $_attr ) ) {
        $_attr['id'] = '';
    }

    return $_attr;
}

function ao_build_picture_attributes( $attributes ) {
    $picture_attribs           = '';
    $picture_attribs_blocklist = array( 'alt', 'height', 'width', 'data-lazy-src', 'data-src', 'src', 'data-lazy-srcset', 'data-srcset', 'srcset', 'data-sizes', 'sizes', 'loading' );
    foreach ( $attributes as $attrib_name => $attrib_val ) {
        if ( ! empty( $attrib_val ) && ! in_array( $attrib_name, $picture_attribs_blocklist ) ) {
            $_attrib          = trim( $attrib_name ) . '="' . trim( $attrib_val ) . '" ';
            $picture_attribs .= $_attrib;
        }
    }

    return $picture_attribs;
}
