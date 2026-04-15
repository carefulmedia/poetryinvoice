<?php

declare(strict_types=1);

namespace Drupal\piv_futureverse;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a poet bio entity type.
 */
interface PoetBioInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
