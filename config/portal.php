<?php

/*
|--------------------------------------------------------------------------
| Customer portal (Phase 12c)
|--------------------------------------------------------------------------
| The legacy Web\LoginController accepted, in order: a bcrypt password, the
| stored password compared as PLAINTEXT (self-healing to a hash on match),
| and the policy number in NNN-NNN-NNN form matched against email +
| policy_number. The last two are insecure and stay OFF unless a deployment
| deliberately opts in.
*/

return [

    // Enable the plaintext-password and policy-number login fallbacks.
    'legacy_login_fallbacks' => (bool) env('PORTAL_LEGACY_LOGIN_FALLBACKS', false),

    // Where a signed-in customer lands (legacy Web\LoginController sent them
    // to the claims list).
    'home_route' => 'portal.claims.index',
];
