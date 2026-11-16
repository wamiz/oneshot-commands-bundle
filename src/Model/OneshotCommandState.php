<?php

declare(strict_types=1);

namespace Wamiz\OneshotCommands\Model;

enum OneshotCommandState: string
{
    /** Never run, will be played by `wamiz:oneshot-commands:run`. */
    case Pending = 'pending';
    /** Never run, not played automatically (`immediate: false`). */
    case Manual = 'manual';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
