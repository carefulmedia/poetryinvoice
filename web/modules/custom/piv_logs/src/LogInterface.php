<?php

declare(strict_types=1);

namespace Drupal\piv_logs;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a log entity type.
 */
interface LogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
