<?php

return [

    /*
    | Content-Security-Policy (App\Http\Middleware\ContentSecurityPolicy).
    |   enforce      block anything the policy doesn't allow (default)
    |   report-only  allow it, but report each violation to /csp-report
    |   off          send no policy
    | Switch to report-only for a quick rollback without a code change.
    */
    'csp_mode' => in_array(env('CSP_MODE'), ['enforce', 'report-only', 'off'], true) ? env('CSP_MODE') : 'enforce',

];
