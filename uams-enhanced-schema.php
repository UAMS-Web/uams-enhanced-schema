<?php

declare(strict_types=1);
/**
 * Plugin Name: UAMS Enhanced Schema Markup
 * Plugin URI:  https://github.com/UAMS-Web/uams-enhanced-schema
 * Description: Replaces existing provider/location schema with corrected, comprehensive JSON-LD.
 *              Designed as throwaway — Statamic migration will use Blade partials instead.
 * Version:     1.0.0
 * Requires PHP: 8.2
 * Author:      UAMS Web Services
 * Author URI:  https://communications.uams.edu/web/
 *
 * INSTALLATION:
 *   Drop this file into wp-content/mu-plugins/
 *   It auto-loads — no activation needed.
 *
 * MODES:
 *   MODE_REPLACE (default) — removes the inline schema from templates and outputs corrected version in wp_footer.
 *   MODE_SAFE    — only adds schemas that don't already exist (BreadcrumbList, FAQPage, Organization).
 *
 *   To switch modes, change the constant below or define it in wp-config.php:
 *     define('UAMS_SCHEMA_MODE', 'safe');
 */
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('UAMS_SCHEMA_MODE')) {
    define('UAMS_SCHEMA_MODE', 'replace'); // 'replace' or 'safe'
}

// ─── Strip existing inline schema from templates when in replace mode ───
/**
 * Registers the output buffer that strips the original inline schema.
 *
 * The mode arrives as a parameter, not as the constant, so a static analyzer
 * cannot fold the comparison: the constant has a default defined above, and
 * folding it deleted this check once (safe mode then stripped as well).
 *
 * @param  string  $mode  The value of UAMS_SCHEMA_MODE: 'replace' or 'safe'.
 */
function uams_enhanced_schema_register_stripping(string $mode): void
{
    if ($mode !== 'replace') {
        return;
    }

    add_action('wp_loaded', function (): void {
        ob_start(function ($html) {
            // Only strip schema blocks that use the old http://www.schema.org context
            // from physician and location single pages
            if (is_singular('provider') || is_singular('location')) {
                return preg_replace(
                    '/<script\s+type=[\'"]application\/ld\+json[\'"]>\s*\{[^}]*"@context":\s*"http:\/\/www\.schema\.org".*?\}\s*<\/script>/s',
                    '<!-- UAMS Enhanced Schema: original schema replaced -->',
                    $html
                );
            }

            return $html;
        });
    });
}

uams_enhanced_schema_register_stripping(UAMS_SCHEMA_MODE);

// ─── Output enhanced schema in wp_footer ───
add_action('wp_footer', 'uams_enhanced_schema_output', 99);

function uams_enhanced_schema_output(): void
{
    if (is_singular('provider')) {
        uams_physician_schema();
    } elseif (is_singular('location')) {
        uams_location_schema();
    } elseif (is_singular('condition')) {
        uams_condition_schema();
    } elseif (is_singular('treatment')) {
        uams_treatment_schema();
    }

    // These always output (both modes) — they don't conflict with existing schema
    if (is_singular(['provider', 'location', 'condition', 'treatment', 'expertise'])) {
        uams_breadcrumb_schema();
    }

    // Organization schema on homepage only
    if (is_front_page()) {
        uams_organization_schema();
    }
}

// ═══════════════════════════════════════════════════════════════
//  PHYSICIAN SCHEMA
// ═══════════════════════════════════════════════════════════════

