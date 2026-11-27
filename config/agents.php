<?php

return [

    /*
    | How long a human has to approve or reject a risky tool call
    | before the run fails. Default: 24 hours.
    */
    'approval_ttl_minutes' => (int) env('AGENT_APPROVAL_TTL_MINUTES', 1440),

];
