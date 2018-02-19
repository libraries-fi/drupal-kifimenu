<?php

namespace Drupal\kifimenu\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\kifimenu\Breadcrumb\MenuBreadcrumbBuilder;
use Drupal\kifimenu\BlockConfiguredMenu;

/**
 * Render a menu in a block
 *
 * @Block(
 *   id = "kifi_breadcrumb_block",
 *   admin_label = @Translation("Breadcrumb")
 * )
 */
class BreadcrumbBlock extends BlockBase implements ContainerFactoryPluginInterface {
  protected $routeMatch;
  protected $crumbManager;
  protected $menuBasedBuilder;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('menu'),
      $container->get('current_route_match'),
      $container->get('breadcrumb'),
      $container->get('kifimenu.block_configured_menu')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $menu_storage, RouteMatchInterface $route_match, BreadcrumbBuilderInterface $crumb_manager, BlockConfiguredMenu $menu_name) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuStorage = $menu_storage;
    $this->routeMatch = $route_match;
    $this->crumbManager = $crumb_manager;
    $this->menuName = $menu_name;

  }

  public function build() {
    $this->menuName->setMenuName($this->configuration['menu_name']);
    $source = $this->crumbManager->build($this->routeMatch);
    $this->menuName->setMenuName(NULL);

    $links = array_slice($source->getLinks(), $this->configuration['depth_min'] - 1);

    $crumb = new Breadcrumb;
    $crumb->setLinks($links);
    $crumb->addCacheableDependency($source);
    return $crumb->toRenderable();
  }

  public function defaultConfiguration() {
    return [
      'menu_name' => 'main',
      'depth_min' => 1,
    ];
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['menu_name'] = [
      '#type' => 'select',
      '#title' => t('Menu'),
      '#options' => $this->getMenuOptions(),
      '#default_value' => 'main',
    ];
    $form['depth_min'] = [
      '#type' => 'select',
      '#title' => t('First level'),
      '#options' => array_combine(range(1, 5), range(1, 5)),
      '#default_value' => $this->configuration['depth_min'],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['menu_name'] = $form_state->getValue('menu_name');
    $this->configuration['depth_min'] = $form_state->getValue('depth_min');
  }

  protected function getMenuOptions() {
    $menus = $this->menuStorage->loadMultiple();
    $options = array_map(function($menu) { return $menu->label(); }, $menus);
    return $options;
  }
}
