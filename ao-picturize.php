<?php
/*
Plugin Name: AO picturize
Plugin URI: http://blog.futtta.be/
Description: AO power-up to use picture to do avif/ webp
Author: Frank Goossens (futtta)
Version: 0.6.0
Author URI: http://blog.futtta.be/
*/

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    if ( class_exists( 'autoptimizeImages' ) && autoptimizeImages::imgopt_active() ) {
        if ( ! defined( 'AO_IMGOPT_HOST' ) ) {
            define( 'AO_IMGOPT_HOST', parse_url( autoptimizeImages::get_imgopt_host_wrapper(), PHP_URL_HOST ) );
        }
        add_filter( 'autoptimize_filter_imgopt_should_lazyload', '__return_false' );
        add_filter( 'autoptimize_filter_imgopt_tag_postopt', 'ao_img_to_picture' );
    }

    if ( is_admin() ) {
        require 'plugin-update-checker/plugin-update-checker.php';
        $ao_picturize_pce_UpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/futtta/ao-picturize',
            __FILE__,
            'ao-picturize'
        );
        $ao_picturize_pce_UpdateChecker->setBranch('main');
        
        if ( ! version_compare( AUTOPTIMIZE_PLUGIN_VERSION, '2.8.1', '>') ) { ?>
            <div class="notice-warning notice"><p>
            <?php
            _e( 'The Autoptimize picturize power-up requires the latest version of the Autoptimize Beta, download it from <a href="https://github.com/futtta/autoptimize/archive/beta.zip" target="_blank">Github here</a>', 'autoptimize' );
            ?>
            </p></div>
        <?php }
    }
});

function ao_img_to_picture( $tag ) {
    // should we check & bail if picture tag is already used? can we do that here?

    static $_exclusions = null;
    if ( null === $_exclusions ) {
        // default excluded from being picturized.
        $_exclusions = array( 'data:image/', 'lazyload', 'rev-slidebg' );

        // and reusing user-set lazyload exclusions.
        $imgopt_options = autoptimizeImages::fetch_options();
        if ( array_key_exists( 'autoptimize_imgopt_text_field_5', $imgopt_options ) ) {
            $exclude_lazyload_option = $imgopt_options['autoptimize_imgopt_text_field_5'];
            if ( ! empty( $exclude_lazyload_option ) ) {
                $_exclusions = array_merge( $_exclusions, array_filter( array_map( 'trim', explode( ',', $imgopt_options['autoptimize_imgopt_text_field_5'] ) ) ) );
            }
        }

        // and finally filter for developer-initiated changes again assuming the lazyload ones apply too.
        $_exclusions = apply_filters( 'autoptimize_filter_imgopt_lazyload_exclude_array', $_exclusions );
    }

    if ( strpos( $tag, AO_IMGOPT_HOST ) === false || str_ireplace( $_exclusions, '', $tag ) !== $tag ) {
        return $tag;
    }

    $attributes         = ao_get_main_attribs( $tag );
    $picture_attributes = ao_build_picture_attributes( $attributes );
    $tmp_tag            = ao_img_prepare_for_source( $tag );
    
    $newtag  =  '<picture ' . $picture_attributes . '>';
    $newtag .= ao_picture_source_ngimg( $tmp_tag, 'avif' );
    $newtag .= ao_picture_source_ngimg( $tmp_tag, 'webp' );
    $newtag .= $tag; // we keep id, class & style on img tag as that is the image placeholder, based on the specs the picture/ source combo will re-populate the img with the correct source.
    $newtag .= '</picture>';
    $newtag  = preg_replace( '/\s{2,}/u', ' ', $newtag ); // remove superflous whitespace after the preg_replace.
    $newtag  = apply_filters( 'autoptimize_filter_imgopt_picture_newtag', $newtag ); // and we like our filters.

    return $newtag;
}

function ao_img_prepare_for_source( $tag ) {
    $newsource = str_replace( '<img ', '<source ', $tag );
    if ( strpos( $tag, ' srcset=' ) === false ) {
        $newsource = str_replace( ' src=', ' srcset=', $newsource );
    }
    $newsource = preg_replace( apply_filters( 'autoptimize_filter_imgopt_picture_source_tag_blocklist_regex', '/\s((?:id|width|height|alt|src|class|loading)=(?:\'|").*(?:\'|"))(>)?/Um') , '$2 ', $newsource ); // remove unwanted attribs.

    return $newsource;      
}

function ao_picture_source_ngimg( $newsource, $type ) {
    if ( strpos( $newsource, AO_IMGOPT_HOST ) !== false && strpos( $newsource, '/client/' ) !== false ) {
        $newsource = str_replace( '/client/', '/client/to_' . $type . ',', $newsource );
        $newsource = str_replace( '/>', ' type="image/' . $type . '" />', $newsource );
    }

    return $newsource;
}

function ao_get_main_attribs( $tag ) {
    if ( class_exists('DOMDocument') ) {
        $dom = new \DOMDocument();
        @$dom->loadHTML( $tag );
        $image = $dom->getElementsByTagName( 'img' )->item(0);
        $attributes = array();

        foreach ( $image->attributes as $attr ) {
            $_attr[$attr->nodeName] = $attr->nodeValue;
        }
    } else {
        foreach ( array( 'id', 'class', 'title', 'style', 'alt' ) as $attrname ) {
            preg_match('/ (' . $attrname . '=(?:\'|").*(?:\'|")) /Um', $tag, $matches );
            $_attr[$attrname] = $matches[1];
        }
    }

    return $_attr;
}

function ao_build_picture_attributes( $attributes ) {
    $picture_attribs           = '';
    $picture_attribs_blocklist = array( 'id', 'class', 'style', 'alt', 'height', 'width', 'data-lazy-src', 'data-src', 'src', 'data-lazy-srcset', 'data-srcset', 'srcset', 'data-sizes', 'sizes', 'loading' );
    $picture_attribs_blocklist = apply_filters( 'autoptimize_filter_imgopt_picture_tag_blocklist_array', $picture_attribs_blocklist );
    foreach ( $attributes as $attrib_name => $attrib_val ) {
        if ( ! empty( $attrib_val ) && ! in_array( $attrib_name, $picture_attribs_blocklist ) ) {
            $_attrib          = trim( $attrib_name ) . '="' . trim( $attrib_val ) . '" ';
            $picture_attribs .= $_attrib;
        }
    }

    return $picture_attribs;
}
