Written against v1.0.2.

Every block the plugin writes, the page that gets it, and where each property comes from. The blocks are written in the footer (`wp_footer`, priority 99) in both modes; [Choosing replace or safe mode](Choosing-replace-or-safe-mode.md) covers what the mode changes. Field names are the ACF fields of the Find-a-Doc plugin. A property whose source is empty is left out, unless the table says it is always written.

- [Which page gets which block](#which-page-gets-which-block)
- [Provider pages](#provider-pages)
- [Location pages](#location-pages)
- [Condition pages](#condition-pages)
- [Treatment pages](#treatment-pages)
- [FAQ block](#faq-block)
- [Breadcrumb block](#breadcrumb-block)
- [Home page](#home-page)

## Which page gets which block

| Page | Blocks, in order |
| --- | --- |
| A single `provider` | `Physician`, `BreadcrumbList` |
| A single `location` | `MedicalClinic`, `BreadcrumbList` |
| A single `condition` | `MedicalCondition`, `FAQPage` when the post has FAQ rows, `BreadcrumbList` |
| A single `treatment` | `MedicalProcedure`, `FAQPage` when the post has FAQ rows, `BreadcrumbList` |
| A single `expertise` | `BreadcrumbList` |
| The front page | `MedicalOrganization` |
| Anything else | nothing |

## Provider pages

A `Physician` block, with `memberOf` naming UAMS Health.

| Property | Source |
| --- | --- |
| `name` | first, middle and last name, then the degrees: `<First> <Middle> <Last>, MD, PhD` |
| `givenName`, `familyName`, `additionalName` | `physician_first_name`, `physician_last_name`, `physician_middle_name` |
| `honorificPrefix` | `physician_prefix` |
| `honorificSuffix` | `physician_suffix`, then the degrees: `Jr., MD, PhD` |
| `url` | the page's permalink, always |
| `image` | the featured image at `large` size; without one, the Find-a-Doc placeholder image, always |
| `logo` | the theme's UAMS Health logo, always |
| `jobTitle` | the `clinical_title` term in `physician_title` |
| `description` | `physician_short_clinical_bio`; without it, `physician_clinical_bio` cut to 250 characters; HTML stripped |
| `identifier` | `physician_npi`, as a `PropertyValue` with `propertyID` `NPI` |
| `medicalSpecialty` | the titles of the published conditions in `physician_conditions_cpt` and treatments in `physician_treatments_cpt`, each once |
| `expertiseCondition` | the published conditions in `physician_conditions_cpt`, each with its name and URL |
| `knowsLanguage` | the `language` terms in `physician_languages` |
| `isAcceptingNewPatients` | `physician_accepting_patients`, as true or false; left out when the field is unset |
| `hasCredential` | each `physician_education` row with a `school` term, then each `boards` term in `physician_boards` as a board certification |
| `address` | one `PostalAddress` per published location in `physician_locations`; a single address when there is one |
| `workLocation` | one `MedicalClinic` per published location, with its name, URL, address and telephone |
| `aggregateRating` | the Press Ganey data stored on the post, only when the provider has an NPI and at least 30 ratings |
| `memberOf` | UAMS Health, always |

A location that has a parent location takes its address from the parent. The street line joins `location_address_1` with the building name and `location_suite`, or `location_address_2` when there is neither.

## Location pages

A `MedicalClinic` block, with `isPartOf` naming UAMS Health.

| Property | Source |
| --- | --- |
| `name`, `url` | the page title and permalink, always |
| `logo` | the theme's UAMS Health logo, always |
| `image` | the featured image at `large` size: the parent location's, unless the location overrides the parent's image with its own |
| `description` | `location_short_desc`; without it, `location_about` cut to 250 characters; HTML stripped |
| `address` | the location's address, or its parent's, with `addressCountry` `US`, always |
| `geo` | the latitude and longitude of `location_map` |
| `telephone` | `location_phone`, formatted for schema |
| `openingHours` | the rows of `location_hours_group`, skipping closed days: `Mo 08:00-17:00`; `Mo-Su 00:00-23:59` for a location open around the clock |
| `openingHoursSpecification` | the modified hours of `location_hours_group`, with their start and end dates |
| `specialOpeningHoursSpecification` | a closed-all-week entry, when the location is marked as closing temporarily and its closing date has passed |
| `medicalSpecialty` | the titles of the published conditions in `location_conditions_cpt` and treatments in `location_treatments_cpt` |
| `availableService` | up to 50 published providers who list this location, each as a `Physician` with name and degrees |
| `isPartOf` | UAMS Health, always |

A location whose hours are marked as variable gets no hours at all.

## Condition pages

A `MedicalCondition` block, with `isPartOf` naming UAMS Health, then an [FAQ block](#faq-block) when the post has FAQ rows.

| Property | Source |
| --- | --- |
| `name`, `url` | the page title and permalink, always |
| `description` | the excerpt; without it, the content cut to 300 characters; HTML stripped |
| `alternateName` | each row of `condition_alternate`, with commas removed |
| `code`, `sameAs` | `medline_code_type` and `medline_code_id`, as a MedlinePlus code and the matching MedlinePlus page; left out when the type is `none` |
| `possibleTreatment` | the published treatments in `condition_treatments`, each as a `MedicalTherapy` |
| `isPartOf` | UAMS Health, always |

## Treatment pages

A `MedicalProcedure` block, with `isPartOf` naming UAMS Health, then an [FAQ block](#faq-block) when the post has FAQ rows. Every treatment is written as `MedicalProcedure`; nothing selects `MedicalTherapy`.

| Property | Source |
| --- | --- |
| `name`, `url` | the page title and permalink, always |
| `description` | the excerpt; without it, the content cut to 300 characters; HTML stripped |
| `alternateName` | each row of `treatment_procedure_alternate`, with commas removed |
| `code`, `sameAs` | `medline_code_type` and `medline_code_id`, as on condition pages |
| `indication` | the published conditions in `treatment_conditions` |
| `availableService` | up to 20 published locations in `treatment_procedure_locations`, each as a `MedicalClinic` |
| `isPartOf` | UAMS Health, always |

## FAQ block

An `FAQPage` block whose `mainEntity` is one `Question` per row, each with an `acceptedAnswer`. The rows come from the `faq` or `faqs` repeater; [Adding FAQ schema to a page](Adding-FAQ-schema-to-a-page.md) has the field names.

## Breadcrumb block

A `BreadcrumbList` on every single provider, location, condition, treatment and expertise page:

1. UAMS Health, linking to the home page.
2. The section for the post type: Find a Doctor, Locations, Conditions, Treatments or Areas of Expertise, linking to its archive.
3. On a location with a parent location, the parent, linking to its page.
4. The page itself, with no link: the provider's name and degrees, or the page title.

## Home page

A `MedicalOrganization` block for UAMS Health: its name and legal name, URL, logo, social profiles, postal address, main telephone and an appointments `ContactPoint`. The values are fixed in the plugin file, not read from the site.
