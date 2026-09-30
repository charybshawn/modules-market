<?php

namespace Cultpantry\Market\Support;

/**
 * The routing service can't answer right now: no key, no connection, a refusal
 * or a rate limit. Callers fall back to showing the list without the filter.
 */
class RoutingUnavailable extends \RuntimeException {}