function uams_physician_schema(): void
{
    $post_id = get_the_ID();

    // ── Name ──
    $first = get_field('physician_first_name', $post_id) ?: '';
    $middle = get_field('physician_middle_name', $post_id) ?: '';
    $last = get_field('physician_last_name', $post_id) ?: '';
    $prefix = get_field('physician_prefix', $post_id) ?: '';
    $suffix = get_field('physician_suffix', $post_id) ?: '';

    // Degrees — taxonomy term IDs in 'degree' taxonomy
    $degree_ids = get_field('physician_degree', $post_id);
    $degrees = [];
    if ($degree_ids && is_array($degree_ids)) {
        foreach ($degree_ids as $term_id) {
            $term = get_term($term_id, 'degree');
            if ($term && ! is_wp_error($term)) {
                $degrees[] = $term->name;
            }
        }
    }

    $full_name = trim("$first $middle $last");
    $display_name = $full_name;
    if ($degrees !== []) {
        $display_name .= ', '.implode(', ', $degrees);
    }

    // ── Clinical title / specialty — taxonomy term ID ──
    $title_id = get_field('physician_title', $post_id);
    $clinical_title = '';
    if ($title_id) {
        $term = get_term($title_id, 'clinical_title');
        if ($term && ! is_wp_error($term)) {
            $clinical_title = $term->name;
        }
    }

    // ── Description ──
    $short_clinical_bio = get_field('physician_short_clinical_bio', $post_id);
    $clinical_bio = get_field('physician_clinical_bio', $post_id);
    $excerpt = '';
    if ($short_clinical_bio) {
        $excerpt = wp_strip_all_tags($short_clinical_bio);
    } elseif ($clinical_bio) {
        $excerpt = mb_strimwidth(wp_strip_all_tags($clinical_bio), 0, 250, '...');
    }

    // ── Image ──
    $image_url = '';
    if (has_post_thumbnail($post_id)) {
        $image_url = get_the_post_thumbnail_url($post_id, 'large');
    } else {
        $image_url = plugins_url('UAMSWP-Find-a-Doc/assets/svg/no-image_3-4.jpg');
    }

    // ── Languages — taxonomy term IDs in 'language' taxonomy ──
    $lang_ids = get_field('physician_languages', $post_id);
    $languages = [];
    if ($lang_ids && is_array($lang_ids)) {
        foreach ($lang_ids as $term_id) {
            $term = get_term($term_id, 'language');
            if ($term && ! is_wp_error($term)) {
                $languages[] = $term->name;
            }
        }
    }

    // ── NPI ──
    $npi = get_field('physician_npi', $post_id) ?: '';

    // ── Accepting new patients ──
    $accepting = get_field('physician_accepting_patients', $post_id);

    // ── Conditions (CPT post IDs) ──
    $condition_ids = get_field('physician_conditions_cpt', $post_id);
    $conditions = [];
    if ($condition_ids && is_array($condition_ids)) {
        foreach ($condition_ids as $cid) {
            if (get_post_status($cid) === 'publish') {
                $conditions[] = [
                    '@type' => 'MedicalCondition',
                    'name' => get_the_title($cid),
                    'url' => get_permalink($cid),
                ];
            }
        }
    }

    // ── Treatments (CPT post IDs) ──
    $treatment_ids = get_field('physician_treatments_cpt', $post_id);
    $treatments = [];
    if ($treatment_ids && is_array($treatment_ids)) {
        foreach ($treatment_ids as $tid) {
            if (get_post_status($tid) === 'publish') {
                $treatments[] = [
                    '@type' => 'MedicalTherapy',
                    'name' => get_the_title($tid),
                    'url' => get_permalink($tid),
                ];
            }
        }
    }

    // ── Medical specialties — combine conditions + treatments for medicalSpecialty ──
    $specialties = [];
    if ($condition_ids && is_array($condition_ids)) {
        foreach ($condition_ids as $cid) {
            if (get_post_status($cid) === 'publish') {
                $specialties[] = get_the_title($cid);
            }
        }
    }

    if ($treatment_ids && is_array($treatment_ids)) {
        foreach ($treatment_ids as $tid) {
            if (get_post_status($tid) === 'publish') {
                $specialties[] = get_the_title($tid);
            }
        }
    }

    // ── Education — ACF repeater ──
    $education = [];
    if (have_rows('physician_education', $post_id)) {
        while (have_rows('physician_education', $post_id)) {
            the_row();

            // school — taxonomy 'school' term ID
            $school_id = get_sub_field('school');
            $school_name = '';
            if ($school_id) {
                $term = get_term($school_id, 'school');
                if ($term && ! is_wp_error($term)) {
                    $school_name = $term->name;
                }
            }

            // education_type — taxonomy 'educationtype' term ID
            $type_id = get_sub_field('education_type');
            $type_name = '';
            if ($type_id) {
                $term = get_term($type_id, 'educationtype');
                if ($term && ! is_wp_error($term)) {
                    $type_name = $term->name;
                }
            }

            $desc = get_sub_field('description') ?: '';

            if ($school_name) {
                $edu_entry = [
                    '@type' => 'EducationalOccupationalCredential',
                    'educationalLevel' => $type_name ?: 'Medical Education',
                    'recognizedBy' => [
                        '@type' => 'EducationalOrganization',
                        'name' => $school_name,
                    ],
                ];
                if ($desc) {
                    $edu_entry['description'] = $desc;
                }

                $education[] = $edu_entry;
            }
        }
    }

    // ── Locations — post IDs ──
    $location_ids = get_field('physician_locations', $post_id);
    $addresses = [];
    $practice_locations = [];
    if ($location_ids && is_array($location_ids)) {
        foreach ($location_ids as $loc_id) {
            if (get_post_status($loc_id) !== 'publish') {
                continue;
            }

            // Location fields use get_field with the location post ID
            // Check for parent location (child locations inherit address from parent)
            $has_parent = get_field('location_parent', $loc_id);
            $parent_id = get_field('location_parent_id', $loc_id);
            $addr_post_id = $loc_id;
            if ($has_parent && $parent_id) {
                $addr_post_id = $parent_id;
            }

            $addr1 = get_field('location_address_1', $addr_post_id) ?: '';
            $city = get_field('location_city', $addr_post_id) ?: '';
            $state = get_field('location_state', $addr_post_id) ?: '';
            $zip = get_field('location_zip', $addr_post_id) ?: '';

            // Building and floor (taxonomy + select field)
            $building_id = get_field('location_building', $addr_post_id);
            $building_name = '';
            if ($building_id) {
                $bterm = get_term($building_id, 'building');
                if ($bterm && ! is_wp_error($bterm) && $bterm->slug !== '_none') {
                    $building_name = $bterm->name;
                }
            }

            $suite = get_field('location_suite', $addr_post_id) ?: '';
            $addr2_parts = array_filter([$building_name, $suite]);
            $addr2 = implode(' ', $addr2_parts);
            if (! $addr2) {
                $addr2 = get_field('location_address_2', $addr_post_id) ?: '';
            }

            $street = trim("$addr1 $addr2");

            $address = [
                '@type' => 'PostalAddress',
                'streetAddress' => $street,
                'addressLocality' => $city,
                'addressRegion' => $state,
                'postalCode' => $zip,
            ];

            $addresses[] = $address;

            // Build MedicalClinic for each location
            $loc_entry = [
                '@type' => 'MedicalClinic',
                'name' => get_the_title($loc_id),
                'url' => get_permalink($loc_id),
                'address' => $address,
            ];

            // Phone — included via location-card template which sets $location_phone
            // We access it directly from location fields
            $phone = get_field('location_phone', $loc_id);
            if ($phone) {
                $loc_entry['telephone'] = uams_format_phone_schema($phone);
            }

            $practice_locations[] = $loc_entry;
        }
    }

    // ── Ratings — Press Ganey API ──
    $aggregate_rating = null;
    $pg_rating_data = get_post_meta($post_id, '_syndicated_api_data', true);
    if ($npi && ! empty($pg_rating_data)) {
        if (! empty($pg_rating_data) && isset($pg_rating_data->status->code) && $pg_rating_data->status->code == '200') {
            $total_ratings = $pg_rating_data->data->entities[0]->totalRatingCount ?? 0;
            if ($total_ratings >= 30) {
                $rating_value = $pg_rating_data->data->entities[0]->overallRating->value ?? null;
                $comment_count = $pg_rating_data->data->entities[0]->totalCommentCount ?? 0;
                if ($rating_value) {
                    $aggregate_rating = [
                        '@type' => 'AggregateRating',
                        'ratingValue' => (string) $rating_value,
                        'bestRating' => '5',
                        'ratingCount' => (string) $total_ratings,
                    ];
                    if ($comment_count && $comment_count > 0) {
                        $aggregate_rating['reviewCount'] = (string) $comment_count;
                    }
                }
            }
        }
    }

    // ── Boards / certifications — taxonomy term IDs ──
    $board_ids = get_field('physician_boards', $post_id);
    $certifications = [];
    if ($board_ids && is_array($board_ids)) {
        foreach ($board_ids as $term_id) {
            $term = get_term($term_id, 'boards');
            if ($term && ! is_wp_error($term)) {
                $certifications[] = $term->name;
            }
        }
    }

    // ── Build schema ──
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Physician',
        'name' => $display_name,
        'givenName' => $first,
        'familyName' => $last,
        'url' => get_permalink($post_id),
        'image' => $image_url,
        'logo' => get_stylesheet_directory_uri().'/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
    ];

    if ($middle) {
        $schema['additionalName'] = $middle;
    }

    if ($prefix) {
        $schema['honorificPrefix'] = $prefix;
    }

    if ($suffix) {
        $schema['honorificSuffix'] = $suffix;
    }

    if ($degrees !== []) {
        $schema['honorificSuffix'] = implode(', ', $degrees);
    }

    if ($clinical_title) {
        $schema['jobTitle'] = $clinical_title;
    }

    if ($excerpt) {
        $schema['description'] = $excerpt;
    }

    if ($npi) {
        $schema['identifier'] = [
            '@type' => 'PropertyValue',
            'propertyID' => 'NPI',
            'value' => $npi,
        ];
    }

    if ($specialties !== []) {
        $schema['medicalSpecialty'] = array_values(array_unique($specialties));
    }

    if ($conditions !== []) {
        $schema['expertiseCondition'] = $conditions;
    }

    if ($languages !== []) {
        $schema['knowsLanguage'] = $languages;
    }

    if ($accepting !== null && $accepting !== '') {
        $schema['isAcceptingNewPatients'] = (bool) $accepting;
    }

    if ($education !== []) {
        $schema['hasCredential'] = $education;
    }

    if ($certifications !== []) {
        $schema['hasCredential'] = array_merge(
            $schema['hasCredential'] ?? [],
            array_map(fn ($cert): array => [
                '@type' => 'EducationalOccupationalCredential',
                'credentialCategory' => 'Board Certification',
                'name' => $cert,
            ], $certifications)
        );
    }

    if ($addresses !== []) {
        $schema['address'] = count($addresses) === 1 ? $addresses[0] : $addresses;
    }

    if ($practice_locations !== []) {
        $schema['workLocation'] = $practice_locations;
    }

    if ($aggregate_rating) {
        $schema['aggregateRating'] = $aggregate_rating;
    }

    $schema['memberOf'] = [
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'url' => 'https://uamshealth.com',
    ];

    uams_output_jsonld($schema);
}

