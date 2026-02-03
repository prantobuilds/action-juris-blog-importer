<?php
/*
Plugin Name: Action Juris AI Blog Importer
Description: Fetch blogs from external API and publish WordPress posts with full content and media.
Version: 1.7
Author: Samiul H Pranto
Author URI: https://profiles.wordpress.org/samiulhpranto/
*/

if (!defined('ABSPATH'))
    exit;

// Enqueue admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'tools_page_actionjuris-blog-importer')
        return;

    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            $("form").on("submit", function() {
                $("#import-results").html("<div class=\"notice notice-info\"><p><strong>🔄 Fetching blogs from API... Please wait...</strong></p></div>");
                $("#import-results").show();
                $("input[type=\"submit\"]").prop("disabled", true).val("Importing...");
            });
        });
    ');
});

// Admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'Import Action Juris Blogs',
        'Import Action Juris Blogs',
        'manage_options',
        'actionjuris-blog-importer',
        'actionjuris_blog_importer_page'
    );
});

// Admin page HTML
function actionjuris_blog_importer_page()
{
    ?>
    <div class="wrap">
        <h1>Action Juris AI Blog Importer v1.7</h1>

        <div class="card">
            <h2>Import Settings</h2>
            <p>This will fetch all blogs from the Action Juris API and create WordPress posts.</p>
            <ul>
                <li>✅ Featured images will be stored locally</li>
                <li>✅ Other images will be hotlinked (not stored locally)</li>
                <li>✅ Both main and sub blogs will be imported</li>
                <li>✅ Existing posts will be skipped</li>
                <li>✅ Internal links between blogs will be created</li>
                <li>✅ Proper heading tags (H2) will be applied</li>
                <li>✅ Two-column image layouts supported</li>
                <li>✅ <strong>NEW:</strong> Country-based categories (gb, ca, us, etc.)</li>
                <li>✅ <strong>NEW:</strong> Automatic batch processing for large imports</li>
            </ul>
        </div>

        <form method="post">
            <input type="hidden" name="actionjuris_import_action" value="1">
            <?php submit_button('Fetch All Blogs from API (Main + Sub Blogs)'); ?>
        </form>

        <div id="import-results" style="display: none; margin-top: 20px;"></div>

        <?php
        if (isset($_POST['actionjuris_import_action'])) {
            echo '<div id="import-output">';
            actionjuris_import_blogs();
            echo '</div>';
            echo '<script>jQuery("input[type=\"submit\"]").prop("disabled", false).val("Fetch All Blogs from API (Main + Sub Blogs)");</script>';
        }
        ?>
    </div>

    <style>
        .import-status {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid;
        }

        .import-success {
            background: #f7f7f7;
            border-color: #46b450;
        }

        .import-skipped {
            background: #fff8e5;
            border-color: #ffb900;
        }

        .import-error {
            background: #ffe5e5;
            border-color: #dc3232;
        }

        .import-info {
            background: #e5f5ff;
            border-color: #0073aa;
        }

        .import-progress {
            background: #f0f0f1;
            padding: 10px;
            margin: 10px 0;
        }

        .import-batch {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
        }

        .batch-header {
            background: #e9e9e9;
            padding: 10px;
            margin: -15px -15px 15px -15px;
            font-weight: bold;
        }
    </style>
    <?php
}

