<?php

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

namespace Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Enums;

enum Selectors: string
{
    case Alert = '.alert';
    case BackendPageModule = '[data-moduleroute-identifier="web_layout"]';
    case ContextMenu = '.context-menu';
    case ContextMenuGroup = '.context-menu .context-menu-group';
    case ContextMenuSubmenu = '.context-menu[data-contextmenu-parent]';
    case ContextMenuSubmenuLegacy = '.context-menu[data-parent]';
    case Tree = '#typo3-pagetree-treeContainer';
    case TreeItem = '.nodes-list > [role="treeitem"]';

}
