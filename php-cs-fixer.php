<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use EliasHaeussler\PhpCsFixerConfig;
use EliasHaeussler\PhpCsFixerConfig\Rules\RuleSet;
use TYPO3\CodingStandards;

$header = PhpCsFixerConfig\Rules\Header::create(
    \Xima\XimaTypo3ContentPlanner\Configuration::EXT_KEY,
    PhpCsFixerConfig\Package\Type::TYPO3Extension,
    PhpCsFixerConfig\Package\Author::create('Konrad Michalik', 'hej@konradmichalik.dev'),
    PhpCsFixerConfig\Package\CopyrightRange::from(2024),
    PhpCsFixerConfig\Package\License::GPL2OrLater,
);

$config = CodingStandards\CsFixerConfig::create();
$finder = $config->getFinder()
    ->in(__DIR__)
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
;

return PhpCsFixerConfig\Config::create()
    ->withConfig($config)
    ->withRule(
        RuleSet::fromArray(
            KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::create(
                [
                    'author' => 'Konrad Michalik <hej@konradmichalik.dev>',
                    'license' => 'GPL-2.0',
                ],
                addStructureName: true,
            )->__toArray(),
        ),
    )
    ->registerCustomFixers([new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer()])
    ->withRule($header)
;