// Main import function with batch processing
function actionjuris_import_blogs()
{
    echo '<div class="import-progress">';
    echo '<p><strong>🚀 Starting blog import process...</strong></p>';
    echo '</div>';

    $api_url = 'https://api.actionjuris.ca/api/get-all-blog/wp';
    $response = wp_remote_get($api_url, array('timeout' => 30));

    if (is_wp_error($response)) {
        echo '<div class="import-error">';
        echo '<p><strong>❌ Failed to fetch API:</strong> ' . esc_html($response->get_error_message()) . '</p>';
        echo '</div>';
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '<div class="import-error">';
        echo '<p><strong>❌ Invalid JSON response from API</strong></p>';
        echo '</div>';
        return;
    }

    if (empty($data['success'])) {
        echo '<div class="import-error">';
        echo '<p><strong>❌ API returned unsuccessful response</strong></p>';
        echo '</div>';
        return;
    }

    // First, collect all slugs for internal linking
    $all_slugs = actionjuris_collect_all_slugs($data);

    // Combine all blogs into one array
    $all_blogs = [];
    if (!empty($data['data'])) {
        $all_blogs = array_merge($all_blogs, $data['data']);
    }
    if (!empty($data['sub_data'])) {
        $all_blogs = array_merge($all_blogs, $data['sub_data']);
    }

    $total_blogs = count($all_blogs);

    if ($total_blogs === 0) {
        echo '<div class="import-skipped">';
        echo '<p>No blogs found to import.</p>';
        echo '</div>';
        return;
    }

    // Set safe batch size (15 blogs per batch is safe)
    $batch_size = 15;
    $total_batches = ceil($total_blogs / $batch_size);

    echo '<div class="import-info">';
    echo '<h3>📊 Import Statistics</h3>';
    echo '<p><strong>Total Blogs Found:</strong> ' . $total_blogs . '</p>';
    echo '<p><strong>Batch Size:</strong> ' . $batch_size . ' blogs per batch (safe limit)</p>';
    echo '<p><strong>Total Batches:</strong> ' . $total_batches . '</p>';
    echo '<p><strong>Estimated Time:</strong> ' . ceil($total_batches * 2) . ' - ' . ceil($total_batches * 3) . ' seconds</p>';
    echo '</div>';

    $imported_count = 0;
    $skipped_count = 0;
    $error_count = 0;

    // Process blogs in batches
    for ($batch = 0; $batch < $total_batches; $batch++) {
        $start = $batch * $batch_size;
        $batch_blogs = array_slice($all_blogs, $start, $batch_size);
        $batch_number = $batch + 1;

        echo '<div class="import-batch">';
        echo '<div class="batch-header">';
        echo '🔄 Processing Batch ' . $batch_number . ' of ' . $total_batches . ' (Blogs ' . ($start + 1) . ' to ' . min($start + $batch_size, $total_blogs) . ')';
        echo '</div>';

        foreach ($batch_blogs as $index => $blog) {
            $blog_number = $start + $index + 1;

            echo '<div class="import-progress">';
            echo '<p>Processing blog ' . $blog_number . ' of ' . $total_blogs . ' (Batch ' . $batch_number . ')</p>';
            echo '</div>';

            $result = actionjuris_import_single_blog($blog, $all_slugs);
            if ($result === 'imported') {
                $imported_count++;
            } elseif ($result === 'skipped') {
                $skipped_count++;
            } else {
                $error_count++;
            }

            // Flush output after each blog for real-time progress
            flush();
            ob_flush();
        }

        echo '</div>'; // Close batch div

        // Add 2-second delay between batches (except last batch)
        if ($batch < $total_batches - 1) {
            echo '<div class="import-info">';
            echo '<p>⏳ Pausing for 2 seconds before next batch...</p>';
            echo '</div>';

            // Flush output
            flush();
            ob_flush();

            sleep(2); // 2 second delay between batches
        }
    }

    // Final summary
    echo '<div class="import-success">';
    echo '<h3>✅ Import Complete!</h3>';
    echo '<p><strong>Summary:</strong></p>';
    echo '<ul>';
    echo '<li>✅ Imported: ' . $imported_count . ' new posts</li>';
    echo '<li>⚠️ Skipped: ' . $skipped_count . ' existing posts</li>';
    echo '<li>❌ Errors: ' . $error_count . ' posts</li>';
    echo '<li>📊 Total processed: ' . ($imported_count + $skipped_count + $error_count) . ' posts</li>';
    echo '<li>📦 Processed in ' . $total_batches . ' batches of ' . $batch_size . '</li>';
    echo '</ul>';
    echo '</div>';
}

// Collect all slugs for internal linking reference
function actionjuris_collect_all_slugs($data)
{
    $slugs = array();

    // Collect from main blogs (data array)
    if (!empty($data['data'])) {
        foreach ($data['data'] as $blog) {
            if (!empty($blog['slug'])) {
                $slugs[$blog['slug']] = array(
                    'title' => $blog['page_title'],
                    'id' => $blog['id']
                );
            }
        }
    }

    // Collect from sub blogs (sub_data array)
    if (!empty($data['sub_data'])) {
        foreach ($data['sub_data'] as $blog) {
            if (!empty($blog['slug'])) {
                $slugs[$blog['slug']] = array(
                    'title' => $blog['page_title'],
                    'id' => $blog['id']
                );
            }
        }
    }

    echo '<div class="import-info">';
    echo '<p>🔗 Found ' . count($slugs) . ' unique blog slugs for internal linking</p>';
    echo '</div>';

    return $slugs;
}

