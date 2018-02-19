<?php

namespace Drupal\kifimenu\Breadcrumb;

use Drupal\Core\Link;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\kifimenu\BlockConfiguredMenu;

/**
 * NOTE: This builder requires custom configuration from BreadcrumbBlock to be usable, so it is not
 * accessible as a generic service like the standard crumb builders are.
 */
class MenuBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  protected $menuTrail;
  protected $linkManager;
  protected $menuName;

  public function __construct(MenuActiveTrailInterface $active_trail, MenuLinkManagerInterface $link_manager, BlockConfiguredMenu $menu_name) {
    $this->menuTrail = $active_trail;
    $this->linkManager = $link_manager;
    $this->menuName = $menu_name;
  }

  public function applies(RouteMatchInterface $route_match) {
    if (!$this->menuName->getMenuName()) {
      return FALSE;
    }
    return count(array_filter($this->getActiveTrail())) > 0;
  }

  public function build(RouteMatchInterface $route_match) {
    $links = [];

    foreach ($this->getActiveTrail() as $link_id) {
      if ($link_id == '') {
        $links[] = Link::createFromRoute($this->t('Home'), '<front>');
      } else {
        $menu_link = $this->linkManager->createInstance($link_id);
        if ($menu_link->getRouteName()) {
          $links[] = Link::createFromRoute($menu_link->getTitle(), $menu_link->getRouteName(), $menu_link->getRouteParameters());
        }
      }
    }
    $crumb = new Breadcrumb;
    $crumb->setLinks($links);
    $crumb->addCacheContexts(['route']);
    return $crumb;
  }

  private function getActiveTrail() {
    if ($menu_name = $this->menuName->getMenuName()) {
      $trail = $this->menuTrail->getActiveTrailIds($menu_name);
      return array_slice(array_reverse($trail), 0, -1);
    } else {
      return [];
    }
  }
}