// ═══════════════════════════════════════════════════════════════
//  LOCATION / CLINIC SCHEMA
// ═══════════════════════════════════════════════════════════════

function uams_location_schema(): void
{
    $post_id = get_the_ID();

    // Parent location logic (child locations may inherit address)
    $has_parent = get_field('location_parent', $post_id);
    $parent_id = get_field('location_parent_id', $post_id);
    $addr_post_id = $post_id;
    if ($has_parent && $parent_id) {
        $parent_post = get_post($parent_id);
        if ($parent_post) {
            $addr_post_id = $parent_id;
        }
    }

    $page_title = get_the_title();

    // ── Address ──
    $addr1 = get_field('location_address_1', $addr_post_id) ?: '';
    $city = get_field('location_city', $addr_post_id) ?: '';
    $state = get_field('location_state', $addr_post_id) ?: '';
    $zip = get_field('location_zip', $addr_post_id) ?: '';

    $building_id = get_field('location_building', $addr_post_id);
    $building_name = '';
    if ($building_id) {
        $bterm = get_term($building_id, 'building');
        if ($bterm && ! is_wp_error($bterm) && $bterm->slug !== '_none') {
            $building_name = $bterm->name;
        }
    }

    $suite = get_field('location_suite', $addr_post_id) ?: '';
    $addr2_parts = array_filter([$building_name, $suite]);
    $addr2 = implode(' ', $addr2_parts);
    if (! $addr2) {
        $addr2 = get_field('location_address_2', $addr_post_id) ?: '';
    }

    $street = trim("$addr1 $addr2");

    // ── Map / Geo ──
    $map = get_field('location_map', $addr_post_id);

    // ── Image ──
    $image_url = '';
    $override_parent = get_field('location_image_override_parent', $post_id);
    $override_featured = get_field('location_image_override_parent_featured', $post_id);

    if ($override_parent && $has_parent && $override_featured) {
        $thumb_id = get_post_thumbnail_id($post_id);
    } else {
        $thumb_id = get_post_thumbnail_id($addr_post_id);
    }

    if ($thumb_id) {
        $image_url = wp_get_attachment_image_url($thumb_id, 'large');
    }

    // ── Description ──
    $short_desc = get_field('location_short_desc', $post_id);
    $about = get_field('location_about', $post_id);
    $description = '';
    if ($short_desc) {
        $description = wp_strip_all_tags($short_desc);
    } elseif ($about) {
        $description = mb_strimwidth(wp_strip_all_tags($about), 0, 250, '...');
    }

    // ── Hours ──
    $hours_group = get_field('location_hours_group', $post_id);
    $opening_hours = [];
    $opening_hours_spec = [];

    if ($hours_group) {
        $hours_vary = $hours_group['location_hours_variable'] ?? false;
        $is_247 = $hours_group['location_24_7'] ?? false;
        $has_modified = $hours_group['location_modified_hours'] ?? false;

        if (! $hours_vary) {
            if ($is_247) {
                $opening_hours = ['Mo-Su 00:00-23:59'];
            } else {
                $hours = $hours_group['location_hours'] ?? [];
                if (is_array($hours)) {
                    foreach ($hours as $hour) {
                        if (! empty($hour['closed'])) {
                            continue;
                        }

                        $day_abbr = uams_day_abbreviation($hour['day'] ?? '');
                        if ($day_abbr && ! empty($hour['open']) && ! empty($hour['close'])) {
                            $open = date('H:i', strtotime($hour['open']));
                            $close = date('H:i', strtotime($hour['close']));
                            $opening_hours[] = "$day_abbr $open-$close";
                        }
                    }
                }
            }

            // Modified hours → openingHoursSpecification
            if ($has_modified) {
                $mod_start = $hours_group['location_modified_hours_start_date'] ?? '';
                $mod_end_known = $hours_group['location_modified_hours_end'] ?? false;
                $mod_end_date = $hours_group['location_modified_hours_end_date'] ?? '';
                $mod_groups = $hours_group['location_modified_hours_group'] ?? [];

                if (is_array($mod_groups)) {
                    foreach ($mod_groups as $mod_group) {
                        $mod_247 = $mod_group['location_modified_hours_24_7'] ?? false;
                        $mod_times = $mod_group['location_modified_hours_times'] ?? [];

                        if ($mod_247) {
                            $spec = [
                                '@type' => 'OpeningHoursSpecification',
                                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                                'opens' => '00:00',
                                'closes' => '23:59',
                            ];
                            if ($mod_start) {
                                $spec['validFrom'] = date('Y-m-d', strtotime($mod_start));
                            }

                            if ($mod_end_known && $mod_end_date) {
                                $spec['validThrough'] = date('Y-m-d', strtotime($mod_end_date));
                            }

                            $opening_hours_spec[] = $spec;
                        } elseif (is_array($mod_times)) {
                            foreach ($mod_times as $mt) {
                                $closed = $mt['location_modified_hours_closed'] ?? false;
                                $day_val = $mt['location_modified_hours_day'] ?? '';
                                $spec = [
                                    '@type' => 'OpeningHoursSpecification',
                                ];
                                if ($day_val === 'Mon - Fri') {
                                    $spec['dayOfWeek'] = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                } else {
                                    $spec['dayOfWeek'] = $day_val;
                                }

                                if ($closed) {
                                    $spec['opens'] = '00:00';
                                    $spec['closes'] = '00:00';
                                } else {
                                    $spec['opens'] = date('H:i', strtotime($mt['location_modified_hours_open'] ?? ''));
                                    $spec['closes'] = date('H:i', strtotime($mt['location_modified_hours_close'] ?? ''));
                                }

                                if ($mod_start) {
                                    $spec['validFrom'] = date('Y-m-d', strtotime($mod_start));
                                }

                                if ($mod_end_known && $mod_end_date) {
                                    $spec['validThrough'] = date('Y-m-d', strtotime($mod_end_date));
                                }

                                $opening_hours_spec[] = $spec;
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Phone ──
    $phone = get_field('location_phone', $post_id);

    // ── Conditions & Treatments ──
    $condition_ids = get_field('location_conditions_cpt', $post_id);
    $specialties = [];
    if ($condition_ids && is_array($condition_ids)) {
        $cond_query = new WP_Query([
            'post_type' => 'condition',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $condition_ids,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($cond_query->posts as $c) {
            $specialties[] = $c->post_title;
        }

        wp_reset_postdata();
    }

    $treatment_ids = get_field('location_treatments_cpt', $post_id);
    if ($treatment_ids && is_array($treatment_ids)) {
        $treat_query = new WP_Query([
            'post_type' => 'treatment',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__in' => $treatment_ids,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($treat_query->posts as $t) {
            $specialties[] = $t->post_title;
        }

        wp_reset_postdata();
    }

    // ── Providers at this location ──
    $physicians = get_field('physician_locations', $post_id);
    $available_services = [];
    if ($physicians && is_array($physicians)) {
        foreach (array_slice($physicians, 0, 50) as $doc_id) {
            if (get_post_status($doc_id) !== 'publish') {
                continue;
            }

            $doc_degrees = [];
            $doc_degree_ids = get_field('physician_degree', $doc_id);
            if ($doc_degree_ids && is_array($doc_degree_ids)) {
                foreach ($doc_degree_ids as $did) {
                    $dt = get_term($did, 'degree');
                    if ($dt && ! is_wp_error($dt)) {
                        $doc_degrees[] = $dt->name;
                    }
                }
            }

            $doc_name = get_the_title($doc_id);
            if ($doc_degrees !== []) {
                $doc_name .= ', '.implode(', ', $doc_degrees);
            }

            $available_services[] = [
                '@type' => 'Physician',
                'name' => $doc_name,
                'url' => get_permalink($doc_id),
            ];
        }
    }

    // ── Closing status ──
    $closing = get_field('location_closing', $post_id);
    $temporarily_closed = false;
    if ($closing) {
        $closing_length = get_field('location_closing_length', $post_id);
        $closing_date = get_field('location_closing_date', $post_id);
        if ($closing_date && new DateTime >= new DateTime($closing_date)) {
            $temporarily_closed = ($closing_length === 'temporary');
        }
    }

    // ── Build schema ──
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'MedicalClinic',
        'name' => $page_title,
        'url' => get_permalink($post_id),
        'logo' => get_stylesheet_directory_uri().'/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
    ];

    if ($image_url) {
        $schema['image'] = $image_url;
    }

    if ($description) {
        $schema['description'] = $description;
    }

    $schema['address'] = [
        '@type' => 'PostalAddress',
        'streetAddress' => $street,
        'addressLocality' => $city,
        'addressRegion' => $state,
        'postalCode' => $zip,
        'addressCountry' => 'US',
    ];

    if ($map && isset($map['lat']) && isset($map['lng'])) {
        $schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => $map['lat'],
            'longitude' => $map['lng'],
        ];
    }

    if ($phone) {
        $schema['telephone'] = uams_format_phone_schema($phone);
    }

    if ($opening_hours !== []) {
        $schema['openingHours'] = $opening_hours;
    }

    if ($opening_hours_spec !== []) {
        $schema['openingHoursSpecification'] = $opening_hours_spec;
    }

    if ($specialties !== []) {
        $schema['medicalSpecialty'] = array_values(array_unique($specialties));
    }

    if ($available_services !== []) {
        $schema['availableService'] = $available_services;
    }

    if ($temporarily_closed) {
        $schema['specialOpeningHoursSpecification'] = [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '00:00',
            'closes' => '00:00',
            'description' => 'Temporarily closed',
        ];
    }

    $schema['isPartOf'] = [
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'url' => 'https://uamshealth.com',
    ];

    uams_output_jsonld($schema);
}

// ═══════════════════════════════════════════════════════════════
//  CONDITION SCHEMA (MedicalCondition)
// ═══════════════════════════════════════════════════════════════

function uams_condition_schema(): void
{
    $post_id = get_the_ID();
    $title = get_the_title();

    // Description from excerpt or content
    $excerpt = get_the_excerpt();
    $content = get_the_content();
    $description = '';
    if ($excerpt) {
        $description = wp_strip_all_tags($excerpt);
    } elseif ($content) {
        $description = mb_strimwidth(wp_strip_all_tags($content), 0, 300, '...');
    }

    // Alternate names (keywords repeater)
    $alternates = get_field('condition_alternate', $post_id);
    $alt_names = [];
    if ($alternates && is_array($alternates)) {
        foreach ($alternates as $alt) {
            if (! empty($alt['text'])) {
                $alt_names[] = str_replace(',', '', $alt['text']);
            }
        }
    }

    // Related treatments (post IDs)
    $treatment_ids = get_field('condition_treatments', $post_id);
    $possible_treatments = [];
    if ($treatment_ids && is_array($treatment_ids)) {
        foreach ($treatment_ids as $tid) {
            if (get_post_status($tid) === 'publish') {
                $possible_treatments[] = [
                    '@type' => 'MedicalTherapy',
                    'name' => get_the_title($tid),
                    'url' => get_permalink($tid),
                ];
            }
        }
    }

    // Related providers (post IDs)
    $physician_ids = get_field('condition_physicians', $post_id);
    $providers = [];
    if ($physician_ids && is_array($physician_ids)) {
        foreach (array_slice($physician_ids, 0, 20) as $doc_id) {
            if (get_post_status($doc_id) !== 'publish') {
                continue;
            }

            $doc_degrees = [];
            $doc_degree_ids = get_field('physician_degree', $doc_id);
            if ($doc_degree_ids && is_array($doc_degree_ids)) {
                foreach ($doc_degree_ids as $did) {
                    $dt = get_term($did, 'degree');
                    if ($dt && ! is_wp_error($dt)) {
                        $doc_degrees[] = $dt->name;
                    }
                }
            }

            $doc_name = get_the_title($doc_id);
            if ($doc_degrees !== []) {
                $doc_name .= ', '.implode(', ', $doc_degrees);
            }

            $providers[] = [
                '@type' => 'Physician',
                'name' => $doc_name,
                'url' => get_permalink($doc_id),
            ];
        }
    }

    // Related locations (post IDs)
    $location_ids = get_field('condition_locations', $post_id);
    $available_at = [];
    if ($location_ids && is_array($location_ids)) {
        foreach (array_slice($location_ids, 0, 20) as $loc_id) {
            if (get_post_status($loc_id) !== 'publish') {
                continue;
            }

            $available_at[] = [
                '@type' => 'MedicalClinic',
                'name' => get_the_title($loc_id),
                'url' => get_permalink($loc_id),
            ];
        }
    }

    // MedlinePlus code
    $medline_type = get_field('medline_code_type', $post_id);
    $medline_code = get_field('medline_code_id', $post_id);

    // Build schema
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'MedicalCondition',
        'name' => $title,
        'url' => get_permalink($post_id),
    ];

    if ($description) {
        $schema['description'] = $description;
    }

    if ($alt_names !== []) {
        $schema['alternateName'] = $alt_names;
    }

    if ($medline_type && $medline_type !== 'none' && $medline_code) {
        $schema['code'] = [
            '@type' => 'MedicalCode',
            'codingSystem' => 'MedlinePlus',
            'codeValue' => $medline_code,
        ];
        $schema['sameAs'] = 'https://medlineplus.gov/'.($medline_type === 'topic' ? '' : 'ency/article/').rawurlencode($medline_code).'.htm';
    }

    if ($possible_treatments !== []) {
        $schema['possibleTreatment'] = $possible_treatments;
    }

    if ($providers !== []) {
        $schema['associatedAnatomy'] = null; // placeholder — remove if no anatomy data
    }

    $schema['isPartOf'] = [
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'url' => 'https://uamshealth.com',
    ];

    // Remove null values
    $schema = array_filter($schema, fn ($v): bool => $v !== null);

    uams_output_jsonld($schema);

    // If the page has FAQ-structured content, output FAQPage schema
    uams_maybe_faq_schema($post_id);
}

// ═══════════════════════════════════════════════════════════════
//  TREATMENT SCHEMA (MedicalProcedure / MedicalTherapy)
// ═══════════════════════════════════════════════════════════════

function uams_treatment_schema(): void
{
    $post_id = get_the_ID();
    $title = get_the_title();

    // Description
    $excerpt = get_the_excerpt();
    $content = get_the_content();
    $description = '';
    if ($excerpt) {
        $description = wp_strip_all_tags($excerpt);
    } elseif ($content) {
        $description = mb_strimwidth(wp_strip_all_tags($content), 0, 300, '...');
    }

    // Alternate names
    $alternates = get_field('treatment_procedure_alternate', $post_id);
    $alt_names = [];
    if ($alternates && is_array($alternates)) {
        foreach ($alternates as $alt) {
            if (! empty($alt['text'])) {
                $alt_names[] = str_replace(',', '', $alt['text']);
            }
        }
    }

    // Related conditions (post IDs)
    $condition_ids = get_field('treatment_conditions', $post_id);
    $conditions = [];
    if ($condition_ids && is_array($condition_ids)) {
        foreach ($condition_ids as $cid) {
            if (get_post_status($cid) === 'publish') {
                $conditions[] = [
                    '@type' => 'MedicalCondition',
                    'name' => get_the_title($cid),
                    'url' => get_permalink($cid),
                ];
            }
        }
    }

    // Related providers
    $physician_ids = get_field('treatment_procedure_physicians', $post_id);
    $providers = [];
    if ($physician_ids && is_array($physician_ids)) {
        foreach (array_slice($physician_ids, 0, 20) as $doc_id) {
            if (get_post_status($doc_id) !== 'publish') {
                continue;
            }

            $doc_degrees = [];
            $doc_degree_ids = get_field('physician_degree', $doc_id);
            if ($doc_degree_ids && is_array($doc_degree_ids)) {
                foreach ($doc_degree_ids as $did) {
                    $dt = get_term($did, 'degree');
                    if ($dt && ! is_wp_error($dt)) {
                        $doc_degrees[] = $dt->name;
                    }
                }
            }

            $doc_name = get_the_title($doc_id);
            if ($doc_degrees !== []) {
                $doc_name .= ', '.implode(', ', $doc_degrees);
            }

            $providers[] = [
                '@type' => 'Physician',
                'name' => $doc_name,
                'url' => get_permalink($doc_id),
            ];
        }
    }

    // Related locations
    $location_ids = get_field('treatment_procedure_locations', $post_id);
    $available_at = [];
    if ($location_ids && is_array($location_ids)) {
        foreach (array_slice($location_ids, 0, 20) as $loc_id) {
            if (get_post_status($loc_id) !== 'publish') {
                continue;
            }

            $available_at[] = [
                '@type' => 'MedicalClinic',
                'name' => get_the_title($loc_id),
                'url' => get_permalink($loc_id),
            ];
        }
    }

    // MedlinePlus code
    $medline_type = get_field('medline_code_type', $post_id);
    $medline_code = get_field('medline_code_id', $post_id);

    // Determine type: MedicalProcedure for surgical, MedicalTherapy for non-surgical
    // Default to MedicalProcedure — can be refined with an ACF field later
    $schema_type = 'MedicalProcedure';

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $schema_type,
        'name' => $title,
        'url' => get_permalink($post_id),
    ];

    if ($description) {
        $schema['description'] = $description;
    }

    if ($alt_names !== []) {
        $schema['alternateName'] = $alt_names;
    }

    if ($medline_type && $medline_type !== 'none' && $medline_code) {
        $schema['code'] = [
            '@type' => 'MedicalCode',
            'codingSystem' => 'MedlinePlus',
            'codeValue' => $medline_code,
        ];
        $schema['sameAs'] = 'https://medlineplus.gov/'.($medline_type === 'topic' ? '' : 'ency/article/').rawurlencode($medline_code).'.htm';
    }

    if ($conditions !== []) {
        $schema['indication'] = $conditions;
    }

    if ($available_at !== []) {
        $schema['availableService'] = $available_at;
    }

    $schema['isPartOf'] = [
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'url' => 'https://uamshealth.com',
    ];

    uams_output_jsonld($schema);

    // If the page has FAQ-structured content, output FAQPage schema
    uams_maybe_faq_schema($post_id);
}

// ═══════════════════════════════════════════════════════════════
//  FAQ SCHEMA (FAQPage)
//  Outputs FAQPage JSON-LD when FAQ content is detected.
//  Works with two patterns:
//    1. ACF repeater field named 'faq' or 'faqs' with 'question'/'answer' sub-fields
//    2. HTML pattern: <h3> followed by content (common FAQ format)
//  Call uams_output_faq_schema($questions) directly from a template
//  to output FAQ schema for manually-curated Q&A blocks.
// ═══════════════════════════════════════════════════════════════

function uams_maybe_faq_schema($post_id = null): void
{
    if (! $post_id) {
        $post_id = get_the_ID();
    }

    $questions = [];

    // Pattern 1: ACF repeater field 'faq' or 'faqs'
    $faq_field = null;
    if (have_rows('faq', $post_id)) {
        $faq_field = 'faq';
    } elseif (have_rows('faqs', $post_id)) {
        $faq_field = 'faqs';
    }

    if ($faq_field) {
        while (have_rows($faq_field, $post_id)) {
            the_row();
            $q = get_sub_field('question') ?: get_sub_field('faq_question') ?: '';
            $a = get_sub_field('answer') ?: get_sub_field('faq_answer') ?: '';
            if ($q && $a) {
                $questions[] = [
                    'question' => wp_strip_all_tags($q),
                    'answer' => wp_strip_all_tags($a),
                ];
            }
        }
    }

    if ($questions !== []) {
        uams_output_faq_schema($questions);
    }
}

function uams_output_faq_schema($questions): void
{
    if (empty($questions)) {
        return;
    }

    $main_entity = [];
    foreach ($questions as $qa) {
        $main_entity[] = [
            '@type' => 'Question',
            'name' => $qa['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $qa['answer'],
            ],
        ];
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $main_entity,
    ];

    uams_output_jsonld($schema);
}

// ═══════════════════════════════════════════════════════════════
//  BREADCRUMB SCHEMA
// ═══════════════════════════════════════════════════════════════

function uams_breadcrumb_schema(): void
{
    $items = [];
    $pos = 1;

    $items[] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'name' => 'UAMS Health',
        'item' => 'https://uamshealth.com',
    ];

    $post_type = get_post_type();
    $type_labels = [
        'provider' => ['Find a Doctor',  'https://uamshealth.com/provider/'],
        'location' => ['Locations',       'https://uamshealth.com/location/'],
        'condition' => ['Conditions',     'https://uamshealth.com/condition/'],
        'treatment' => ['Treatments',     'https://uamshealth.com/treatment/'],
        'expertise' => ['Areas of Expertise', 'https://uamshealth.com/expertise/'],
    ];

    if (isset($type_labels[$post_type])) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $type_labels[$post_type][0],
            'item' => $type_labels[$post_type][1],
        ];
    }

    // Parent location for child locations
    if ($post_type === 'location') {
        $has_parent = get_field('location_parent');
        $parent_id = get_field('location_parent_id');
        if ($has_parent && $parent_id) {
            $parent_post = get_post($parent_id);
            if ($parent_post) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $pos++,
                    'name' => $parent_post->post_title,
                    'item' => get_permalink($parent_id),
                ];
            }
        }
    }

    // Last item — use display name for providers, post_title for everything else
    if ($post_type === 'provider') {
        $post_id = get_the_ID();
        $first = get_field('physician_first_name', $post_id) ?: '';
        $middle = get_field('physician_middle_name', $post_id) ?: '';
        $last = get_field('physician_last_name', $post_id) ?: '';
        $full_name = trim("$first $middle $last");

        $degree_ids = get_field('physician_degree', $post_id);
        $degrees = [];
        if ($degree_ids && is_array($degree_ids)) {
            foreach ($degree_ids as $term_id) {
                $term = get_term($term_id, 'degree');
                if ($term && ! is_wp_error($term)) {
                    $degrees[] = $term->name;
                }
            }
        }

        $label = $degrees !== [] ? $full_name.', '.implode(', ', $degrees) : $full_name;
    } else {
        $label = get_the_title();
    }

    $items[] = [
        '@type' => 'ListItem',
        'position' => $pos,
        'name' => $label,
    ];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    uams_output_jsonld($schema);
}

// ═══════════════════════════════════════════════════════════════
//  ORGANIZATION SCHEMA (homepage)
// ═══════════════════════════════════════════════════════════════

function uams_organization_schema(): void
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'legalName' => 'University of Arkansas for Medical Sciences',
        'url' => 'https://uamshealth.com',
        'logo' => get_stylesheet_directory_uri().'/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
        'sameAs' => [
            'https://www.facebook.com/UAMSHealth',
            'https://twitter.com/uaborhealthcare',
            'https://www.instagram.com/uamshealth/',
            'https://www.linkedin.com/company/uams/',
            'https://www.youtube.com/user/UAMSHealth',
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '4301 W. Markham Street',
            'addressLocality' => 'Little Rock',
            'addressRegion' => 'AR',
            'postalCode' => '72205',
            'addressCountry' => 'US',
        ],
        'telephone' => '+1-501-686-7000',
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+1-501-686-8000',
            'contactType' => 'patient appointments',
        ],
    ];

    uams_output_jsonld($schema);
}

// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════

function uams_output_jsonld($data): void
{
    echo "\n".'<script type="application/ld+json">'."\n";
    // JSON_HEX_TAG and JSON_HEX_AMP keep a "</script>" or "&" inside a value from ending the
    // script element or being read as an entity; slashes stay unescaped for readable URLs.
    echo wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP);
    echo "\n</script>\n";
}

function uams_format_phone_schema($phone)
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 10) {
        return '+1-'.substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6);
    }

    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+1-'.substr($digits, 1, 3).'-'.substr($digits, 4, 3).'-'.substr($digits, 7);
    }

    return $phone;
}

function uams_day_abbreviation($day): string
{
    $map = [
        'Monday' => 'Mo', 'Tuesday' => 'Tu', 'Wednesday' => 'We',
        'Thursday' => 'Th', 'Friday' => 'Fr', 'Saturday' => 'Sa',
        'Sunday' => 'Su', 'Mon - Fri' => 'Mo-Fr',
    ];

    return $map[$day] ?? '';
}