// Function to download and attach featured image
function actionjuris_download_featured_image($image_url, $post_id, $title)
{
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Download image to temp file
    $tmp = download_url($image_url);

    if (is_wp_error($tmp)) {
        return false;
    }

    // Get file extension and create proper filename
    $file_array = array(
        'name' => sanitize_file_name($title . '-featured-image.jpg'),
        'tmp_name' => $tmp
    );

    // Upload the image
    $attachment_id = media_handle_sideload($file_array, $post_id, $title);

    // Clean up temp file
    @unlink($tmp);

    if (is_wp_error($attachment_id)) {
        return false;
    }

    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);

    return $attachment_id;
}

// Create or get country category - FIXED VERSION
function actionjuris_get_country_category($country_code)
{
    if (empty($country_code)) {
        return 0; // No category if no country code
    }

    // Convert country code to readable name
    $country_names = [
        'af' => 'Afghanistan',
        'al' => 'Albania',
        'dz' => 'Algeria',
        'ad' => 'Andorra',
        'ao' => 'Angola',
        'ag' => 'Antigua and Barbuda',
        'ar' => 'Argentina',
        'am' => 'Armenia',
        'au' => 'Australia',
        'at' => 'Austria',
        'az' => 'Azerbaijan',
        'bs' => 'Bahamas',
        'bh' => 'Bahrain',
        'bd' => 'Bangladesh',
        'bb' => 'Barbados',
        'by' => 'Belarus',
        'be' => 'Belgium',
        'bz' => 'Belize',
        'bj' => 'Benin',
        'bt' => 'Bhutan',
        'bo' => 'Bolivia',
        'ba' => 'Bosnia and Herzegovina',
        'bw' => 'Botswana',
        'br' => 'Brazil',
        'bn' => 'Brunei',
        'bg' => 'Bulgaria',
        'bf' => 'Burkina Faso',
        'bi' => 'Burundi',
        'kh' => 'Cambodia',
        'cm' => 'Cameroon',
        'ca' => 'Canada',
        'cv' => 'Cape Verde',
        'cf' => 'Central African Republic',
        'td' => 'Chad',
        'cl' => 'Chile',
        'cn' => 'China',
        'co' => 'Colombia',
        'km' => 'Comoros',
        'cg' => 'Congo',
        'cr' => 'Costa Rica',
        'hr' => 'Croatia',
        'cu' => 'Cuba',
        'cy' => 'Cyprus',
        'cz' => 'Czech Republic',
        'dk' => 'Denmark',
        'dj' => 'Djibouti',
        'dm' => 'Dominica',
        'do' => 'Dominican Republic',
        'tl' => 'East Timor',
        'ec' => 'Ecuador',
        'eg' => 'Egypt',
        'sv' => 'El Salvador',
        'gq' => 'Equatorial Guinea',
        'er' => 'Eritrea',
        'ee' => 'Estonia',
        'et' => 'Ethiopia',
        'fj' => 'Fiji',
        'fi' => 'Finland',
        'fr' => 'France',
        'ga' => 'Gabon',
        'gm' => 'Gambia',
        'ge' => 'Georgia',
        'de' => 'Germany',
        'gh' => 'Ghana',
        'gr' => 'Greece',
        'gd' => 'Grenada',
        'gt' => 'Guatemala',
        'gn' => 'Guinea',
        'gw' => 'Guinea-Bissau',
        'gy' => 'Guyana',
        'ht' => 'Haiti',
        'hn' => 'Honduras',
        'hk' => 'Hong Kong',
        'hu' => 'Hungary',
        'is' => 'Iceland',
        'in' => 'India',
        'id' => 'Indonesia',
        'ir' => 'Iran',
        'iq' => 'Iraq',
        'ie' => 'Ireland',
        'il' => 'Israel',
        'it' => 'Italy',
        'ci' => 'Ivory Coast',
        'jm' => 'Jamaica',
        'jp' => 'Japan',
        'jo' => 'Jordan',
        'kz' => 'Kazakhstan',
        'ke' => 'Kenya',
        'ki' => 'Kiribati',
        'kw' => 'Kuwait',
        'kg' => 'Kyrgyzstan',
        'la' => 'Laos',
        'lv' => 'Latvia',
        'lb' => 'Lebanon',
        'ls' => 'Lesotho',
        'lr' => 'Liberia',
        'ly' => 'Libya',
        'li' => 'Liechtenstein',
        'lt' => 'Lithuania',
        'lu' => 'Luxembourg',
        'mo' => 'Macau',
        'mg' => 'Madagascar',
        'mw' => 'Malawi',
        'my' => 'Malaysia',
        'mv' => 'Maldives',
        'ml' => 'Mali',
        'mt' => 'Malta',
        'mh' => 'Marshall Islands',
        'mr' => 'Mauritania',
        'mu' => 'Mauritius',
        'mx' => 'Mexico',
        'fm' => 'Micronesia',
        'md' => 'Moldova',
        'mc' => 'Monaco',
        'mn' => 'Mongolia',
        'me' => 'Montenegro',
        'ma' => 'Morocco',
        'mz' => 'Mozambique',
        'mm' => 'Myanmar',
        'na' => 'Namibia',
        'nr' => 'Nauru',
        'np' => 'Nepal',
        'nl' => 'Netherlands',
        'nz' => 'New Zealand',
        'ni' => 'Nicaragua',
        'ne' => 'Niger',
        'ng' => 'Nigeria',
        'kp' => 'North Korea',
        'mk' => 'North Macedonia',
        'no' => 'Norway',
        'om' => 'Oman',
        'pk' => 'Pakistan',
        'pw' => 'Palau',
        'ps' => 'Palestine',
        'pa' => 'Panama',
        'pg' => 'Papua New Guinea',
        'py' => 'Paraguay',
        'pe' => 'Peru',
        'ph' => 'Philippines',
        'pl' => 'Poland',
        'pt' => 'Portugal',
        'qa' => 'Qatar',
        'ro' => 'Romania',
        'ru' => 'Russia',
        'rw' => 'Rwanda',
        'kn' => 'Saint Kitts and Nevis',
        'lc' => 'Saint Lucia',
        'vc' => 'Saint Vincent and the Grenadines',
        'ws' => 'Samoa',
        'sm' => 'San Marino',
        'st' => 'Sao Tome and Principe',
        'sa' => 'Saudi Arabia',
        'sn' => 'Senegal',
        'rs' => 'Serbia',
        'sc' => 'Seychelles',
        'sl' => 'Sierra Leone',
        'sg' => 'Singapore',
        'sk' => 'Slovakia',
        'si' => 'Slovenia',
        'sb' => 'Solomon Islands',
        'so' => 'Somalia',
        'za' => 'South Africa',
        'kr' => 'South Korea',
        'ss' => 'South Sudan',
        'es' => 'Spain',
        'lk' => 'Sri Lanka',
        'sd' => 'Sudan',
        'sr' => 'Suriname',
        'se' => 'Sweden',
        'ch' => 'Switzerland',
        'sy' => 'Syria',
        'tw' => 'Taiwan',
        'tj' => 'Tajikistan',
        'tz' => 'Tanzania',
        'th' => 'Thailand',
        'tg' => 'Togo',
        'to' => 'Tonga',
        'tt' => 'Trinidad and Tobago',
        'tn' => 'Tunisia',
        'tr' => 'Turkey',
        'tm' => 'Turkmenistan',
        'tv' => 'Tuvalu',
        'ug' => 'Uganda',
        'ua' => 'Ukraine',
        'ae' => 'United Arab Emirates',
        'gb' => 'Great Britain',
        'us' => 'United States',
        'uy' => 'Uruguay',
        'uz' => 'Uzbekistan',
        'vu' => 'Vanuatu',
        'va' => 'Vatican City',
        've' => 'Venezuela',
        'vn' => 'Vietnam',
        'ye' => 'Yemen',
        'zm' => 'Zambia',
        'zw' => 'Zimbabwe',
    ];

    // Normalize country code
    $country_code_lower = strtolower($country_code);

    // For UK/GB, use 'uk' as the unified slug
    if (in_array($country_code_lower, ['gb', 'uk'])) {
        $category_slug = 'uk';  // Unified slug for both
        $category_name = 'United Kingdom';
    } else {
        $category_slug = $country_code_lower;
        $category_name = isset($country_names[$country_code_lower])
            ? $country_names[$country_code_lower]
            : strtoupper($country_code);
    }

    // Check if category exists
    $existing_category = get_term_by('slug', $category_slug, 'category');

    if ($existing_category) {
        echo '<div class="import-info">';
        echo '<p>🏷️ Using existing category: ' . esc_html($category_name) . ' (' . esc_html($category_slug) . ')</p>';
        echo '</div>';
        return $existing_category->term_id;
    }

    // Create new category
    $new_category = wp_insert_term(
        $category_name,
        'category',
        [
            'slug' => $category_slug,
            'description' => 'Blogs from ' . $category_name
        ]
    );

    if (is_wp_error($new_category)) {
        echo '<div class="import-error">';
        echo '<p>❌ Failed to create category for ' . esc_html($country_code) . ': ' . esc_html($new_category->get_error_message()) . '</p>';
        echo '</div>';
        return 0;
    }

    echo '<div class="import-success">';
    echo '<p>✅ Created new category: ' . esc_html($category_name) . ' (' . esc_html($category_slug) . ')</p>';
    echo '</div>';

    return $new_category['term_id'];
}

