<?php

use Xima\XimaTypo3ContentPlanner\Configuration;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comment',
        'label' => 'content',
        'label_alt' => 'author, content',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => [
            'default' => 'content-message',
        ],
        'default_sortby' => 'crdate',
        'searchFields' => 'title,text',
        'hideTable' => true,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'content,author',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'content' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_comment.content',
            'config' => [
                'enableRichtext' => true,
                'richtextConfiguration' => 'comments',
                'type' => 'text',
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 5,
                'max' => 500,
                'required' => true,
            ],
        ],
        'author' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'foreign_table' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'foreign_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'pid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
