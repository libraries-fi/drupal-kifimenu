<?php

namespace Drupal\kifimenu\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Render a menu in a block
 *
 * @Block(
 *   id = "kifimenu_block",
 *   admin_label = @Translation("Menu Tree")
 * )
 */
class MenuBlock extends BlockBase implements ContainerFactoryPluginInterface {
  const EXPAND_NONE = 0;
  const EXPAND_ALL = 1;
  const EXPAND_ACTIVE = 2;

  protected $menuStorage;
  protected $menuTrail;
  protected $menuTree;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('menu'),
      $container->get('menu.active_trail'),
      $container->get('menu.link_tree')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $menu_storage, $menu_trail, $menu_tree) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuStorage = $menu_storage;
    $this->menuTrail = $menu_trail;
    $this->menuTree = $menu_tree;
  }

  public function build() {
    $full_trail = array_values($this->menuTrail->getActiveTrailIds($this->configuration['menu_name']));
    $trail = array_slice($full_trail, $this->configuration['depth_min']);

    $params = $this->menuTree->getCurrentRouteMenuTreeParameters($this->configuration['menu_name']);

    $params->maxDepth = $this->configuration['depth_max'];
    $params->minDepth = $this->configuration['depth_min'];
    $params->activeTrail = $trail;

    if ($params->minDepth < 2) {
      $params->expandedParents = [];
    }

    $tree = $this->menuTree->load($this->configuration['menu_name'], $params);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $menu = $this->menuTree->build($tree);

    if ($this->configuration['active_top_level_only']) {
      $plid = count($full_trail) >= $this->configuration['depth_min'] ? $full_trail[$this->configuration['depth_min'] - 1] : 0;
      $this->filterTopLevelItems($menu, $plid);
    }

    if ($this->configuration['expand_items'] == self::EXPAND_NONE) {
      $this->unsetExpandedClass($menu['#items']);
    } elseif ($this->configuration['expand_items'] == self::EXPAND_ACTIVE) {
      $this->unsetExpandedClass($menu['#items'], $trail);
    }

    $wrapper_classes = $this->configuration['wrapper_classes'];
    $libraries = [];

    if ($this->configuration['dropdown']) {
      $wrapper_classes .= ' kifimenu-dropdown';
      $libraries[] = 'kifimenu/dropdown';
    }

    if (!empty($menu['#items'])) {
      return [
        '#theme' => 'kifimenu',
        '#attached' => [
          'library' => $libraries,
        ],
        '#menu' => $menu,
        '#classes' => $wrapper_classes,
        '#id' => $this->configuration['wrapper_id'],
      ];
    } else {
      return [];
    }
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $menus = array_map(function($menu) {
      return (string)$menu->label();
    }, $this->menuStorage->loadMultiple(null));

    asort($menus);

    $form = [];
    $form['menu_name'] = [
      '#type' => 'select',
      '#title' => t('Menu'),
      '#options' => $menus,
      '#default_value' => $this->configuration['menu_name'],
    ];
    $form['depth_min'] = [
      '#type' => 'select',
      '#title' => t('First level'),
      '#options' => array_combine(range(1, 5), range(1, 5)),
      '#default_value' => $this->configuration['depth_min'],
    ];
    $form['depth_max'] = [
      '#type' => 'select',
      '#title' => t('Last level'),
      '#options' => [127 => t('Unlimited')] + $form['depth_min']['#options'],
      '#default_value' => $this->configuration['depth_max'],
    ];
    $form['dropdown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable drop-down features'),
      '#default_value' => $this->configuration['dropdown'],
    ];
    $form['active_top_level_only'] = [
      '#type' => 'checkbox',
      '#title' => t('Active branch only'),
      '#default_value' => $this->configuration['active_top_level_only'],
    ];
    $form['expand_items'] = [
      '#type' => 'select',
      '#title' => t('Expand menu items?'),
      '#default_value' => $this->configuration['expand_items'],
      '#options' => [
        self::EXPAND_NONE => t('Keep menu collapsed'),
        self::EXPAND_ALL => t('Expand all items'),
        self::EXPAND_ACTIVE => t('Expand active trail'),
      ],
    ];
    $form['wrapper_classes'] = [
      '#type' => 'textfield',
      '#title' => t('Wrapper classes'),
      '#default_value' => $this->configuration['wrapper_classes'],
    ];
    $form['wrapper_id'] = [
      '#type' => 'textfield',
      '#title' => t('Wrapper ID'),
      '#default_value' => $this->configuration['wrapper_id'],
      '#description' => $this->t('The ID will also be used for theme suggestions.'),
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['depth_min'] = $form_state->getValue('depth_min');
    $this->configuration['depth_max'] = $form_state->getValue('depth_max');
    $this->configuration['active_top_level_only'] = $form_state->getValue('active_top_level_only');
    $this->configuration['expand_items'] = $form_state->getValue('expand_items');
    $this->configuration['wrapper_classes'] = trim($form_state->getValue('wrapper_classes'));
    $this->configuration['wrapper_id'] = trim($form_state->getValue('wrapper_id'));
    $this->configuration['menu_name'] = $form_state->getValue('menu_name');
    $this->configuration['dropdown'] = $form_state->getValue('dropdown');
    parent::blockSubmit($form, $form_state);
  }

  public function defaultConfiguration() {
    return [
      'depth_min' => 1,
      'depth_max' => 2,
      'active_top_level_only' => 0,
      'menu_name' => 'main',
      'expand_items' => self::EXPAND_NONE,
      'dropdown' => 0,
      'wrapper_classes' => '',
      'wrapper_id' => ''
    ];
  }

  public function getCacheContexts() {
    $contexts = ['route.menu_active_trails:' . $this->configuration['menu_name']];
    return Cache::mergeContexts(parent::getCacheContexts(), $contexts);
  }

  protected function unsetExpandedClass(&$links, $expanded = []) {
    if (empty($links)) {
      return;
    }
    foreach ($links as $id => &$item) {
      if (!in_array($id, $expanded)) {
        $item['is_expanded'] = false;
        $item['is_collapsed'] = true;
        // $item['attributes']->addClass('menu-item--collapsed');
      }

      if ($item['below']) {
        $this->unsetExpandedClass($item['below'], $expanded);
      }
    }
  }

  protected function filterTopLevelItems(&$menu, $top_id) {
    foreach ($menu as $id => $item) {
      if (is_numeric($id) && $item['#original_link']->plid != $top_id) {
        unset($menu[$id]);
      }
    }
  }

  protected function filterTopLevelLinks(&$menu) {
    foreach ($menu as $id => &$item) {
      if (is_numeric($id)) {
//         unset($item['#href']);
        $item['#href'] = '';
      }
    }
  }
}
