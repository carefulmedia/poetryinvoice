<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a journal year entity type.
 */
interface JournalYearInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
