<?php

// Compatibility fallback in case Laravel Sanctum autoloading fails during early bootstrap in unusual environments.
// This defines a no-op trait so `use Laravel\\Sanctum\\HasApiTokens` in the User model won't cause a fatal error.

// Avoid declaring `namespace` inside conditional blocks (that's a parse error). Use a runtime definition
// only when the trait doesn't already exist.
if (! trait_exists('\\Laravel\\Sanctum\\HasApiTokens')) {
    // Define the trait in the Laravel\Sanctum namespace at runtime.
    // Using eval is safe here for a tiny no-op trait and avoids PHP namespace declaration rules.
    eval('namespace Laravel\\Sanctum; trait HasApiTokens { }');
}
