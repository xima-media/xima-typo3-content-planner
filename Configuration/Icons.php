<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;
use Xima\XimaTypo3ContentPlanner\Configuration;

return [
    // flag
    'flag-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-black.svg',
    ],
    'flag-white' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-white',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-white.svg',
    ],
    'flag-toolbar' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-toolbar',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-toolbar.svg',
    ],
    'flag-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-blue.svg',
    ],
    'flag-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-ed',
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-gray.svg',
    ],
    'flag-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-green.svg',
    ],
    'flag-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-red.svg',
    ],
    'flag-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/flags.svg#flag-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/flags/flag-yellow.svg',
    ],
    // star
    'star-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-black.svg',
    ],
    'star-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-blue.svg',
    ],
    'star-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-gray.svg',
    ],
    'star-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-green.svg',
    ],
    'star-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-red.svg',
    ],
    'star-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/stars.svg#star-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/stars/star-yellow.svg',
    ],
    // tag
    'tag-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-black.svg',
    ],
    'tag-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-blue.svg',
    ],
    'tag-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-gray.svg',
    ],
    'tag-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-green.svg',
    ],
    'tag-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-red.svg',
    ],
    'tag-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/tags.svg#tag-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/tags/tag-yellow.svg',
    ],
    // info
    'info-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-black.svg',
    ],
    'info-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-blue.svg',
    ],
    'info-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-gray.svg',
    ],
    'info-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-green.svg',
    ],
    'info-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-red.svg',
    ],
    'info-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/infos.svg#info-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/infos/info-yellow.svg',
    ],
    // heart
    'heart-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-black.svg',
    ],
    'heart-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-blue.svg',
    ],
    'heart-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-gray.svg',
    ],
    'heart-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-green.svg',
    ],
    'heart-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-red.svg',
    ],
    'heart-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/hearts.svg#heart-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/hearts/heart-yellow.svg',
    ],
    // color
    'color-black' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-black',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-black.svg',
    ],
    'color-blue' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-blue',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-blue.svg',
    ],
    'color-gray' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-gray',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-gray.svg',
    ],
    'color-green' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-green',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-green.svg',
    ],
    'color-red' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-red',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-red.svg',
    ],
    'color-yellow' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/sprites/colors.svg#color-yellow',
        'source' => 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Icons/svgs/colors/color-yellow.svg',
    ],
];
