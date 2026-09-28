<?php

declare(strict_types=1);

namespace Quoyer\Enums;

enum CatalogueKind: string
{
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
}
