<?php

namespace Drupal\webprofiler\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityTypeManagerWrapper.
 */
class EntityTypeManagerWrapper extends DefaultPluginManager implements EntityTypeManagerInterface, ContainerAwareInterface {

  /**
   * @var array
   */
  private $loaded;

  /**
   * @var array
   */
  private $rendered;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($entity_type) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $handler */
    $handler = $this->getHandler($entity_type, 'storage');
    $type = ($handler instanceof ConfigEntityStorageInterface) ? 'config' : 'content';

    if (!isset($this->loaded[$type][$entity_type])) {
      $handler = $this->getStorageDecorator($entity_type, $handler);
      $this->loaded[$type][$entity_type] = $handler;
    }
    else {
      $handler = $this->loaded[$type][$entity_type];
    }

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewBuilder($entity_type) {
    /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $handler */
    $handler = $this->getHandler($entity_type, 'view_builder');

    if ($handler instanceof EntityViewBuilderInterface) {
      if (!isset($this->rendered[$entity_type])) {
        $handler = new EntityViewBuilderDecorator($handler);
        $this->rendered[$entity_type] = $handler;
      }
      else {
        $handler = $this->rendered[$entity_type];
      }
    }

    return $handler;
  }

  /**
   * @param $type
   * @param $entity_type
   *
   * @return array
   */
  public function getLoaded($type, $entity_type) {
    return isset($this->loaded[$type][$entity_type]) ? $this->loaded[$type][$entity_type] : NULL;
  }

  /**
   * @param $entity_type
   *
   * @return array
   */
  public function getRendered($entity_type) {
    return isset($this->rendered[$entity_type]) ? $this->rendered[$entity_type] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE) {
    $this->entityManager->useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $this->entityManager->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessControlHandler($entity_type) {
    return $this->entityManager->getAccessControlHandler($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    $this->entityManager->clearCachedDefinitions();
    $this->loaded = NULL;
    $this->rendered = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getListBuilder($entity_type) {
    return $this->entityManager->getListBuilder($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject($entity_type, $operation) {
    return $this->entityManager->getFormObject($entity_type, $operation);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteProviders($entity_type) {
    return $this->entityManager->getRouteProviders($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function hasHandler($entity_type, $handler_type) {
    return $this->entityManager->hasHandler($entity_type, $handler_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler($entity_type, $handler_type) {
    return $this->entityManager->getHandler($entity_type, $handler_type);
  }

  /**
   * {@inheritdoc}
   */
  public function createHandlerInstance(
    $class,
    EntityTypeInterface $definition = NULL
  ) {
    return $this->entityManager->createHandlerInstance($class, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($entity_type_id, $exception_on_invalid = TRUE) {
    return $this->entityManager->getDefinition(
      $entity_type_id,
      $exception_on_invalid
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return $this->entityManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->entityManager->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->entityManager->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public function setContainer(ContainerInterface $container = NULL) {
    $this->entityManager->setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveDefinition($entity_type_id) {
    return $this->entityManager->getActiveDefinition($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveFieldStorageDefinitions($entity_type_id) {
    return $this->entityManager->getActiveFieldStorageDefinitions($entity_type_id);
  }

  /**
   * Return a decorator for the storage handler.
   *
   * @param $entity_type
   * @param $handler
   *
   * @return \Drupal\webprofiler\Entity\EntityDecorator
   */
  private function getStorageDecorator($entity_type, $handler) {
    // Loaded this way to avoid circular references.
    /** @var \Drupal\webprofiler\DecoratorGeneratorInterface $decoratorGenerator */
    $decoratorGenerator = \Drupal::service('webprofiler.config_entity_storage_decorator_generator');
    $decorators = $decoratorGenerator->getDecorators();

    $storage = PhpStorageFactory::get('webprofiler');
    if ($handler instanceof ConfigEntityStorageInterface) {
      if (array_key_exists($entity_type, $decorators)) {
        $storage->load($entity_type);
        if (!class_exists($decorators[$entity_type])) {
          try {
            $decoratorGenerator->generate();
            $storage->load($entity_type);
          }
          catch (\Exception $e) {
            return $handler;
          }
        }

        return new $decorators[$entity_type]($handler);
      }

      return new ConfigEntityStorageDecorator($handler);
    }

    return $handler;
  }

}