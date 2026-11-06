<?php

declare(strict_types=1);

/**
 * Static mock data — fase 1 layout (PDF pagina 7, Week 14).
 * Vervangen door DB-koppeling in fase 2.
 */
return [
    'week_label' => 'Week 14',
    'headline'   => 'Post name 1',

    /* Rationale: read-only, 18/21 plain text */
    'rationale'  => "Lorem ipsum dolor sit amet,\nconsectetuer adipiscing\nelit, sed diam nonummy\nnibh euismod tincidunt ut\nlaoreet dolore magna\naliquam erat volutpat. Ut\nwisi enim",

    /* Artikel-body */
    'content'    => "Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi",

    'hashtags'   => '#nummer1, #nummer2, #nummer3,',
    'source_url' => 'https://example.com/bron-artikel',

    /* Tone — b to b, b to c, internal, partners, personal */
    'tone' => [
        'options'  => ['b to b', 'b to c', 'internal', 'partners', 'personal'],
        'selected' => 'b to b',
    ],

    /* Angle — PDF: archetype, adversary, general */
    'angle' => [
        'options'  => ['archetype', 'adversary', 'general'],
        'selected' => 'archetype',
    ],

    /* Image ratio — PDF: square, landscape */
    'image_ratio' => [
        'options'  => ['square', 'landscape'],
        'selected' => 'square',
    ],

    /* Platform — PDF: Linkedin, Instagram, X, Facebook, Blog */
    'platform' => [
        'options'  => ['Linkedin', 'Instagram', 'X', 'Facebook', 'Blog'],
        'selected' => 'Linkedin',
    ],
];
