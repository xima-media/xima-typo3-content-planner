<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

enum HeaderMode: string
{
    case EDIT = 'edit';
    case WEB_LAYOUT = 'web_layout';
    case WEB_LIST = 'web_list';
}
