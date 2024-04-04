<?php

namespace Drupal\piv_search\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;

/**
 * PIV Search event subscriber.
 */
class PivSearchSubscriber implements EventSubscriberInterface {

  /**
   * Event handler for when a item is indexed on solr.
   */
  public function postCreateIndexDocument(PostCreateIndexDocumentEvent $event): void {
    $document = $event->getSolariumDocument();
    $entity = $event->getSearchApiItem()->getOriginalObject()->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    if ($entity_type == 'user') {
      $full_name = $entity->getDisplayName();
      $author = array_map('strtolower', explode(' ', $full_name));
      // Field zm_full_name is for storage only.
      $document->setField('zm_full_name', $full_name)
        ->setField('tm_X3b_und_full_name_searchable', $author)
        ->removeField('tm_X3b_en_full_name_searchable')
        ->setField('zs_entity_type', 'Poet')
        ->setBoost(100);
    }
    elseif ($entity_type == 'node') {
      $bundle_label = $entity->type->entity->label();
      $document->setField('zs_entity_type', $bundle_label);
      if ($bundle == 'poem') {
        $author = $entity->uid->entity->getDisplayName();
        $author = array_map('strtolower', explode(' ', $author));
        $document->setField('tm_X3b_und_poem_author', $author)
          ->removeField('tm_X3b_en_poem_author');
      }
      else {
        // Remove dummy fields if no value.
        $document->removeField('tm_X3b_und_poem_author')
          ->removeField('sort_X3b_und_poem_author')
          ->removeField('tm_X3b_en_poem_author');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiSolrEvents::POST_CREATE_INDEX_DOCUMENT => ['postCreateIndexDocument'],
    ];
  }

}