// Function to import a single blog (main or sub)
function actionjuris_import_single_blog($blog, $all_slugs)
{
    // Increase time limit for each blog
    @set_time_limit(60); // 60 seconds per blog
    @ini_set('memory_limit', '256M');

    $slug = $blog['slug'];
    $title = $blog['page_title'];
    $created_at = $blog['created_at'];

    // Skip if post with slug exists
    if (get_page_by_path($slug, OBJECT, 'post')) {
        echo '<div class="import-skipped">';
        echo '<p>⏭️ Skipped (already exists): <strong>' . esc_html($title) . '</strong></p>';
        echo '</div>';
        return 'skipped';
    }

    // Fetch full blog content
    $blog_json_url = 'https://api.actionjuris.ca/api/blog/' . $slug;
    $full_response = wp_remote_get($blog_json_url, array('timeout' => 30));

    if (is_wp_error($full_response)) {
        echo '<div class="import-error">';
        echo '<p>❌ Failed to fetch blog content: <strong>' . esc_html($title) . '</strong> - ' . esc_html($full_response->get_error_message()) . '</p>';
        echo '</div>';
        return 'error';
    }

    $full_body = wp_remote_retrieve_body($full_response);
    $full_data = json_decode($full_body, true);

    if (empty($full_data['data']['layout'])) {
        echo '<div class="import-error">';
        echo '<p>❌ No content layout found for: <strong>' . esc_html($title) . '</strong></p>';
        echo '</div>';
        return 'error';
    }

    // Get country code for category assignment
    $country_code = !empty($full_data['data']['blog']['country_code'])
        ? $full_data['data']['blog']['country_code']
        : '';

    // Create country category if country code exists
    $country_category_id = 0;
    if (!empty($country_code)) {
        $country_category_id = actionjuris_get_country_category($country_code);
    }

    // Start building content HTML
    $content_html = '';

    // Process content blocks (NOTE: Featured image is now handled separately via set_post_thumbnail)
    foreach ($full_data['data']['layout'] as $block) {
        switch ($block['type']) {
            case 'content':
                $content_html .= actionjuris_process_content_block($block['content'], $all_slugs);
                break;

            case 'images':
                $content_html .= actionjuris_process_images_block($block, $title);
                break;

            case 'content-image':
            case 'image-content':
                if (!empty($block['content'])) {
                    $content_html .= actionjuris_process_content_block($block['content'], $all_slugs);
                }
                if (!empty($block['images'])) {
                    foreach ($block['images'] as $img) {
                        $img_url = 'https://api.actionjuris.ca/storage/' . $img['image'];
                        $content_html .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '" style="max-width: 100%; height: auto;" />';
                    }
                }
                break;

            default:
                // Ignore unknown types
                break;
        }
    }

    // Create post first
    $post_id = wp_insert_post([
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content_html,
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_date' => $created_at,
        'post_date_gmt' => get_gmt_from_date($created_at),
    ]);

    if (is_wp_error($post_id)) {
        echo '<div class="import-error">';
        echo '<p>❌ Failed to create post: <strong>' . esc_html($title) . '</strong> - ' . esc_html($post_id->get_error_message()) . '</p>';
        echo '</div>';
        return 'error';
    }

    // Assign country category if available
    if ($country_category_id > 0) {
        wp_set_post_categories($post_id, [$country_category_id], false);
        echo '<div class="import-success">';
        echo '<p>🏷️ Assigned to country category ID: ' . $country_category_id . '</p>';
        echo '</div>';
    }

    // Download and set featured image if available
    // Check for featured image in blog['images'][0] first
    $featured_image_url = null;

    if (!empty($full_data['data']['blog']['images'][0]['image'])) {
        $featured_image_url = 'https://api.actionjuris.ca/storage/' . $full_data['data']['blog']['images'][0]['image'];
    } elseif (!empty($blog['first_image']['image'])) {
        // Fallback to old location
        $featured_image_url = 'https://api.actionjuris.ca/storage/' . $blog['first_image']['image'];
    }

    if ($featured_image_url) {
        echo '<div class="import-info">';
        echo '<p>📸 Downloading featured image: ' . esc_url($featured_image_url) . '</p>';
        echo '</div>';

        $attachment_id = actionjuris_download_featured_image($featured_image_url, $post_id, $title);

        if ($attachment_id) {
            echo '<div class="import-success">';
            echo '<p>✅ Featured image set successfully (Attachment ID: ' . $attachment_id . ')</p>';
            echo '</div>';

            // Store original URL in post meta for reference
            update_post_meta($post_id, '_actionjuris_featured_image_url', $featured_image_url);
        } else {
            echo '<div class="import-error">';
            echo '<p>❌ Failed to download featured image</p>';
            echo '</div>';
        }
    }

    echo '<div class="import-success">';
    echo '<p>✅ Imported: <strong>' . esc_html($title) . '</strong> (Post ID: ' . $post_id . ')</p>';
    echo '</div>';

    return 'imported';
}

