<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * The answer to a catalogue upsert. `received` = `upserted` + `skipped`.
 * A malformed row is skipped and counted, never fatal.
 *
 * @property-read string $object `catalogue_sync_result`
 * @property-read int $received
 * @property-read int $upserted
 * @property-read int $skipped
 */
final class CatalogueSyncResult extends QuoyerObject {}
