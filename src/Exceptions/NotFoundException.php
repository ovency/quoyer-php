<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * 404: the id in the path matches nothing (`resource_not_found`), no customer
 * matches the identifiers in the body (`customer_not_found`), or nothing was
 * credited for a reversal (`bucket_not_found`, which you can treat as done).
 */
class NotFoundException extends InvalidRequestException {}
