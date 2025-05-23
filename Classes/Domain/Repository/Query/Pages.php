<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository\Query;

class Pages extends AbstractQuery implements QueryInterface
{
    protected array $selects = [
        'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody',
    ];
}
