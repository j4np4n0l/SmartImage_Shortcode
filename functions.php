<?php

function wp_dynamic_filename_responsive_image_shortcode($atts) {
    // 1. Accept 'name' along with custom 'alt' and size parameter overrides
    $args = shortcode_atts(array(
        'name'    => '', 
        'alt'     => '', // Direct shortcode override
        'desktop' => '1200', 
        'tablet'  => '768',
        'mobile'  => '400'
    ), $atts);

    if (empty($args['name'])) return '';

    $filename = sanitize_file_name($args['name']);
    
    // 2. Database Lookup: Find the Attachment ID from the filename
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_wp_attached_file' 
         AND meta_value LIKE %s 
         LIMIT 1",
        '%' . $wpdb->esc_like($filename)
    ));

    if (!$id) return '<!-- Smart Image Error: File "' . esc_html($filename) . '" not found in media library -->';
    
    $id = intval($id);

    // PRIORITY: Use custom shortcode 'alt' attribute if provided; otherwise, use Media Library alt
    $alt_text = !empty($args['alt']) ? $args['alt'] : get_post_meta($id, '_wp_attachment_image_alt', true);

    // Helper function to dynamically find or instantly slice the image on the fly
    $get_dynamic_img_url = function($attachment_id, $target_width) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) return wp_get_attachment_image_url($attachment_id, 'full');

        $metadata = wp_get_attachment_metadata($attachment_id);
        $size_key = 'dynamic_' . $target_width;

        if (isset($metadata['sizes'][$size_key])) {
            return wp_get_attachment_image_url($attachment_id, $size_key);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $editor = wp_get_image_editor($file_path);
        if (!is_wp_error($editor)) {
            $sizes = $editor->get_size();
            $ratio = $sizes['height'] / $sizes['width'];
            $target_height = round($target_width * $ratio);

            $resized = image_make_intermediate_size($file_path, $target_width, $target_height, false);
            
            if ($resized && !is_wp_error($resized)) {
                $metadata['sizes'][$size_key] = $resized;
                wp_update_attachment_metadata($attachment_id, $metadata);
                
                return wp_get_attachment_image_url($attachment_id, $size_key);
            }
        }
        return wp_get_attachment_image_url($attachment_id, 'full');
    };

    // 3. Fetch the custom dynamic image sizes
    $desktop_url = $get_dynamic_img_url($id, intval($args['desktop']));
    $tablet_url  = $get_dynamic_img_url($id, intval($args['tablet']));
    $mobile_url  = $get_dynamic_img_url($id, intval($args['mobile']));
    $fallback_url = wp_get_attachment_image_url($id, 'large');

    // Isolate base paths to cleanly swap .webp and .avif extensions
    $get_base = function($url) {
        return pathinfo($url, PATHINFO_DIRNAME) . '/' . pathinfo($url, PATHINFO_FILENAME);
    };

    $desktop_base = $get_base($desktop_url);
    $tablet_base  = $get_base($tablet_url);
    $mobile_base  = $get_base($mobile_url);

    // 4. Output the complete modern responsive picture template
    $html = '<picture>';
    
    // Desktop layout rules
    $html .= '  <source media="(min-width: ' . (intval($args['tablet']) + 1) . 'px)" srcset="' . esc_url($desktop_base . '.avif') . '" type="image/avif">';
    $html .= '  <source media="(min-width: ' . (intval($args['tablet']) + 1) . 'px)" srcset="' . esc_url($desktop_base . '.webp') . '" type="image/webp">';
    $html .= '  <source media="(min-width: ' . (intval($args['tablet']) + 1) . 'px)" srcset="' . esc_url($desktop_url) . '">';

    // Tablet layout rules
    $html .= '  <source media="(min-width: ' . (intval($args['mobile']) + 1) . 'px)" srcset="' . esc_url($tablet_base . '.avif') . '" type="image/avif">';
    $html .= '  <source media="(min-width: ' . (intval($args['mobile']) + 1) . 'px)" srcset="' . esc_url($tablet_base . '.webp') . '" type="image/webp">';
    $html .= '  <source media="(min-width: ' . (intval($args['mobile']) + 1) . 'px)" srcset="' . esc_url($tablet_url) . '">';

    // Mobile layout rules
    $html .= '  <source srcset="' . esc_url($mobile_base . '.avif') . '" type="image/avif">';
    $html .= '  <source srcset="' . esc_url($mobile_base . '.webp') . '" type="image/webp">';
    
    // Standard img fallback (Outputs the finalized custom alt text)
    $html .= '  <img src="' . esc_url($fallback_url) . '" alt="' . esc_attr($alt_text) . '" loading="lazy">';
    $html .= '</picture>';

    return $html;
}
add_shortcode('smart_img', 'wp_dynamic_filename_responsive_image_shortcode');


?>


[smart_img name="banner-photo.jpg" alt="Summer collection promo banner" desktop="1440" tablet="960" mobile="480"]