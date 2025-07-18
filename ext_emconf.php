<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Planner',
    'description' => 'This extension provides a page status functionality to support the planning of content work.',
    'category' => 'module',
    'author' => 'Konrad Michalik',
    'author_email' => 'hej@konradmichalik.dev',
    'state' => 'stable',
    'version' => '1.5.2',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.3.99',
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
