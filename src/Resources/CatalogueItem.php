<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * A product, category or brand, so earning rules can be set up by name.
 * A name lookup only: points are computed from an order's line items.
 *
 * @property-read string $id YOUR id.
 * @property-read string $object `catalogue_item`
 * @property-read string $kind `product`, `category` or `brand`.
 * @property-read string $name
 * @property-read string|null $parent_id
 * @property-read string|null $url
 * @property-read string|null $image_url
 * @property-read string|null $last_seen_at
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class CatalogueItem extends QuoyerObject {}
