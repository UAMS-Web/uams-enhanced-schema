<?php

/**
 * uams_organization_schema() writes the fixed MedicalOrganization block, with the
 * logo taken from the active theme.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

it('writes the organization schema with the theme logo', function (): void {
    when('wp_json_encode')->alias('json_encode');
    when('get_stylesheet_directory_uri')->justReturn('https://example.test/wp-content/themes/uams');

    ob_start();
    uams_organization_schema();
    $out = (string) ob_get_clean();

    expect($out)->toStartWith("\n<script type=\"application/ld+json\">\n")
        ->and($out)->toEndWith("\n</script>\n");

    $json = trim(substr($out, strlen("\n<script type=\"application/ld+json\">\n"), -strlen("\n</script>\n")));

    expect(json_decode($json, true))->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'MedicalOrganization',
        'name' => 'UAMS Health',
        'legalName' => 'University of Arkansas for Medical Sciences',
        'url' => 'https://uamshealth.com',
        'logo' => 'https://example.test/wp-content/themes/uams/assets/svg/uams-logo_health_horizontal_dark_386x50.png',
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
    ]);
});
