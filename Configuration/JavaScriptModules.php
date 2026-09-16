<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

// EXT:rte_ckeditor's own JavaScriptModules.php (@typo3/rte-ckeditor/*, the @ckeditor/*
// packages it depends on) is tagged 'backend.form', so it is excluded from the importmap of
// any page that never declares that tag - which is every page the inline comment composer
// (CP-28, #327) can appear on, since none of them go through FormEngine. Re-declaring the
// same mapping here, under the tag this extension's own modules already rely on, makes the
// entries resolvable wherever @content-planner/* already is - without hand-copying
// rte_ckeditor's own import list (and risking it drifting out of sync with a future core
// minor): read it from the installed package instead. Guarded by isLoaded() because minimal
// test/CI package sets (this extension's own functional suite included) do not necessarily
// activate rte_ckeditor, and ExtensionManagementUtility::extPath() throws for an inactive one.
$rteCKEditorImports = [];
if (ExtensionManagementUtility::isLoaded('rte_ckeditor')) {
    $rteCKEditorModules = include ExtensionManagementUtility::extPath('rte_ckeditor').'Configuration/JavaScriptModules.php';
    $rteCKEditorImports = is_array($rteCKEditorModules['imports'] ?? null) ? $rteCKEditorModules['imports'] : [];
}

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.contextmenu',
    ],
    'imports' => [
        Configuration::JAVASCRIPT_MODULE_PREFIX => 'EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/',
        ...$rteCKEditorImports,
    ],
];