// Process images block with layout support
function actionjuris_process_images_block($block, $title)
{
    $images_html = '';

    if (!empty($block['images'])) {
        // Check if it's a two-column layout
        if (isset($block['layout']) && $block['layout'] === 'two-column' && count($block['images']) === 2) {
            $images_html .= '<div class="two-column-images" style="display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0;">';
            foreach ($block['images'] as $img) {
                $img_url = 'https://api.actionjuris.ca/storage/' . $img['image'];
                $images_html .= '<div style="flex: 1; min-width: 250px;">';
                $images_html .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '" style="max-width: 100%; height: auto;" />';
                $images_html .= '</div>';
            }
            $images_html .= '</div>';
        } else {
            // Default vertical layout
            $images_html .= '<div class="image-gallery">';
            foreach ($block['images'] as $img) {
                $img_url = 'https://api.actionjuris.ca/storage/' . $img['image'];
                $images_html .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '" style="max-width: 100%; height: auto;" />';
            }
            $images_html .= '</div>';
        }
    }

    return $images_html;
}

// Process content blocks with proper headings and internal links
function actionjuris_process_content_block($content, $all_slugs)
{
    $processed_content = '';

    // Check if content contains |*|*| separator
    if (strpos($content, '|*|*|') !== false) {
        $parts = explode('|*|*|', $content, 2); // Split into max 2 parts
        $heading = trim($parts[0]);
        $paragraph_content = trim($parts[1]);

        // Add H2 for content before |*|*|
        if (!empty($heading)) {
            $processed_content .= '<h2>' . wp_kses_post($heading) . '</h2>';
        }

        // Add P tag for content after |*|*|
        if (!empty($paragraph_content)) {
            $paragraph_content = actionjuris_process_internal_links($paragraph_content, $all_slugs);
            $processed_content .= '<p>' . wp_kses_post($paragraph_content) . '</p>';
        }
    } else {
        // No |*|*| found - treat entire content as P tag
        $content = actionjuris_process_internal_links($content, $all_slugs);
        $processed_content .= '<p>' . wp_kses_post(trim($content)) . '</p>';
    }

    return $processed_content;
}

// Process internal links in content
function actionjuris_process_internal_links($content, $all_slugs)
{
    // Pattern to match: [link text || slug || id]
    $pattern = '/\[([^\]]+?)\s*\|\|\s*([^\|\]]+?)\s*\|\|\s*([^\|\]]+?)\]/';

    return preg_replace_callback($pattern, function ($matches) use ($all_slugs) {
        $link_text = trim($matches[1]);
        $target_slug = trim($matches[2]);
        $blog_id = trim($matches[3]);

        // Check if the target slug exists in our imported blogs
        $target_post = get_page_by_path($target_slug, OBJECT, 'post');

        if ($target_post) {
            // Link to existing WordPress post
            $url = get_permalink($target_post->ID);
            return '<a href="' . esc_url($url) . '" class="internal-blog-link">' . esc_html($link_text) . '</a>';
        } elseif (isset($all_slugs[$target_slug])) {
            // Link to a blog that will be imported (store as meta for later processing)
            return '<span class="pending-internal-link" data-slug="' . esc_attr($target_slug) . '" data-text="' . esc_attr($link_text) . '">' . esc_html($link_text) . '</span>';
        } else {
            // Fallback - keep original text without link
            return $link_text;
        }
    }, $content);
}